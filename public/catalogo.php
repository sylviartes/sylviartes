<?php
/**
 * =============================================================================
 *  PORTFÓLIO - Galeria de Trabalhos Realizados
 * =============================================================================
 *
 *  Mostra os bordados que a SylviArtes já fez, agrupados por categoria.
 *  Cada item é uma "inspiração" - clicar abre o detalhe com galeria de fotos
 *  e botão para pedir orçamento de algo parecido.
 *
 *  Não há preços visíveis nem checkout - todos os pedidos passam por
 *  pedir-orcamento.php (form único). Cada peça é personalizada.
 *
 *  Mantém pesquisa por nome/descrição e filtro por categoria.
 * =============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/avaliacoes.php';   // funções de estrelas/média
require_once __DIR__ . '/../src/breadcrumbs.php';
// Título e descrição para esta página
$pageTitle       = 'Portfólio de Bordados';
$pageDescription = 'Veja os bordados e trabalhos de costura artesanal da SylviArtes. Inspire-se e peça o seu personalizado.';
require_once __DIR__ . '/header.php';

// === FILTROS ===
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$categoriaFiltro = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$ordem = $_GET['ordem'] ?? 'padrao';

// Mapa de ordenações permitidas → cláusulas SQL.
// Usar lista branca evita SQL injection (não inserimos $_GET direto na query).
$ordensValidas = [
    'padrao'      => 'p.nome ASC',
    'recente'     => 'p.id DESC',
];
$orderBy = $ordensValidas[$ordem] ?? $ordensValidas['padrao'];

// Categorias para o filtro lateral
// Inclui preco_referencia se a coluna existir (ALTER alter_orcamento.sql aplicado)
$temPrecoRef = false;
try {
    $check = $conn->query("SHOW COLUMNS FROM categoria LIKE 'preco_referencia'");
    $temPrecoRef = (bool)$check->fetch();
} catch (Exception $e) { /* ignora */ }

