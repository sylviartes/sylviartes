<?php
/**
 * =============================================================================
 *  PÁGINA 404 — Página Não Encontrada
 * =============================================================================
 *
 *  Página de erro personalizada para quando um URL não corresponde a nenhuma
 *  página existente. Para a usar automaticamente, criar um .htaccess na raiz
 *  do public/ com:
 *      ErrorDocument 404 /public/404.php
 *
 *  Em alternativa, pode ser invocada manualmente noutras páginas:
 *      header("HTTP/1.1 404 Not Found");
 *      include __DIR__ . '/404.php'; exit;
 * =============================================================================
 */
http_response_code(404);
$pageTitle = 'Página Não Encontrada';
require_once __DIR__ . '/header.php';
?>

<style>
.erro-404 {
    text-align: center;
    padding: 80px 20px;
    max-width: 600px;
    margin: 0 auto;
}
.erro-404 .codigo {
    font-family: 'Playfair Display', serif;
    font-size: 120px;
    font-weight: 700;
    color: #d66d7f;
    line-height: 1;
    margin: 0;
    background: linear-gradient(135deg, #c95f7a, #e8a4b0);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.erro-404 h2 {
    font-family: 'Playfair Display', serif;
    color: #2d3436;
    margin: 16px 0;
}
.erro-404 p {
    color: #636e72;
    font-size: 17px;
    margin-bottom: 30px;
}
.erro-404 .acoes {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}
.erro-404 .btn-rosa {
    display: inline-block;
    padding: 12px 28px;
    background: linear-gradient(135deg, #c95f7a, #d6788b);
    color: #fff;
    text-decoration: none;
    border-radius: 999px;
    font-weight: 600;
    transition: all 0.25s;
}
.erro-404 .btn-rosa:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(201,95,122,0.25); }
.erro-404 .btn-ghost {
    display: inline-block;
    padding: 12px 28px;
    background: #fff;
    color: #d66d7f !important;
    border: 2px solid #d66d7f;
    border-radius: 999px;
    font-weight: 600;
    text-decoration: none;
}
</style>

<div class="erro-404">
    <h1 class="codigo">404</h1>
    <h2>Esta página perdeu-se na costura...</h2>
    <p>A página que procura não existe ou foi removida. Mas não se preocupe — temos muitas peças bonitas à sua espera!</p>
    <div class="acoes">
        <a href="index.php" class="btn-rosa">🏠 Voltar à página inicial</a>
        <a href="catalogo.php" class="btn-ghost">Ver catálogo</a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
