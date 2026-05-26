<?php
/**
 * =============================================================================
 *  STRIPE — Retomar Pagamento de Pedido Existente
 * =============================================================================
 *
 *  Quando um cliente cria um pedido com cartão/MB Way mas fecha a janela do
 *  Stripe sem terminar, o pedido fica registado mas sem pagamento. Esta
 *  página gera uma NOVA Checkout Session e redireciona-o para concluir.
 *
 *  Acedido pelo botão "Pagar agora" em cliente/encomenda.php.
 *
 *  Validações de segurança:
 *    1. Cliente tem de estar autenticado
 *    2. O pedido tem de pertencer a este cliente
 *    3. Não pode estar já validado (não vamos cobrar duas vezes)
 *    4. Tem de ser um método online (cartão/mbway)
 * =============================================================================
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/stripe.php';

// Sem login → manda fazer login primeiro
if (!isset($_SESSION['cliente_id'])) {
    header("Location: cliente/login.php");
    exit;
}

$pedidoId = (int)($_GET['pedido_id'] ?? 0);
$clienteId = $_SESSION['cliente_id'];

// Carrega pedido + pagamento + email do cliente numa só query
// (e confirma que o pedido pertence a este cliente)
$stmt = $conn->prepare("
    SELECT p.id, p.valor_total, pg.id AS pag_id, pg.metodo, pg.estado_pagamento, u.email
    FROM pedido p
    JOIN pagamento pg ON pg.pedido_id = p.id
    JOIN utilizador u ON u.id = p.utilizador_id
    WHERE p.id = ? AND p.utilizador_id = ?
");
$stmt->execute([$pedidoId, $clienteId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Pedido não encontrado / não é deste cliente
if (!$row) {
    header("Location: cliente/encomendas.php");
    exit;
}

// Já está pago — não vamos cobrar de novo!
if ($row['estado_pagamento'] === 'validado') {
    header("Location: cliente/encomenda.php?id=" . $pedidoId);
    exit;
}

// Método offline (transferência/dinheiro) — não tem pagamento online
if (!in_array($row['metodo'], ['cartao', 'mbway'], true)) {
    header("Location: cliente/encomenda.php?id=" . $pedidoId);
    exit;
}

if (!stripe_disponivel()) {
    die("Stripe SDK não instalado. Corre: composer require stripe/stripe-php");
}

try {
    // Cria nova sessão Stripe (helper definido em config/stripe.php)
    $session = criar_checkout_session(
        (int)$row['id'],
        (float)$row['valor_total'],
        $row['metodo'],
        $row['email']
    );

    // Substitui o session_id antigo (se houver) pelo novo — o webhook vai
    // procurar pagamento por este novo session_id
    $stmt = $conn->prepare("UPDATE pagamento SET stripe_session_id = ? WHERE id = ?");
    $stmt->execute([$session->id, $row['pag_id']]);

    // Redireciona para a página alojada do Stripe
    header("Location: " . $session->url);
    exit;
} catch (Exception $e) {
    die("Erro ao iniciar pagamento Stripe: " . htmlspecialchars($e->getMessage()));
}