$colsCategoria = $temPrecoRef ? "id, nome, preco_referencia" : "id, nome";
$stmt = $conn->query("SELECT $colsCategoria FROM categoria ORDER BY nome");
$todasCategorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Conta quantos produtos visíveis existem em cada categoria
// (para mostrar "(N)" ao lado do nome da categoria no filtro lateral)
$stmtContagem = $conn->query("
    SELECT categoria_id, COUNT(*) AS total
    FROM produto
    WHERE visivel_catalogo = 1
    GROUP BY categoria_id
");
$contagemPorCategoria = [];
foreach ($stmtContagem->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $contagemPorCategoria[(int)$row['categoria_id']] = (int)$row['total'];
}

// Detetar estrutura da base de dados
$temMime = false;
$temFicheiroNaGaleria = false;

try {
    $stmt = $conn->query("SHOW COLUMNS FROM produto LIKE 'imagem_mime'");
    $temMime = !empty($stmt->fetch(PDO::FETCH_ASSOC));

    $stmt = $conn->query("SHOW COLUMNS FROM produto_imagem LIKE 'imagem'");
    $temFicheiroNaGaleria = !empty($stmt->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
}

/**
 * Obtém todas as imagens de um produto
 */
function get_todas_imagens_produto(PDO $conn, array $prod, bool $temMime, bool $temFicheiroNaGaleria): array {
    $imgs = [];
    $produto_id = (int)$prod['id'];

    if ($temFicheiroNaGaleria) {
        $sqlGaleria = "SELECT imagem FROM produto_imagem WHERE produto_id = :produto_id ORDER BY ordem ASC";
        $stmt = $conn->prepare($sqlGaleria);
        $stmt->execute([':produto_id' => $produto_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (!empty($row['imagem'])) {
                $caminho = 'imagens/produtos/' . $row['imagem'];
                if (file_exists(__DIR__ . '/' . $caminho)) {
                    $imgs[] = $caminho;
                }
            }
        }
    }

    // Fallback para imagem principal da tabela produto (campo legado)
    if (empty($imgs)) {
        if (isset($prod['imagem']) && !empty($prod['imagem'])) {
            $caminho = 'imagens/produtos/' . $prod['imagem'];
            if (file_exists(__DIR__ . '/' . $caminho)) {
                $imgs[] = $caminho;
            }
        }
        // Nota: o ramo de fallback para BLOB (base64) foi removido - as imagens
        // são guardadas como nome de ficheiro desde a migração para produto_imagem.
    }

    if (empty($imgs)) {
        $imgs[] = 'imagens/logo_sylviartes.png';
    }

    return $imgs;
}
?>

<style>
/* Anula o padding do .pagina-main - o catalogo-container gere o seu próprio espaçamento */
main { padding: 0 !important; max-width: 100% !important; }

.catalogo-container { max-width: 1280px; margin: 0 auto; padding: 20px; }
.catalogo-layout { display: flex; gap: 28px; margin-top: 36px; align-items: flex-start; }

/* ----- Barra lateral de filtros ----- */
.catalogo-filtros {
    flex: 0 0 270px; background: #fff; padding: 24px;
    border-radius: 16px; border: 1px solid #f0e3e7;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    position: sticky; top: 20px;
}
.catalogo-filtros h3 {
    font-family: 'Playfair Display', serif;
    font-size: 19px; color: #2d3436; margin: 0 0 18px;
    display: flex; align-items: center; gap: 8px;
}
.catalogo-filtros h3 i { color: #d66d7f; font-size: 16px; }
.catalogo-filtros label {
    display: block; font-size: 12px; font-weight: 600;
    color: #8a6070; text-transform: uppercase; letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.catalogo-filtros input, .catalogo-filtros select {
    width: 100%; padding: 12px 14px; margin: 0 0 18px;
    border: 1px solid #e8e8e8; border-radius: 10px;
    box-sizing: border-box; font-family: inherit; font-size: 14px;
    background: #fff; transition: border-color 0.15s, box-shadow 0.15s;
}
.catalogo-filtros input:focus, .catalogo-filtros select:focus {
    border-color: #d66d7f; outline: none;
    box-shadow: 0 0 0 3px rgba(214,109,127,0.12);
}
.btn-filtrar {
    width: 100%; padding: 13px; border: none; border-radius: 999px;
    background: linear-gradient(135deg, #d66d7f, #bf5b6d); color: #fff;
    font-family: inherit; font-weight: 600; font-size: 14px; cursor: pointer;
    transition: box-shadow 0.2s, transform 0.2s;
}
.btn-filtrar:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(201,95,122,0.28); }

.catalogo-conteudo { flex: 1; min-width: 0; }

/* ----- Cabeçalho de cada secção de categoria ----- */
.categoria-titulo {
    font-family: 'Playfair Display', serif;
    font-size: 24px; margin: 32px 0 20px; color: #2d3436;
    display: flex; align-items: center; gap: 12px;
}
.categoria-titulo::before {
    content: ''; width: 4px; height: 26px; border-radius: 4px;
    background: #d66d7f; flex-shrink: 0;
}
.categoria-titulo .conta {
    font-family: 'Poppins', sans-serif; font-size: 12px; font-weight: 600;
    color: #d66d7f; background: #fff0f3; padding: 3px 12px; border-radius: 999px;
}
.produtos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 22px; margin-bottom: 48px; }

/* ----- Cartão de trabalho ----- */
.produto-card {
    background: #fff; border-radius: 16px; overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    border: 1px solid #f0e3e7;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    display: flex; flex-direction: column; height: 100%;
}
.produto-card:hover { transform: translateY(-4px); border-color: #e8a4b0; box-shadow: 0 12px 26px rgba(214,109,127,0.12); }

.produto-img-box { height: 240px; background: #fdf6f8; position: relative; cursor: zoom-in; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.produto-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
.produto-card:hover .produto-img { transform: scale(1.04); }
/* Overlay escuro + ícone de lupa centrado ao fazer hover */
.produto-img-box::before {
    content: '';
    position: absolute; inset: 0;
    background: rgba(30,10,20,0);
    transition: background 0.3s;
    z-index: 1;
}
.produto-img-box::after {
    content: '\f00e'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%) scale(0.7);
    width: 52px; height: 52px; border-radius: 50%;
    background: rgba(255,255,255,0.92); color: #d66d7f;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; opacity: 0;
    transition: opacity 0.25s, transform 0.25s;
    z-index: 2;
}
.produto-card:hover .produto-img-box::before { background: rgba(30,10,20,0.22); }
.produto-card:hover .produto-img-box::after { opacity: 1; transform: translate(-50%,-50%) scale(1); }

.produto-info { padding: 18px 20px; flex-grow: 1; display: flex; flex-direction: column; }
.produto-nome { font-size: 16px; font-weight: 600; color: #2d3436; text-decoration: none; margin-bottom: 8px; transition: color 0.2s; }
.produto-nome:hover { color: #d66d7f; }

.produto-desc {
    font-size: 13.5px;
    color: #777;
    margin-bottom: 16px;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 40px;
}

.produto-footer { margin-top: auto; }
.btn-orcamento {
    display: block; text-align: center; text-decoration: none;
    background: #fff8fa; color: #d66d7f; border: 1px solid #f0c8d2;
    padding: 12px; border-radius: 999px; width: 100%;
    font-weight: 600; font-size: 14px; cursor: pointer;
    transition: background 0.2s, color 0.2s;
}
.btn-orcamento:hover { background: #d66d7f; color: #fff; border-color: #d66d7f; }

/* ============================================================
   LIGHTBOX / MODAL ZOOM - redesign completo
   ============================================================ */

/* Fundo com efeito frosted glass em vez de preto sólido */
.modal-zoom {
    display: none;
    position: fixed; inset: 0; z-index: 99999;
    background: rgba(10, 6, 14, 0.82);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    justify-content: center; align-items: center;
    flex-direction: column; gap: 18px;
    /* Animação de abertura */
    animation: modalAbrir 0.22s ease;
}
@keyframes modalAbrir {
    from { opacity: 0; transform: scale(0.98); }
    to   { opacity: 1; transform: scale(1); }
}

/* Imagem principal com sombra e transição de fade entre fotos */
.modal-conteudo {
    max-width: min(88vw, 900px);
    max-height: 70vh;
    object-fit: contain;
    border-radius: 10px;
    box-shadow: 0 32px 80px rgba(0,0,0,0.55);
    transition: opacity 0.18s ease;
    display: block;
}

/* Contador "2 / 3" - pílula no topo ao centro */
.modal-contador {
    position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.18);
    color: rgba(255,255,255,0.90);
    font-size: 13px; font-weight: 500; font-family: 'Poppins', sans-serif;
    padding: 6px 18px; border-radius: 999px;
    white-space: nowrap;
}

/* Botão fechar - círculo glass top-right */
.modal-fechar {
    position: fixed; top: 16px; right: 20px;
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(255,255,255,0.10);
    border: 1.5px solid rgba(255,255,255,0.18);
    color: #fff; font-size: 17px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background 0.2s; z-index: 10;
}
.modal-fechar:hover { background: rgba(255,255,255,0.22); }

/* Setas de navegação - glass com hover rosa */
.modal-nav {
    position: fixed; top: 50%; transform: translateY(-50%);
    width: 50px; height: 50px; border-radius: 50%;
    background: rgba(255,255,255,0.10);
    border: 1.5px solid rgba(255,255,255,0.18);
    color: #fff; font-size: 17px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background 0.2s, border-color 0.2s, transform 0.2s;
    z-index: 10;
}
.modal-nav:hover {
    background: rgba(214,109,127,0.55);
    border-color: rgba(214,109,127,0.65);
    transform: translateY(-50%) scale(1.08);
}
.modal-prev { left: 18px; }
.modal-next { right: 18px; }

/* Tira de miniaturas no fundo - scroll horizontal se houver muitas */
.modal-thumbs {
    display: flex; gap: 8px;
    padding: 8px 14px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    max-width: min(88vw, 600px);
    overflow-x: auto;
    scrollbar-width: none; /* esconde scrollbar no Firefox */
}
.modal-thumbs::-webkit-scrollbar { display: none; }

.modal-thumb {
    width: 54px; height: 54px; border-radius: 8px;
    overflow: hidden; flex-shrink: 0;
    border: 2px solid transparent;
    opacity: 0.50; cursor: pointer;
    transition: opacity 0.15s, border-color 0.15s;
    background: none; padding: 0;
}
.modal-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.modal-thumb:hover { opacity: 0.80; }
/* Miniatura ativa: borda rosa + opacidade total */
.modal-thumb.ativo { border-color: #d66d7f; opacity: 1; }

/* Botão toggle - só aparece em mobile */
.catalogo-filtros-toggle {
    display: none;
    width: 100%;
    padding: 13px 20px;
    background: #fff;
    border: 1px solid #f0e3e7;
    border-radius: 12px;
    font-family: inherit;
    font-weight: 600;
    font-size: 14px;
    color: #d66d7f;
    cursor: pointer;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: background 0.2s;
}
.catalogo-filtros-toggle:hover { background: #fff8fa; }
.catalogo-filtros-toggle .toggle-seta { margin-left: auto; font-size: 12px; }

@media (max-width: 768px) {
    /* align-items: stretch garante que o conteúdo ocupa 100% da largura disponível
       (o padrão flex-start encolhia o conteúdo para zero se a BD estivesse vazia) */
    .catalogo-layout { flex-direction: column; align-items: stretch; }
    /* Sidebar oculta por defeito - revelada ao clicar no botão toggle */
    .catalogo-filtros { width: 100%; position: static; display: none; }
    .catalogo-filtros.abertos { display: block; }
    .catalogo-filtros-toggle { display: flex; }
    /* Conteúdo sempre ocupa a largura total */
    .catalogo-conteudo { width: 100%; }
}
</style>

<div class="catalogo-container">
    <?= render_breadcrumbs([['nome' => 'Portfólio']]) ?>

    <div style="text-align:center; margin-bottom:30px;">
        <h1 style="font-family:'Playfair Display',serif; color:#2d3436; font-size:36px; margin-bottom:8px;">
            O Nosso Portfólio
        </h1>
        <p style="color:#666; max-width:600px; margin:0 auto 20px;">
            Veja alguns dos bordados que já fizemos. Cada peça é única e feita à mão.
            Encontrou algo que gosta? Clique em "Quero algo parecido" e fazemos uma personalização para si.
        </p>
        <a href="pedir-orcamento.php" style="display:inline-block; background:linear-gradient(135deg,#d66d7f,#bf5b6d); color:#fff; padding:14px 32px; border-radius:999px; text-decoration:none; font-weight:600; box-shadow:0 8px 20px rgba(201,95,122,0.20);">
            ✨ Pedir Orçamento Personalizado
        </a>
    </div>
    <div class="catalogo-layout">

        <!-- Botão que mostra/oculta os filtros em telemóvel -->
        <button class="catalogo-filtros-toggle" id="btnFiltros"
                aria-controls="sidebarFiltros" aria-expanded="false">
            <i class="fas fa-filter"></i> Filtros e Pesquisa
            <span class="toggle-seta" id="filtrosSeta">▼</span>
        </button>

        <aside class="catalogo-filtros" id="sidebarFiltros">
            <form method="get" data-no-loading>
                <h3>Filtros</h3>
                <label>Pesquisar</label>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="O que procura?">

                <label>Categoria</label>
                <select name="categoria">
                    <option value="0">Todas as Categorias</option>
                    <?php foreach ($todasCategorias as $c):
                        // Mostra o número de produtos entre parênteses (ex: "Toalhas (5)")
                        $count = $contagemPorCategoria[(int)$c['id']] ?? 0;
                    ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $categoriaFiltro == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?> (<?= $count ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Ordenar por</label>
                <select name="ordem">
                    <option value="padrao"  <?= $ordem === 'padrao'  ? 'selected' : '' ?>>Por nome</option>
                    <option value="recente" <?= $ordem === 'recente' ? 'selected' : '' ?>>Adicionados recentemente</option>
                </select>

                <button type="submit" class="btn-filtrar">Filtrar</button>
                <a href="catalogo.php" style="display:block; text-align:center; margin-top:15px; color:#999; text-decoration:none; font-size:13px;">Limpar Filtros</a>
            </form>
        </aside>

        <div class="catalogo-conteudo">
            <?php
            // ---- Indicador de filtros activos ----
            // Mostra um "pill" rosa quando há pesquisa ou categoria selecionada,
            // com link para limpar os filtros.
            $filtrosAtivos = ($q !== '') || ($categoriaFiltro > 0);
            if ($filtrosAtivos):
                $descFiltro = [];
                if ($q !== '') $descFiltro[] = 'Pesquisa: <strong>' . htmlspecialchars($q) . '</strong>';
                if ($categoriaFiltro > 0) {
                    // Encontra o nome da categoria selecionada
                    foreach ($todasCategorias as $c) {
                        if ((int)$c['id'] === $categoriaFiltro) {
                            $descFiltro[] = 'Categoria: <strong>' . htmlspecialchars($c['nome']) . '</strong>';
                            break;
                        }
                    }
                }
            ?>
            <div style="background:#fff0f3; border:1px solid #f0c0cc; border-radius:10px;
                        padding:10px 16px; margin-bottom:18px; display:flex;
                        justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <span style="color:#d66d7f; font-size:14px;">
                    <i class="fas fa-filter"></i> A filtrar por: <?= implode(' &bull; ', $descFiltro) ?>
                </span>
                <a href="catalogo.php" style="color:#d66d7f; font-size:13px; font-weight:600;
                   text-decoration:none; white-space:nowrap;">
                    <i class="fas fa-times"></i> Limpar filtros
                </a>
            </div>
            <?php endif; ?>

            <?php
            $algoEncontrado = false;

            foreach ($todasCategorias as $cat):
                if ($categoriaFiltro > 0 && $categoriaFiltro != $cat['id']) {
                    continue;
                }

                // SQL com alias 'p' para usar com o $orderBy da lista branca
                $sql = "SELECT p.* FROM produto p
                        WHERE p.categoria_id = :categoria_id
                          AND p.visivel_catalogo = 1
                          AND (p.stock IS NULL OR p.stock > 0)";
                $params = [':categoria_id' => (int)$cat['id']];

                if ($q !== '') {
                    $sql .= " AND (p.nome LIKE :q OR p.descricao LIKE :q2)";
                    $params[':q'] = "%$q%";
                    $params[':q2'] = "%$q%";
                }

                // Aplica a ordenação escolhida (lista branca - seguro)
                $sql .= " ORDER BY " . $orderBy;

                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($produtos)):
                    $algoEncontrado = true;
                    $nItens = count($produtos);
                    echo "<h2 class='categoria-titulo'>" . htmlspecialchars($cat['nome'])
                       . " <span class='conta'>" . $nItens . ($nItens === 1 ? ' peça' : ' peças') . "</span></h2>";
                    echo "<div class='produtos-grid'>";

                    foreach ($produtos as $p):
                        $listaImagens = get_todas_imagens_produto($conn, $p, $temMime, $temFicheiroNaGaleria);
                        $jsonImagens = htmlspecialchars(json_encode($listaImagens), ENT_QUOTES, 'UTF-8');
            ?>
                        <div class="produto-card">
                            <!-- A classe "skeleton" mostra um efeito de carregamento (pulse)
                                 até a imagem terminar de carregar (onload remove-a). -->
                            <div class="produto-img-box skeleton" onclick='abrirZoom(<?= $jsonImagens ?>, 0)'>
                                <!-- loading="lazy": o browser só carrega a imagem quando ela aparece no ecrã -->
                                <img src="<?= htmlspecialchars($listaImagens[0]) ?>" class="produto-img" alt="<?= htmlspecialchars($p['nome']) ?>" loading="lazy" decoding="async"
                                     onload="this.parentElement.classList.remove('skeleton')"
                                     onerror="this.parentElement.classList.remove('skeleton')">
                            </div>

                            <div class="produto-info">
                                <a href="produto.php?id=<?= (int)$p['id'] ?>" class="produto-nome">
                                    <?= htmlspecialchars($p['nome']) ?>
                                </a>

                                <?php
                                // Média de estrelas + nº avaliações deste produto
                                $estats = calcular_media_estrelas($conn, (int)$p['id']);
                                if ($estats['total'] > 0):
                                ?>
                                    <div style="margin-bottom: 8px;">
                                        <?= render_estrelas($estats['media'], true) ?>
                                        <small style="color:#999;">(<?= $estats['total'] ?>)</small>
                                    </div>
                                <?php endif; ?>

                                <div class="produto-desc">
                                    <?= htmlspecialchars($p['descricao'] ?? '') ?>
                                </div>

                                <div class="produto-footer">
                                    <a href="pedir-orcamento.php?inspiracao=<?= (int)$p['id'] ?>" class="btn-orcamento">
                                        ✨ Quero algo parecido
                                    </a>
                                </div>
                            </div>
                        </div>
            <?php
                    endforeach;

                    echo "</div>";
                endif;
            endforeach;

            if (!$algoEncontrado):
                // Estado vazio - aparece quando nenhum produto corresponde ao filtro
            ?>
            <div style="text-align:center; padding:60px 20px;">
                <div style="font-size:3rem; color:#e8a4b0; margin-bottom:16px;">
                    <i class="fas fa-search"></i>
                </div>
                <h3 style="color:#444; margin-bottom:8px;">Nenhum resultado encontrado</h3>
                <p style="color:#888; margin-bottom:24px; max-width:360px; margin-left:auto; margin-right:auto;">
                    Não encontrámos produtos com esses critérios. Tente limpar os filtros ou
                    peça-nos algo feito à medida.
                </p>
                <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                    <a href="catalogo.php"
                       style="background:#f5f5f5; color:#555; padding:12px 24px;
                              border-radius:999px; text-decoration:none; font-weight:600;">
                        <i class="fas fa-times"></i> Limpar filtros
                    </a>
                    <a href="pedir-orcamento.php"
                       style="background:linear-gradient(135deg, #d66d7f, #bf5b6d); color:white;
                              padding:12px 24px; border-radius:999px; text-decoration:none; font-weight:600;">
                        <i class="fas fa-paint-brush"></i> Pedir personalizado
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================================
     LIGHTBOX - Estrutura redesenhada
     - Fundo faz fechar ao clicar (JS via event delegation)
     - Botões com Font Awesome em vez de caracteres Unicode
     - Tira de miniaturas preenchida dinamicamente por JS
     ============================================================ -->
<div id="modalZoom" class="modal-zoom">

    <!-- Contador "2 / 3" fixo no topo ao centro -->
    <div id="modalContador" class="modal-contador"></div>

    <!-- Botão fechar - círculo glass top-right -->
    <button class="modal-fechar" id="btnModalFechar" aria-label="Fechar lightbox">
        <i class="fas fa-times"></i>
    </button>

    <!-- Imagem principal com fade entre fotos -->
    <img id="imgNoModal" class="modal-conteudo" alt="Zoom produto">

    <!-- Setas de navegação -->
    <button class="modal-nav modal-prev" id="btnModalPrev" aria-label="Foto anterior">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="modal-nav modal-next" id="btnModalNext" aria-label="Próxima foto">
        <i class="fas fa-chevron-right"></i>
    </button>

    <!-- Tira de miniaturas (preenchida pelo JS ao abrir) -->
    <div id="modalThumbs" class="modal-thumbs"></div>

</div>

<script>
// =============================================================================
// LIGHTBOX - Lógica do modal redesenhado
// =============================================================================

let imagensAtuais = [];   // array de caminhos das imagens do produto atual
let indiceAtual   = 0;    // índice da imagem visível no momento

// --- Referências aos elementos do DOM ---
const modal     = document.getElementById('modalZoom');
const imgPrinc  = document.getElementById('imgNoModal');
const contador  = document.getElementById('modalContador');
const thumbsDiv = document.getElementById('modalThumbs');
const btnPrev   = document.getElementById('btnModalPrev');
const btnNext   = document.getElementById('btnModalNext');
const btnFechar = document.getElementById('btnModalFechar');

// Abre o lightbox com as imagens do produto e mostra a de índice idx
function abrirZoom(imgs, idx) {
    imagensAtuais = imgs;
    indiceAtual   = idx;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden'; // bloqueia scroll da página

    // Mostra a imagem imediatamente (sem fade na abertura)
    imgPrinc.style.opacity = '1';
    imgPrinc.src = imagensAtuais[indiceAtual];

    construirThumbs();   // gera/atualiza a tira de miniaturas
    atualizarUI();       // sincroniza contador, nav, thumb ativa
}

// Atualiza o contador, setas e miniatura ativa sem trocar a imagem principal
function atualizarUI() {
    // Contador "1 / 3"
    contador.textContent = (indiceAtual + 1) + ' / ' + imagensAtuais.length;

    // Setas e tira de thumbs - só aparecem se houver mais de 1 foto
    const multi = imagensAtuais.length > 1;
    btnPrev.style.display  = multi ? 'flex' : 'none';
    btnNext.style.display  = multi ? 'flex' : 'none';
    thumbsDiv.style.display = multi ? 'flex' : 'none';

    // Realça a miniatura ativa
    document.querySelectorAll('.modal-thumb').forEach((el, i) => {
        el.classList.toggle('ativo', i === indiceAtual);
    });
}

// Troca para outra imagem com efeito de fade suave
function mudarImagem(dir) {
    indiceAtual = (indiceAtual + dir + imagensAtuais.length) % imagensAtuais.length;

    // Fade out → troca src → fade in
    imgPrinc.style.opacity = '0';
    setTimeout(() => {
        imgPrinc.src = imagensAtuais[indiceAtual];
        imgPrinc.style.opacity = '1';
        atualizarUI();
    }, 170); // duração igual à transition CSS (0.18s)
}

// Cria as miniaturas na tira inferior
function construirThumbs() {
    // Limpa filhos existentes via DOM (evita usar innerHTML)
    while (thumbsDiv.firstChild) thumbsDiv.removeChild(thumbsDiv.firstChild);

    imagensAtuais.forEach((src, idx) => {
        const btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'modal-thumb' + (idx === indiceAtual ? ' ativo' : '');
        btn.setAttribute('aria-label', 'Ver foto ' + (idx + 1));

        // Thumbnail com índice na closure
        btn.addEventListener('click', (function(i) {
            return function(e) {
                e.stopPropagation();
                indiceAtual = i;
                imgPrinc.style.opacity = '0';
                setTimeout(() => {
                    imgPrinc.src = imagensAtuais[indiceAtual];
                    imgPrinc.style.opacity = '1';
                    atualizarUI();
                }, 170);
            };
        })(idx));

        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        btn.appendChild(img);
        thumbsDiv.appendChild(btn);
    });
}

// Fecha o lightbox
function fecharModal() {
    modal.style.display = 'none';
    document.body.style.overflow = ''; // repõe o scroll
}

// --- Event listeners ---

// Clique no fundo escuro fecha o modal
modal.addEventListener('click', function(e) {
    if (e.target === modal) fecharModal();
});

// Botão X fecha o modal
btnFechar.addEventListener('click', fecharModal);

// Setas de navegação
btnPrev.addEventListener('click', function(e) { e.stopPropagation(); mudarImagem(-1); });
btnNext.addEventListener('click', function(e) { e.stopPropagation(); mudarImagem(1); });

// Teclado: Esc fecha, ← / → navega
document.addEventListener('keydown', function(e) {
    if (modal.style.display !== 'flex') return;
    if (e.key === 'Escape')      fecharModal();
    if (e.key === 'ArrowLeft')   mudarImagem(-1);
    if (e.key === 'ArrowRight')  mudarImagem(1);
});

// === Toggle da barra de filtros em mobile ===
(function () {
    const btn = document.getElementById('btnFiltros');
    const sidebar = document.getElementById('sidebarFiltros');
    if (!btn || !sidebar) return;
    btn.addEventListener('click', function () {
        const aberto = sidebar.classList.toggle('abertos');
        document.getElementById('filtrosSeta').textContent = aberto ? '▲' : '▼';
        btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });
})();

// === Swipe (deslizar) no telemóvel para mudar de imagem ===
(function () {
    var modal = document.getElementById("modalZoom");
    if (!modal) return;
    var xInicio = null;
    modal.addEventListener('touchstart', function (e) {
        xInicio = e.changedTouches[0].clientX;
    }, { passive: true });
    modal.addEventListener('touchend', function (e) {
        if (xInicio === null || imagensAtuais.length < 2) return;
        var dx = e.changedTouches[0].clientX - xInicio;
        if (Math.abs(dx) > 50) {                 // só conta como swipe se mover > 50px
            mudarImagem(dx < 0 ? 1 : -1);        // esquerda = próxima, direita = anterior
        }
        xInicio = null;
    }, { passive: true });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>