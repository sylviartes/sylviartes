<?php
/**
 * =============================================================================
 *  ADMIN - Enviar Link de Pagamento Stripe ao Cliente
 * =============================================================================
 *
 *  Endpoint POST chamado pelo botão "Enviar link de pagamento" em view.php.
 *  Fluxo:
 *    1. Valida admin autenticado + pedido válido
 *    2. Vai buscar email do cliente + valor_total atual
 *    3. Emite uma fatura dinâmica via criar_fatura_stripe() (config/stripe.php),
 *       com morada de faturação e de envio do cliente
 *    4. Guarda a hosted_invoice_url em pagamento.stripe_payment_link_url
 *    5. Atualiza pedido.estado para 'aguarda_pagamento'
 *    6. Envia email ao cliente via enviar_email_orcamento() (src/email.php)
 *    7. Regista mudança em log_alteracoes_pedido
 *    8. Redirect de volta ao view.php com flag de sucesso
 * =============================================================================
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/stripe.php';
require_once __DIR__ . '/../../../config/csrf.php';
require_once __DIR__ . '/../../../src/email.php';
require_once __DIR__ . '/../auth.php';

// Aceita só POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

csrf_validate();

$pedidoId = (int)($_POST['pedido_id'] ?? 0);
if ($pedidoId <= 0) {
    header("Location: index.php");
    exit;
}

// === 1. Carrega pedido + email do cliente + dados de pagamento ===
$stmt = $conn->prepare("
    SELECT p.id, p.estado, p.valor_total,
           u.nome AS cliente_nome, u.email AS cliente_email,
           u.morada AS cliente_morada, u.codigo_postal AS cliente_cp,
           u.localidade AS cliente_localidade, u.telefone AS cliente_telefone,
           pg.id AS pagamento_id, pg.metodo
    FROM pedido p
    JOIN utilizador u ON u.id = p.utilizador_id
    LEFT JOIN pagamento pg ON pg.pedido_id = p.id
    WHERE p.id = ?
");
$stmt->execute([$pedidoId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    header("Location: index.php?erro=pedido_inexistente");
    exit;
}

// === 2. Validações ===
// Verifica se a coluna stripe_payment_link_url existe (SQL alter_orcamento.sql aplicada)
try {
    $check = $conn->query("SHOW COLUMNS FROM pagamento LIKE 'stripe_payment_link_url'");
    if (!$check->fetch()) {
        header("Location: view.php?id=$pedidoId&erro=" . urlencode("Aplique a SQL docs/db/alter_orcamento.sql antes de enviar links"));
        exit;
    }
} catch (Exception $e) {
    header("Location: view.php?id=$pedidoId&erro=bd_indisponivel");
    exit;
}

if (!stripe_disponivel()) {
    header("Location: view.php?id=$pedidoId&erro=stripe_indisponivel");
    exit;
}

if ($row['valor_total'] <= 0) {
    header("Location: view.php?id=$pedidoId&erro=valor_invalido");
    exit;
}

if (empty($row['cliente_email'])) {
    header("Location: view.php?id=$pedidoId&erro=sem_email");
    exit;
}

// === 3. Coleta descrição dos produtos para mostrar no email ===
$stmtItens = $conn->prepare("
    SELECT dp.quantidade, dp.descricao, pr.nome AS produto_nome
    FROM detalhe_pedido dp
    LEFT JOIN produto pr ON pr.id = dp.produto_id
    WHERE dp.pedido_id = ?
");
$stmtItens->execute([$pedidoId]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

$descricaoItens = [];
foreach ($itens as $it) {
    $linha = ($it['produto_nome'] ?? 'Produto') . ' x' . (int)$it['quantidade'];
    $descricaoItens[] = $linha;
}
$descricao = implode(', ', $descricaoItens);

try {
    // === 4. Cria uma CHECKOUT SESSION no Stripe (Cartão + MB Way + Multibanco) ===
    // Usamos Checkout (em vez de Fatura) porque só assim o MB Way fica disponível.
    $sessao = criar_checkout_session(
        $pedidoId,
        (float)$row['valor_total'],
        'cartao',
        $row['cliente_email'],
        $descricao
    );

    // URL da página de pagamento alojada pela Stripe e ID da sessão (para o webhook)
    $linkUrl   = $sessao->url;
    $sessionId = $sessao->id;

    // === 5. Guarda URL + session_id na BD (atualiza pagamento existente, ou cria novo) ===
    if ($row['pagamento_id']) {
        $stmt = $conn->prepare("
            UPDATE pagamento
            SET stripe_payment_link_url = ?,
                stripe_session_id = ?,
                valor = ?,
                estado_pagamento = 'analise_pagamento'
            WHERE id = ?
        ");
        $stmt->execute([$linkUrl, $sessionId, $row['valor_total'], $row['pagamento_id']]);
    } else {
        // Sem pagamento criado ainda - cria com método 'cartao'
        $stmt = $conn->prepare("
            INSERT INTO pagamento (pedido_id, metodo, valor, estado_pagamento, stripe_payment_link_url, stripe_session_id)
            VALUES (?, 'cartao', ?, 'analise_pagamento', ?, ?)
        ");
        $stmt->execute([$pedidoId, $row['valor_total'], $linkUrl, $sessionId]);
    }

    // === 6. Atualiza estado do pedido + log ===
    if ($row['estado'] !== 'aguarda_pagamento') {
        $estadoAnterior = $row['estado'];
        $stmt = $conn->prepare("UPDATE pedido SET estado = 'aguarda_pagamento' WHERE id = ?");
        $stmt->execute([$pedidoId]);

        $stmt = $conn->prepare("
            INSERT INTO log_alteracoes_pedido (pedido_id, estado_anterior, estado_novo, alterado_por)
            VALUES (?, ?, 'aguarda_pagamento', ?)
        ");
        $stmt->execute([$pedidoId, $estadoAnterior, 'admin#' . ($_SESSION['admin_id'] ?? 0)]);
    }

    // === 7. Envia email à cliente com o link ===
    $emailEnviado = enviar_email_orcamento(
        $row['cliente_email'],
        $row['cliente_nome'],
        $pedidoId,
        (float)$row['valor_total'],
        $linkUrl,
        $descricao
    );

    // Redirect de sucesso (com flag para mostrar mensagem)
    $flag = $emailEnviado ? 'link_enviado' : 'link_gerado_sem_email';
    header("Location: view.php?id=$pedidoId&$flag=1");
    exit;

} catch (Exception $e) {
    // Em caso de erro Stripe, redireciona com mensagem
    error_log("Admin: erro ao enviar link Stripe: " . $e->getMessage());
    $erro = urlencode("Ocorreu um erro ao gerar o link de pagamento.");
    header("Location: view.php?id=$pedidoId&erro_stripe=$erro");
    exit;
}
