<?php
/**
 * =============================================================================
 *  CARRINHO — SylviArtes
 * =============================================================================
 *
 *  O site usa um modelo de orçamento (sem preços fixos), por isso não há um
 *  carrinho de compras normal. Esta função é usada apenas para limpar a sessão
 *  do carrinho depois de uma compra via Stripe ser concluída com sucesso.
 * =============================================================================
 */

/**
 * Esvazia o carrinho — chamado em stripe_success.php depois do pagamento.
 */
function clear_cart() {
    // Remove o array do carrinho da sessão do utilizador
    $_SESSION['cart'] = [];
}
