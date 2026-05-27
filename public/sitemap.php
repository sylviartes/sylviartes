<?php
/**
 * sitemap.php — Mapa do site em formato XML
 *
 * O Google usa este ficheiro para descobrir todas as páginas do site.
 * Inclui as páginas estáticas (homepage, portfólio, sobre, contacto)
 * e gera automaticamente uma entrada para cada produto visível na BD.
 *
 * URL: https://sylviartes.pt/sitemap.php
 */

require_once __DIR__ . '/../config/db.php';

// Diz ao browser (e ao Google) que isto é XML, não HTML
header('Content-Type: application/xml; charset=utf-8');

// URL base do site — muda quando tiveres domínio próprio
$base = 'https://sylviartes.pt';

// Busca todos os produtos visíveis para incluir no sitemap
$stmt = $conn->query("SELECT id, nome FROM produto WHERE visivel_catalogo = 1 ORDER BY id");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data de hoje no formato que o Google espera (AAAA-MM-DD)
$hoje = date('Y-m-d');
?>
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <!-- Página inicial -->
    <url>
        <loc><?= $base ?>/</loc>
        <lastmod><?= $hoje ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Portfólio / Catálogo -->
    <url>
        <loc><?= $base ?>/catalogo.php</loc>
        <lastmod><?= $hoje ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Sobre Nós -->
    <url>
        <loc><?= $base ?>/sobre.php</loc>
        <lastmod><?= $hoje ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>

    <!-- Contacto -->
    <url>
        <loc><?= $base ?>/contacto.php</loc>
        <lastmod><?= $hoje ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>

    <!-- Pedir Orçamento -->
    <url>
        <loc><?= $base ?>/pedir-orcamento.php</loc>
        <lastmod><?= $hoje ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Uma entrada por cada produto visível -->
    <?php foreach ($produtos as $p): ?>
    <url>
        <loc><?= $base ?>/produto.php?id=<?= (int)$p['id'] ?></loc>
        <lastmod><?= $hoje ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>

</urlset>
