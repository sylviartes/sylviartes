<?php
/**
 * =============================================================================
 *  STRIPE - Página de Cancelamento
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
<div class="checkout-wrapper" style="text-align:center; padding:60px 20px;">
    <h1 style="color:#8b1e2d;">Pagamento cancelado</h1>
    <p style="max-width:560px; margin:14px auto 0; color:#555; font-size:17px; line-height:1.6;">
        O pagamento foi interrompido. Não se preocupe: o seu pedido continua registado
        e pode ser pago mais tarde a partir da sua área de cliente.
    </p>
    <p style="margin-top:32px;">
        <?php
        // Botão principal em rosa da marca (estilo inline para não depender do CSS).
        $btnRosa = 'display:inline-flex; align-items:center; gap:9px; padding:15px 32px;'
                 . 'background:linear-gradient(135deg,#d66d7f,#bf5b6d); color:#fff; text-decoration:none;'
                 . 'border-radius:999px; font-weight:700; font-size:16px; box-shadow:0 8px 20px rgba(214,109,127,0.25);';
        ?>
        <?php if (isset($_SESSION['cliente_id']) && $pedidoId): ?>
            <a href="cliente/encomenda.php?id=<?php echo $pedidoId; ?>" style="<?php echo $btnRosa; ?>">
                <i class="fas fa-credit-card"></i> Retomar pagamento
            </a>
        <?php else: ?>
            <a href="pedir-orcamento.php" style="<?php echo $btnRosa; ?>">
                <i class="fas fa-paper-plane"></i> Fazer novo pedido
            </a>
        <?php endif; ?>
    </p>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
