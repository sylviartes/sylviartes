<?php
/**
 * =============================================================================
 *  PRODUTO — Página de detalhe individual
 * =============================================================================
 *
 *  URL: produto.php?id=N
 *
 *  Mostra:
 *    - Galeria de imagens (thumbnails + zoom no clique)
 *    - Nome, categoria, descrição, preço, stock
 *    - Botão "Adicionar ao Carrinho"
 *    - Avaliações de clientes (estrelas + comentários aprovados)
 *    - Form para deixar avaliação (se cliente logado tiver comprado o produto)
 * =============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/avaliacoes.php';
require_once __DIR__ . '/../src/breadcrumbs.php';
require_once __DIR__ . '/../config/csrf.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// =============================================================================
// PROCESSAR SUBMISSÃO DE AVALIAÇÃO (antes do header.php para poder fazer redirect)
// =============================================================================
require_once __DIR__ . '/../config/session.php';
$msgAvaliacao = "";
$tipoMsgAvaliacao = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['accao']) && $_POST['accao'] === 'avaliar'
    && isset($_SESSION['cliente_id'])
) {
    csrf_validate();
    $clienteId = (int)$_SESSION['cliente_id'];
    $estrelas = (int)($_POST['estrelas'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');

    if ($estrelas < 1 || $estrelas > 5) {
        $msgAvaliacao = "Selecione entre 1 e 5 estrelas.";
        $tipoMsgAvaliacao = "erro";
    } elseif (!cliente_pode_avaliar($conn, $clienteId, $id)) {
        $msgAvaliacao = "Não pode avaliar este produto (já avaliou ou ainda não o comprou).";
        $tipoMsgAvaliacao = "erro";
    } else {
        // Insere a avaliação. aprovado = 0 → fica à espera de moderação admin.
        $stmt = $conn->prepare("
            INSERT INTO avaliacao (utilizador_id, produto_id, estrelas, comentario, aprovado)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$clienteId, $id, $estrelas, $comentario]);

        // Redirect para evitar reenvio se o utilizador atualizar a página
        header("Location: produto.php?id=" . $id . "&aval_ok=1");
        exit;
    }
}

if (isset($_GET['aval_ok'])) {
    $msgAvaliacao = "Obrigado! A sua avaliação será publicada após aprovação.";
    $tipoMsgAvaliacao = "ok";
}

// =============================================================================
// CARREGAR PRODUTO (antes do header para poder definir o título da página)
// =============================================================================
$stmt = $conn->prepare("SELECT * FROM produto WHERE id = ? AND visivel_catalogo = 1");
$stmt->execute([$id]);
$p = $stmt->fetch();

// Define o título da aba do browser com o nome do produto
$pageTitle       = $p ? htmlspecialchars($p['nome']) : 'Produto';
$pageDescription = $p ? 'Bordado artesanal ' . htmlspecialchars($p['nome']) . ' — encomende o seu em SylviArtes.' : '';

require_once __DIR__ . '/header.php';

// Produto inexistente / oculto → mostra erro amigável
if (!$p) {
?>
<div class="container py-5 text-center">
    <h2 style="color:#d66d7f;">Produto não encontrado</h2>
    <p class="text-muted">Pode ter sido removido ou estar temporariamente indisponível.</p>
    <a href="catalogo.php" class="btn btn-primary mt-3" style="background:#d66d7f; border:none;">← Voltar ao Catálogo</a>
</div>
<?php
require_once __DIR__ . '/footer.php';
exit;
}

// === Categoria do produto ===
// Busca nome e preco_referencia (preço indicativo para mostrar "A partir de €X")
$catNome = '';
$catPrecoRef = null;
$stmt = $conn->prepare("SELECT nome, preco_referencia FROM categoria WHERE id = ? LIMIT 1");
$stmt->execute([$p['categoria_id']]);
$cat = $stmt->fetch();
if ($cat) {
    $catNome     = $cat['nome'];
    // preco_referencia pode não existir se a coluna ainda não foi criada — verificamos
    $catPrecoRef = isset($cat['preco_referencia']) && $cat['preco_referencia'] > 0
                   ? (float)$cat['preco_referencia']
                   : null;
}

/**
 * Helper local: obtém todas as imagens do produto.
 * Procura primeiro na galeria (produto_imagem), depois fallback à imagem principal,
 * e finalmente ao logo se não houver nenhuma.
 */
