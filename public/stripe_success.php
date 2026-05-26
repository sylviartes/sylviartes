<?php
/**
 * =============================================================================
 *  STRIPE — Página de Sucesso
 * =============================================================================
 *
 *  URL para onde o Stripe redireciona o utilizador APÓS o pagamento.
 *  Configurada no helper criar_checkout_session() (config/stripe.php) como
 *  success_url, com o placeholder ?session_id={CHECKOUT_SESSION_ID} que o
 *  Stripe substitui pelo ID real da sessão de pagamento.
 *
 *  IMPORTANTE: esta página NÃO atualiza a base de dados.
 *  A confirmação real do pagamento vem pelo WEBHOOK (stripe_webhook.php),
 *  que é a fonte de verdade. Aqui só:
 *    1. Lemos o session_id (se existir) só para mostrar o nº do pedido
 *    2. Limpamos o carrinho do cliente
 *    3. Mostramos uma mensagem de obrigado
 * =============================================================================
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../src/cart.php';

$sessionId = $_GET['session_id'] ?? '';
$pedidoId = null;
$estadoPagamento = 'analise_pagamento';

// Se temos session_id e o SDK do Stripe está disponível, vamos buscar o
// estado para mostrar mensagem mais informativa
if ($sessionId !== '' && stripe_disponivel()) {
    try {
        stripe_init();
        // Pede ao Stripe os dados desta sessão
        $session = \Stripe\Checkout\Session::retrieve($sessionId);

        // O pedido_id foi guardado em "metadata" quando criámos a sessão
        $pedidoId = $session->metadata->pedido_id ?? null;

        // payment_status:
        //   - 'paid'         → cartão confirmado
        //   - 'unpaid'       → MB Way ainda à espera de confirmação assíncrona
        $estadoPagamento = ($session->payment_status === 'paid') ? 'validado' : 'analise_pagamento';
    } catch (Exception $e) {
        // Se falhar não é grave — o webhook vai tratar de tudo
    }
}

// Esvazia o carrinho — a compra foi feita
clear_cart();

require_once __DIR__ . '/header.php';
?>
<div class="checkout-wrapper" style="text-align:center;">
    <h1 style="color:#2d7a44;">✓ Pagamento recebido</h1>
    <?php if ($pedidoId): ?>
        <p>O seu pedido <strong>#<?php echo (int)$pedidoId; ?></strong> foi registado com sucesso.</p>
    <?php endif; ?>
    <p>
        <?php if ($estadoPagamento === 'validado'): ?>
            Recebemos a confirmação do Stripe. Vamos começar a produção em breve.
        <?php else: ?>
            Estamos a confirmar o pagamento. Receberá atualizações por email.
        <?php endif; ?>
    </p>
    <p style="margin-top:30px;">
        <?php if (isset($_SESSION['cliente_id'])): ?>
            <a href="cliente/encomendas.php" class="checkout-btn" style="width:auto; padding:14px 28px; display:inline-block;">Ver as minhas encomendas</a>
        <?php else: ?>
            <a href="catalogo.php" class="checkout-btn" style="width:auto; padding:14px 28px; display:inline-block;">Continuar a comprar</a>
        <?php endif; ?>
    </p>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
