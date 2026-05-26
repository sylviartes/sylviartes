<?php
/**
 * =============================================================================
 *  LOGOUT DO CLIENTE
 * =============================================================================
 *
 *  Termina a sessão do cliente. Diferente do logout admin (que destrói TUDO),
 *  aqui só removemos as variáveis do cliente — o carrinho de compras (se
 *  existir) é preservado, para o caso de o utilizador querer continuar a
 *  comprar como convidado depois de sair.
 * =============================================================================
 */

require_once __DIR__ . '/../../config/session.php';

// Remove apenas as variáveis de identificação do cliente
unset($_SESSION['cliente_id'], $_SESSION['cliente_nome']);

// Volta à página inicial da loja
header("Location: ../index.php");
exit;