function obter_imagens_produto_loja($conn, $produto_id, $p) {
    $imagens = [];

    $stmt = $conn->prepare("SELECT * FROM produto_imagem WHERE produto_id = ? ORDER BY ordem ASC");
    $stmt->execute([$produto_id]);

    while ($row = $stmt->fetch()) {
        if (!empty($row['imagem'])) {
            // Imagens estão guardadas como nome de ficheiro em /imagens/produtos/
            $caminho = __DIR__ . '/imagens/produtos/' . $row['imagem'];
            if (file_exists($caminho)) {
                $imagens[] = 'imagens/produtos/' . $row['imagem'];
            }
            // O ramo de fallback BLOB foi removido — imagens são sempre nomes de ficheiro.
        }
    }

    // Fallback para campo legado "imagem" na tabela produto
    if (empty($imagens) && !empty($p['imagem'])) {
        $caminho = __DIR__ . '/imagens/produtos/' . $p['imagem'];
        if (file_exists($caminho)) {
            $imagens[] = 'imagens/produtos/' . $p['imagem'];
        }
    }

    // Sem imagens? Usa o logo
    if (empty($imagens)) {
        $imagens[] = 'imagens/logo_sylviartes.png';
    }

    return $imagens;
}

$imagens_produto = obter_imagens_produto_loja($conn, $id, $p);
$imagem_principal = $imagens_produto[0];

// === Avaliações ===
$avaliacoes = obter_avaliacoes_produto($conn, $id);
$mediaEstrelas = calcular_media_estrelas($conn, $id);

// Pode mostrar form de avaliação?
$podeAvaliar = isset($_SESSION['cliente_id'])
    && cliente_pode_avaliar($conn, (int)$_SESSION['cliente_id'], $id);
?>

<style>
.produto-detalhe {
    max-width: 1200px;
    margin: 30px auto;
    padding: 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}
