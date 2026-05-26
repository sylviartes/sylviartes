<?php
/**
 * =============================================================================
 *  GUARD DE AUTENTICAÇÃO — Área Admin
 * =============================================================================
 *
 *  Incluído no topo de TODAS as páginas administrativas (gestão de produtos,
 *  encomendas, categorias, etc.). Se o admin não estiver autenticado,
 *  redireciona para login.php.
 *
 *  As variáveis $_SESSION['admin_id'] e $_SESSION['admin_nome'] são gravadas
 *  por login.php após autenticação bem-sucedida.
 * =============================================================================
 */

// Inicia sessão se ainda não iniciada
require_once __DIR__ . '/../../config/session.php';

// Sem credenciais admin → fora!
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
?>