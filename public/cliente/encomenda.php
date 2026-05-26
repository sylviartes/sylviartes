<?php
/**
 * =============================================================================
 *  DETALHE DE ENCOMENDA — Vista única do cliente
 * =============================================================================
 *
 *  Página com toda a informação sobre uma encomenda específica e onde o
 *  cliente pode tomar acções:
 *
 *    1. CANCELAR pedido
 *       Permitido enquanto o estado for "em_analise" ou "aguarda_pagamento".
 *       Regista a alteração em log_alteracoes_pedido.
 *
 *    2. UPLOAD DE COMPROVATIVO (transferência bancária)
 *       Sobe um JPG/PNG/PDF para a coluna BLOB `pagamento.comprovativo`.
 *       Limite de 5MB. Volta a pôr o pagamento em "analise_pagamento".
 *
 *    3. PAGAR AGORA (cartão / MB Way)
 *       Reabre a sessão Stripe se o cliente fechou a janela sem pagar.
 *       Ver stripe_retomar.php.
 *
 *  SEGURANÇA: Verifica SEMPRE `utilizador_id = $clienteId` em todas as
 *  queries para garantir que ninguém vê/manipula encomendas de outras pessoas.
 * =============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/avaliacoes.php';

$clienteId = $_SESSION['cliente_id'];
$pedidoId = (int)($_GET['id'] ?? 0);

// ID inválido → volta à lista
if ($pedidoId <= 0) {
    header("Location: encomendas.php");
    exit;
}

// Carrega o pedido E confirma que pertence ao cliente logado
// (esta é a verificação crítica de segurança contra IDs adivinhados)
$stmt = $conn->prepare("
    SELECT * FROM pedido WHERE id = ? AND utilizador_id = ?
");
$stmt->execute([$pedidoId, $clienteId]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    // Pedido não existe OU não é deste cliente → fora daqui!
    header("Location: encomendas.php");
    exit;
}

$mensagem = "";  // texto de feedback
$tipoMsg = "";   // 'ok' ou 'erro' (para colorir a caixa)

// =========================================================================
// PROCESSAMENTO DE ACÇÕES (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accao = $_POST['accao'] ?? '';

    // ----- AÇÃO: Cancelar pedido -----
    if ($accao === 'cancelar') {
        // Só permite cancelar enquanto o estado ainda for inicial
        if (in_array($pedido['estado'], ['em_analise', 'aguarda_pagamento'], true)) {
            try {
                // Transação para garantir atomicidade: ou ambos UPDATE+INSERT ocorrem, ou nenhum
                $conn->beginTransaction();
                $estadoAnt = $pedido['estado'];

                // 1) Marca o pedido como cancelado (com dupla verificação de segurança)
                $stmt = $conn->prepare("UPDATE pedido SET estado='cancelado' WHERE id=? AND utilizador_id=?");
                $stmt->execute([$pedidoId, $clienteId]);

                // 2) Regista a alteração no log para auditoria
                $stmtLog = $conn->prepare("
                    INSERT INTO log_alteracoes_pedido (pedido_id, estado_anterior, estado_novo, alterado_por)
                    VALUES (?, ?, 'cancelado', ?)
                ");
                $stmtLog->execute([$pedidoId, $estadoAnt, 'cliente#' . $clienteId]);

                $conn->commit();
                header("Location: encomenda.php?id=" . $pedidoId . "&msg=cancelado");
                exit;
            } catch (Exception $e) {
                $conn->rollBack();
                $mensagem = "Erro ao cancelar: " . $e->getMessage();
                $tipoMsg = "erro";
            }
        } else {
            $mensagem = "Este pedido já não pode ser cancelado.";
            $tipoMsg = "erro";
        }
    }
    // ----- AÇÃO: Upload de comprovativo de transferência -----
    elseif ($accao === 'comprovativo') {
        // Validação básica do upload (variável $_FILES é populada pelo PHP no upload)
        if (!isset($_FILES['comprovativo']) || $_FILES['comprovativo']['error'] !== UPLOAD_ERR_OK) {
            $mensagem = "Selecione um ficheiro válido.";
            $tipoMsg = "erro";
        } else {
            $tamanho = $_FILES['comprovativo']['size'];
            // mime_content_type lê os "magic bytes" do ficheiro — mais seguro
            // do que confiar na extensão (que pode ser falsificada)
            $tipo = mime_content_type($_FILES['comprovativo']['tmp_name']);
            $tiposOk = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

            if ($tamanho > 5 * 1024 * 1024) {  // 5 MB em bytes
                $mensagem = "Ficheiro demasiado grande (máx 5MB).";
                $tipoMsg = "erro";
            } elseif (!in_array($tipo, $tiposOk, true)) {
                $mensagem = "Apenas JPG, PNG, GIF ou PDF são permitidos.";
                $tipoMsg = "erro";
            } else {
                // Lê o ficheiro inteiro para variável e guarda na BD como BLOB.
                // Para ficheiros grandes seria melhor guardar em pasta + caminho na BD,
                // mas para a PAP guardar em BLOB simplifica a estrutura.
                $blob = file_get_contents($_FILES['comprovativo']['tmp_name']);
                $stmt = $conn->prepare("
                    UPDATE pagamento
                    SET comprovativo = ?, estado_pagamento = 'analise_pagamento'
                    WHERE pedido_id = ?
                ");
                // bindValue com PARAM_LOB é a forma correcta de passar binários ao PDO
                $stmt->bindValue(1, $blob, PDO::PARAM_LOB);
                $stmt->bindValue(2, $pedidoId, PDO::PARAM_INT);
                $stmt->execute();

                header("Location: encomenda.php?id=" . $pedidoId . "&msg=comprovativo");
                exit;
            }
        }
    }
}

// Mensagens "flash" passadas via querystring depois de um redirect
// (Pattern Post-Redirect-Get: evita re-submissão se o utilizador atualizar página)
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'cancelado') { $mensagem = "Pedido cancelado."; $tipoMsg = "ok"; }
    if ($_GET['msg'] === 'comprovativo') { $mensagem = "Comprovativo enviado. Estamos a validar o pagamento."; $tipoMsg = "ok"; }
}

// =========================================================================
// CARREGAMENTO DE DADOS PARA A VIEW
// =========================================================================

// Recarrega o pedido (depois de eventuais UPDATEs feitos acima)
$stmt = $conn->prepare("SELECT * FROM pedido WHERE id=?");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

// Linha de pagamento associada ao pedido
$stmt = $conn->prepare("SELECT * FROM pagamento WHERE pedido_id = ?");
$stmt->execute([$pedidoId]);
$pagamento = $stmt->fetch(PDO::FETCH_ASSOC);

// Itens (detalhe do pedido) — JOIN com produto para ir buscar o nome
// (LEFT JOIN porque o produto pode ter sido eliminado entretanto)
$stmt = $conn->prepare("
    SELECT dp.*, pr.nome AS produto_nome
    FROM detalhe_pedido dp
    LEFT JOIN produto pr ON pr.id = dp.produto_id
    WHERE dp.pedido_id = ?
");
$stmt->execute([$pedidoId]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Flags de UI: que botões/secções mostrar? ===
$podeCancelar = in_array($pedido['estado'], ['em_analise', 'aguarda_pagamento'], true);
// Mostrar botão "Pagar agora" (só faz sentido para Stripe não validado)
$pagInline = $pagamento && in_array($pagamento['metodo'], ['cartao', 'mbway'], true)
             && $pagamento['estado_pagamento'] !== 'validado'
             && $pedido['estado'] !== 'cancelado';

// Mostrar zona de upload de comprovativo (só para transferência não validada)
$podeUpload = $pagamento && $pagamento['metodo'] === 'transferencia'
              && $pagamento['estado_pagamento'] !== 'validado'
              && $pedido['estado'] !== 'cancelado';

function estadoLabel2($e) {
    return [
        'aguarda_orcamento' => 'Aguarda Orçamento',
        'em_analise' => 'Em análise',
        'aguarda_pagamento' => 'Aguarda pagamento',
        'em_producao' => 'Em produção',
        'concluido' => 'Concluído',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ][$e] ?? $e;
}
function pagLabel2($e) {
    return [
        'analise_pagamento' => 'A validar',
        'validado' => 'Pago',
        'recusado' => 'Recusado',
    ][$e] ?? '—';
}
function metodoLabel($m) {
    return [
        'cartao' => 'Cartão',
        'mbway' => 'MB Way',
        'transferencia' => 'Transferência Bancária',
        'dinheiro' => 'Dinheiro no levantamento',
    ][$m] ?? $m;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encomenda #<?php echo (int)$pedido['id']; ?> — SylviArtes</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cliente_style.css">
</head>
<body>
    <div class="cli-wrapper">
        <a href="encomendas.php" class="cli-back">← As minhas encomendas</a>

        <?php if ($mensagem): ?>
            <div class="<?php echo $tipoMsg === 'ok' ? 'auth-sucesso' : 'auth-erro'; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <div class="cli-section">
            <h2>Encomenda #<?php echo (int)$pedido['id']; ?></h2>
            <p style="color:#636e72; margin-bottom:18px;">
                Feita em <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['data']))); ?>
            </p>

            <p>
                <strong>Estado:</strong>
                <span class="cli-badge b-<?php echo htmlspecialchars($pedido['estado']); ?>">
                    <?php echo htmlspecialchars(estadoLabel2($pedido['estado'])); ?>
                </span>
            </p>

            <?php if ($pedido['estado'] === 'aguarda_orcamento'): ?>
                <div style="background:#fff8fa; border-left:4px solid #d66d7f; padding:14px 18px; border-radius:8px; margin: 14px 0; color:#555;">
                    <i class="fas fa-info-circle" style="color:#d66d7f;"></i>
                    <strong>Estamos a analisar o seu pedido.</strong>
                    Vamos contactá-la(o) em breve por telefone ou email para confirmar
                    detalhes e enviar o orçamento final com link de pagamento.
                </div>
            <?php elseif ($pedido['estado'] === 'aguarda_pagamento'): ?>
                <div style="background:#f0f9ff; border-left:4px solid #3b82f6; padding:14px 18px; border-radius:8px; margin: 14px 0; color:#555;">
                    <i class="fas fa-envelope" style="color:#3b82f6;"></i>
                    <strong>Orçamento enviado!</strong>
                    Verifique o email com o link de pagamento.
                </div>
            <?php elseif (in_array($pedido['estado'], ['concluido', 'entregue'], true)): ?>
                <?php
                // Convite a avaliar — só se ainda não tiver avaliado o item de inspiração
                $inspiracaoId = (int)($pedido['portfolio_inspiracao_id'] ?? 0);
                $podeAvaliarEste = false;
                if ($inspiracaoId > 0 && avaliacoes_disponiveis($conn)) {
                    $podeAvaliarEste = cliente_pode_avaliar($conn, $clienteId, $inspiracaoId);
                }
                ?>
                <?php if ($podeAvaliarEste): ?>
                    <div style="background:linear-gradient(135deg,#fff8fa,#fdf0f4); border:1px solid #f0c8d2; border-radius:10px; padding:16px 20px; margin:14px 0; display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap;">
                        <div>
                            <strong style="color:#d66d7f;"><i class="fas fa-star"></i> Como foi a sua experiência?</strong>
                            <div style="color:#555; font-size:14px; margin-top:4px;">A sua opinião ajuda outras clientes a escolher.</div>
                        </div>
                        <a href="../produto.php?id=<?= $inspiracaoId ?>#avaliar" class="cli-btn">
                            Deixar Avaliação
                        </a>
                    </div>
                <?php elseif ($inspiracaoId > 0): ?>
                    <div style="background:#f0fdf4; border-left:4px solid #22c55e; padding:14px 18px; border-radius:8px; margin:14px 0; color:#555;">
                        <i class="fas fa-check-circle" style="color:#22c55e;"></i>
                        <strong>Pedido entregue!</strong> Obrigado pela sua confiança.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($pagamento): ?>
                <p>
                    <strong>Pagamento:</strong>
                    <?php echo htmlspecialchars(metodoLabel($pagamento['metodo'])); ?> —
                    <span class="cli-badge b-<?php echo htmlspecialchars($pagamento['estado_pagamento']); ?>">
                        <?php echo htmlspecialchars(pagLabel2($pagamento['estado_pagamento'])); ?>
                    </span>
                </p>
            <?php endif; ?>

            <p><strong>Entrega:</strong>
                <?php echo $pedido['tipo_entrega'] === 'domicilio' ? 'Ao domicílio' : 'Levantamento no atelier'; ?>
                <?php if ($pedido['tipo_entrega'] === 'domicilio'): ?>
                    — <?php echo htmlspecialchars($pedido['morada_entrega']); ?>
                <?php endif; ?>
            </p>
            <p><strong>Prazo desejado:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($pedido['prazo_entrega_desejado']))); ?></p>

            <?php if (!empty($pedido['observacoes'])): ?>
                <p><strong>Observações:</strong> <?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></p>
            <?php endif; ?>
        </div>

        <div class="cli-section">
            <h2>Produtos</h2>
            <table class="cli-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Preço unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $it): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($it['produto_nome'] ?? 'Produto removido'); ?></strong>
                                <?php if (!empty($it['descricao'])): ?>
                                    <br><small style="color:#636e72;"><?php echo htmlspecialchars($it['descricao']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int)$it['quantidade']; ?></td>
                            <td><?php echo number_format($it['preco_unitario'] ?? 0, 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format(($it['preco_unitario'] ?? 0) * $it['quantidade'], 2, ',', '.'); ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3" style="text-align:right;"><strong>Custo de envio</strong></td>
                        <td><?php echo number_format($pedido['custo_envio'], 2, ',', '.'); ?> €</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align:right;"><strong>Total</strong></td>
                        <td><strong style="color:#d66d7f;"><?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?> €</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if ($pagInline): ?>
            <div class="cli-section">
                <h2>Pagar agora</h2>
                <p>Foi escolhido <strong><?php echo htmlspecialchars(metodoLabel($pagamento['metodo'])); ?></strong>. Clique abaixo para concluir o pagamento na plataforma segura do Stripe.</p>
                <a class="cli-btn" href="../stripe_retomar.php?pedido_id=<?php echo (int)$pedido['id']; ?>">
                    Pagar <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?> €
                </a>
            </div>
        <?php endif; ?>

        <?php if ($podeUpload): ?>
            <div class="cli-section">
                <h2>Comprovativo de transferência</h2>
                <p>Faça a transferência para o IBAN abaixo e envie aqui o comprovativo (JPG, PNG ou PDF, máx 5MB).</p>
                <p style="background:#fff8fa; padding:12px 14px; border-radius:10px; border:1px dashed #e8a4b0;">
                    <strong>IBAN:</strong> PT50 0000 0000 0000 0000 0000 0<br>
                    <strong>Beneficiário:</strong> SylviArtes<br>
                    <strong>Referência:</strong> Pedido #<?php echo (int)$pedido['id']; ?>
                </p>

                <?php if (!empty($pagamento['comprovativo'])): ?>
                    <p style="color:#1f6b35;">✓ Já enviou um comprovativo. Pode enviar outro para substituir.</p>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accao" value="comprovativo">
                    <input type="file" name="comprovativo" accept="image/*,application/pdf" required style="margin:14px 0;">
                    <br>
                    <button type="submit" class="cli-btn">Enviar comprovativo</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($podeCancelar): ?>
            <div class="cli-section">
                <h2>Cancelar encomenda</h2>
                <p>Pode cancelar enquanto o estado for <em>Em análise</em> ou <em>Aguarda pagamento</em>.</p>
                <form method="POST" onsubmit="return confirm('Tem a certeza que quer cancelar este pedido?');">
                    <input type="hidden" name="accao" value="cancelar">
                    <button type="submit" class="cli-btn cli-btn-danger">Cancelar pedido</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
