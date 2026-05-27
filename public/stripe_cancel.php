<?php
/**
 * =============================================================================
 *  STRIPE — Página de Cancelamento
 * =============================================================================
 *
 *  Para onde o Stripe redireciona o utilizador se ele clicar em "Voltar"
 *  ou fechar o pagamento sem concluir. Configurada como cancel_url em
 *  criar_checkout_session().
 *
 *  IMPORTANTE: o pedido NÃO é apagado da BD. Fica registado com
 *  estado='em_analise' e estado_pagamento='analise_pagamento'. O cliente
 *  pode retomar o pagamento depois através da sua área de cliente
 *  (botão "Pagar agora" em encomenda.php → stripe_retomar.php).
 * =============================================================================
 */

require_once __DIR__ . '/../config/session.php';
$pedidoId = (int)($_GET['pedido_id'] ?? 0);  // ID do pedido afetado
$pageTitle = 'Pagamento Cancelado';
require_once __DIR__ . '/header.php';
?>
<div class="checkout-wrapper" style="text-align:center;">
    <h1 style="color:#8b1e2d;">Pagamento cancelado</h1>
    <p>O pagamento foi interrompido. O pedido <?php if ($pedidoId): ?><strong>#<?php echo $pedidoId; ?></strong> <?php endif; ?>continua registado e pode ser pago mais tarde a partir da sua área de cliente.</p>
    <p style="margin-top:30px;">
        <?php if (isset($_SESSION['cliente_id']) && $pedidoId): ?>
            <a href="cliente/encomenda.php?id=<?php echo $pedidoId; ?>" class="checkout-btn" style="width:auto; padding:14px 28px; display:inline-block;">Ir ao pedido</a>
        <?php else: ?>
            <a href="pedir-orcamento.php" class="checkout-btn" style="width:auto; padding:14px 28px; display:inline-block;">Fazer novo pedido</a>
        <?php endif; ?>
    </p>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
