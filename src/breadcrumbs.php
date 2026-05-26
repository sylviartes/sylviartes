<?php
/**
 * =============================================================================
 *  BREADCRUMBS — Migalhas de Navegação
 * =============================================================================
 *
 *  Função utilitária que mostra o caminho hierárquico da página atual
 *  (ex: Início > Catálogo > Toalhas > Toalha XPTO). Ajuda o utilizador
 *  a saber onde está e a navegar facilmente para níveis superiores.
 *
 *  Uso:
 *      render_breadcrumbs([
 *          ['nome' => 'Catálogo', 'url' => 'catalogo.php'],
 *          ['nome' => $produto['nome']]   // último item sem URL = página atual
 *      ]);
 * =============================================================================
 */

function render_breadcrumbs(array $items): string
{
    $html = '<nav aria-label="breadcrumb" style="margin: 16px 0 24px;">';
    $html .= '<ol style="list-style:none; padding:0; margin:0; display:flex; flex-wrap:wrap; gap:8px; font-size:14px; align-items:center;">';

    // Primeiro item é sempre "Início"
    $html .= '<li><a href="index.php" style="color:#999; text-decoration:none;">'
           . '<i class="fas fa-home"></i> Início</a></li>';

    foreach ($items as $i => $item) {
        $html .= '<li style="color:#ccc;">›</li>';
        $isUltimo = ($i === count($items) - 1);

        if ($isUltimo || empty($item['url'])) {
            // Último item — não é link, fica em destaque
            $html .= '<li style="color:#d66d7f; font-weight:600;">'
                   . htmlspecialchars($item['nome']) . '</li>';
        } else {
            $html .= '<li><a href="' . htmlspecialchars($item['url']) . '" style="color:#999; text-decoration:none;">'
                   . htmlspecialchars($item['nome']) . '</a></li>';
        }
    }

    $html .= '</ol></nav>';
    return $html;
}