.produto-galeria { display: flex; flex-direction: column; gap: 12px; }
.galeria-principal {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    cursor: zoom-in;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.produto-detalhe-img { width: 100%; height: 480px; object-fit: cover; display: block; }
.galeria-thumbnails { display: flex; gap: 8px; flex-wrap: wrap; }
.galeria-thumb {
    width: 70px; height: 70px;
    border-radius: 10px; overflow: hidden;
    cursor: pointer; border: 2px solid transparent;
    transition: all 0.2s;
}
.galeria-thumb img { width: 100%; height: 100%; object-fit: cover; }
.galeria-thumb.active, .galeria-thumb:hover { border-color: #d66d7f; }

.produto-detalhe-body { padding: 20px 0; }
.produto-categoria {
    display: inline-block; background: #fff0f3; color: #d66d7f;
    padding: 4px 12px; border-radius: 999px; font-size: 12px;
    font-weight: 600; text-transform: uppercase; letter-spacing: 1px;
}
.produto-nome {
    font-family: 'Playfair Display', serif; font-size: 36px;
    margin: 12px 0; color: #2d3436;
}
.produto-desc { color: #636e72; line-height: 1.7; margin-bottom: 24px; }
.produto-preco { font-size: 32px; font-weight: 700; color: #d66d7f; }
.produto-stock {
    margin-left: 16px; color: #28a745; font-weight: 600; font-size: 14px;
}
.produto-btn {
    display: inline-block; margin-top: 24px;
    padding: 14px 32px;
    background: linear-gradient(135deg, #d66d7f, #bf5b6d);
    color: #fff; border: none; border-radius: 999px;
    font-weight: 600; font-size: 16px; text-decoration: none;
    cursor: pointer; transition: 0.25s;
}
.produto-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(201,95,122,0.28); }

.zoom-modal {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.92);
    z-index: 9999; justify-content: center; align-items: center; padding: 20px;
}
.zoom-conteudo { max-width: 90%; max-height: 90vh; object-fit: contain; }

/* Avaliações */
.avaliacoes-secao {
    max-width: 1200px; margin: 50px auto; padding: 30px;
    background: #fff; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
.avaliacoes-titulo {
    font-family: 'Playfair Display', serif; font-size: 28px;
    color: #2d3436; margin-bottom: 8px;
}
.avaliacoes-resumo { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; }
.avaliacoes-media { font-size: 36px; font-weight: 700; color: #2d3436; }
.avaliacoes-vazio { color: #999; font-style: italic; padding: 20px 0; }

.avaliacao-item {
    border-top: 1px solid #f3e7eb; padding: 18px 0;
}
.avaliacao-item:first-of-type { border-top: none; }
.avaliacao-cabecalho { display: flex; justify-content: space-between; margin-bottom: 8px; }
.avaliacao-autor { font-weight: 600; color: #2d3436; }
.avaliacao-data { color: #999; font-size: 13px; }
.avaliacao-comentario { color: #636e72; line-height: 1.6; margin-top: 6px; }

.aval-form {
    background: #fff8fa; padding: 24px; border-radius: 14px;
    border: 1px dashed #e8a4b0; margin-top: 24px;
}
.aval-estrelas {
    display: inline-flex; gap: 6px; font-size: 28px; cursor: pointer;
    margin: 8px 0 16px; color: #ddd;
}
.aval-estrelas i { transition: color 0.15s; }
.aval-estrelas i.ativa { color: #f5b301; }
.aval-form textarea {
    width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;
    font-family: inherit; min-height: 90px; resize: vertical;
}
.aval-form button {
    margin-top: 12px; padding: 10px 24px;
    background: linear-gradient(135deg, #d66d7f, #bf5b6d); color: #fff;
    border: none; border-radius: 999px; font-weight: 600; cursor: pointer;
}

@media (max-width: 768px) {
    .produto-detalhe { grid-template-columns: 1fr; gap: 20px; }
    .produto-detalhe-img { height: 320px; }
}
</style>

<div style="max-width:1200px; margin:0 auto; padding:0 20px;">
    <?= render_breadcrumbs([
        ['nome' => 'Catálogo', 'url' => 'catalogo.php'],
        ['nome' => $p['nome']]
    ]) ?>
</div>

<!-- ============================================================ -->
<!-- GALERIA DE IMAGENS + INFO DO PRODUTO                          -->
<!-- ============================================================ -->
<div class="produto-detalhe">
    <div class="produto-galeria">
        <div class="galeria-principal" onclick="abrirZoomModal()">
            <img src="<?= htmlspecialchars($imagem_principal) ?>" id="imgPrincipal" class="produto-detalhe-img" alt="<?= htmlspecialchars($p['nome']) ?>" decoding="async">
        </div>

        <?php if (count($imagens_produto) > 1): ?>
        <div class="galeria-thumbnails">
            <?php foreach ($imagens_produto as $index => $img): ?>
                <div class="galeria-thumb <?= $index === 0 ? 'active' : '' ?>"
                     onclick="trocarImagem('<?= htmlspecialchars($img) ?>', this)">
                    <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy" decoding="async">
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="produto-detalhe-body">
        <?php if ($catNome): ?>
            <span class="produto-categoria"><?= htmlspecialchars($catNome) ?></span>
        <?php endif; ?>

        <h1 class="produto-nome"><?= htmlspecialchars($p['nome']) ?></h1>

        <?php if ($mediaEstrelas['total'] > 0): ?>
            <div style="margin-bottom: 14px;">
                <?= render_estrelas($mediaEstrelas['media']) ?>
                <span style="color:#636e72; margin-left:6px;">
                    <?= $mediaEstrelas['media'] ?> · <?= $mediaEstrelas['total'] ?> avaliação<?= $mediaEstrelas['total'] > 1 ? 'ões' : '' ?>
                </span>
            </div>
        <?php endif; ?>

        <p class="produto-desc"><?= nl2br(htmlspecialchars($p['descricao'])) ?></p>

        <!-- Bloco de características rápidas do produto -->
        <div style="background:#fff8fa; border:1px solid #f4cdd5; border-radius:14px;
                    padding:16px 18px; margin: 16px 0;">

            <!-- Preço de referência — só aparece se a categoria tiver este campo preenchido -->
            <?php if ($catPrecoRef): ?>
            <div style="margin-bottom:10px;">
                <span style="font-size:0.8rem; color:#999; text-transform:uppercase; letter-spacing:0.5px;">
                    A partir de
                </span><br>
                <span style="font-size:1.6rem; font-weight:700; color:#d66d7f;">
                    <?= number_format($catPrecoRef, 2, ',', '.') ?> €
                </span>
                <span style="font-size:0.85rem; color:#888;"> (preço indicativo — orçamento gratuito)</span>
            </div>
            <?php endif; ?>

            <!-- Características fixas desta categoria de produto -->
            <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:6px;">
                <li style="color:#555; font-size:14px;">
                    <i class="fas fa-hand-holding-heart" style="color:#d66d7f; width:18px;"></i>
                    Feito completamente à mão
                </li>
                <li style="color:#555; font-size:14px;">
                    <i class="fas fa-palette" style="color:#d66d7f; width:18px;"></i>
                    Personalizável: nomes, cores, tamanho e tecido
                </li>
                <li style="color:#555; font-size:14px;">
                    <i class="fas fa-shipping-fast" style="color:#d66d7f; width:18px;"></i>
                    Envio para todo Portugal
                </li>
            </ul>
        </div>

        <a href="pedir-orcamento.php?inspiracao=<?= $id ?>" class="produto-btn" style="text-decoration:none; display:inline-block;">
            ✨ Quero algo parecido — Pedir Orçamento
        </a>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECÇÃO DE AVALIAÇÕES                                          -->
<!-- ============================================================ -->
<div class="avaliacoes-secao" id="avaliar">
    <h2 class="avaliacoes-titulo">Avaliações de Clientes</h2>

    <?php if ($mediaEstrelas['total'] > 0): ?>
        <div class="avaliacoes-resumo">
            <span class="avaliacoes-media"><?= $mediaEstrelas['media'] ?></span>
            <div>
                <?= render_estrelas($mediaEstrelas['media']) ?>
                <div style="color:#636e72; font-size:13px;">
                    Baseado em <?= $mediaEstrelas['total'] ?> avaliação<?= $mediaEstrelas['total'] > 1 ? 'ões' : '' ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($msgAvaliacao)): ?>
        <div style="padding:12px 16px; border-radius:10px; margin-bottom:18px;
                    background:<?= $tipoMsgAvaliacao === 'ok' ? '#edf9f0' : '#fdeced' ?>;
                    color:<?= $tipoMsgAvaliacao === 'ok' ? '#1f6b35' : '#8b1e2d' ?>;">
            <?= htmlspecialchars($msgAvaliacao) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($avaliacoes)): ?>
        <p class="avaliacoes-vazio">Ainda não há avaliações para este produto. Seja o primeiro a avaliar!</p>
    <?php else: ?>
        <?php foreach ($avaliacoes as $a): ?>
            <div class="avaliacao-item">
                <div class="avaliacao-cabecalho">
                    <div>
                        <div class="avaliacao-autor"><?= htmlspecialchars($a['nome']) ?></div>
                        <?= render_estrelas((float)$a['estrelas'], true) ?>
                    </div>
                    <div class="avaliacao-data"><?= date('d/m/Y', strtotime($a['data'])) ?></div>
                </div>
                <?php if (!empty($a['comentario'])): ?>
                    <div class="avaliacao-comentario"><?= nl2br(htmlspecialchars($a['comentario'])) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Form de submissão (só aparece se o cliente comprou e ainda não avaliou) -->
    <?php if ($podeAvaliar): ?>
        <div class="aval-form">
            <h4 style="margin:0 0 6px; color:#d66d7f;">Deixe a sua avaliação</h4>
            <p style="color:#636e72; font-size:14px; margin:0 0 12px;">A sua opinião ajuda outros clientes!</p>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="accao" value="avaliar">
                <input type="hidden" name="estrelas" id="aval-estrelas-input" value="0">

                <label>Estrelas:</label>
                <div class="aval-estrelas" id="aval-estrelas">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="far fa-star" data-valor="<?= $i ?>" onclick="selecionarEstrelas(<?= $i ?>)"></i>
                    <?php endfor; ?>
                </div>

                <label>Comentário (opcional):</label>
                <textarea name="comentario" placeholder="Conte-nos a sua experiência com este produto..."></textarea>

                <button type="submit">Enviar avaliação</button>
            </form>
        </div>
    <?php elseif (!isset($_SESSION['cliente_id'])): ?>
        <p style="color:#636e72; margin-top:16px; font-size:14px;">
            <a href="cliente/login.php" style="color:#d66d7f;">Entre na sua conta</a>
            para deixar uma avaliação (apenas clientes com encomendas concluídas podem avaliar).
        </p>
    <?php elseif (avaliacoes_disponiveis($conn) && !cliente_tem_pedido_avaliavel($conn, (int)$_SESSION['cliente_id'])): ?>
        <p style="color:#636e72; margin-top:16px; font-size:14px;">
            Quando receber a sua primeira encomenda, poderá deixar uma avaliação aqui.
            <a href="cliente/encomendas.php" style="color:#d66d7f;">Ver as minhas encomendas →</a>
        </p>
    <?php endif; ?>
</div>

<!-- Modal de zoom da imagem principal -->
<div id="zoomModal" class="zoom-modal" onclick="this.style.display='none'">
    <img id="imgZoom" class="zoom-conteudo" alt="">
</div>

<script>
// Trocar imagem da galeria ao clicar num thumbnail
function trocarImagem(src, el) {
    document.getElementById('imgPrincipal').src = src;
    document.querySelectorAll('.galeria-thumb').forEach(e => e.classList.remove('active'));
    el.classList.add('active');
}

// Abre o modal de zoom da imagem principal
function abrirZoomModal() {
    document.getElementById('zoomModal').style.display = 'flex';
    document.getElementById('imgZoom').src = document.getElementById('imgPrincipal').src;
}

// Selecionar estrelas no form de avaliação
function selecionarEstrelas(valor) {
    document.getElementById('aval-estrelas-input').value = valor;
    document.querySelectorAll('#aval-estrelas i').forEach((el, idx) => {
        if (idx < valor) {
            el.classList.remove('far');
            el.classList.add('fas', 'ativa');
        } else {
            el.classList.remove('fas', 'ativa');
            el.classList.add('far');
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
