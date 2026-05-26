<?php
/**
 * =============================================================================
 *  LOGOUT DO ADMIN
 * =============================================================================
 *
 *  Termina a sessão do admin completamente — destrói TODAS as variáveis de
 *  sessão (incluindo eventual carrinho ou login de cliente, embora seja raro
 *  o admin estar também como cliente). Após logout volta para o login.
 * =============================================================================
 */
require_once __DIR__ . '/../../config/session.php';
session_unset();    // Apaga todas as variáveis $_SESSION
session_destroy();  // Destrói o ficheiro de sessão no servidor
header("Location: login.php");
exit;
