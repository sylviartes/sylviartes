<?php
/**
 * =============================================================================
 *  GUARD DE AUTENTICAÇÃO - Área Cliente
 * =============================================================================
 *
 *  Pequeno ficheiro que se inclui no topo de TODAS as páginas que requerem
 *  utilizador autenticado (perfil.php, encomendas.php, encomenda.php, etc.).
 *
 *  Funcionamento: se a sessão não tiver $_SESSION['cliente_id'] definido,
 *  significa que o utilizador NÃO fez login → redireciona para login.php
 *  e termina o script (exit). Caso contrário, deixa o resto da página
 *  executar normalmente.
 * =============================================================================
 */

// Inicia a sessão se ainda não estiver iniciada
// (algumas páginas já chamam session_start() noutro sítio - por isso verificamos)
require_once __DIR__ . '/../../config/session.php';

// Se não há cliente logado, manda para o login
if (!isset($_SESSION['cliente_id'])) {
    header("Location: login.php");
    exit();
}
