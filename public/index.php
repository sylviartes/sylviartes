<?php
/**
 * =============================================================================
 *  HOMEPAGE — SylviArtes
 * =============================================================================
 *
 *  Página inicial pública. Estrutura:
 *    1. Hero (banner principal)
 *    2. Estatísticas (social proof)
 *    3. O que criamos (3 cards)
 *    4. Como funciona (4 passos)
 *    5. Trabalhos em destaque (queries reais)
 *    6. Depoimentos (avaliações reais aprovadas)
 *    7. Porquê a SylviArtes
 *    8. CTA final
 * =============================================================================
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/avaliacoes.php';
// Título e descrição para esta página (usados no <head> do header.php)
$pageTitle       = 'Costura Criativa & Bordados Personalizados';
$pageDescription = 'Bordados artesanais feitos à mão em Portugal. Encomendar é fácil — peça o seu orçamento grátis.';
require_once __DIR__ . '/header.php';

// === Produtos em destaque: 4 produtos visíveis ===
$stmtDestaques = $conn->query("
    SELECT p.id, p.nome, p.preco_base,
           (SELECT imagem FROM produto_imagem WHERE produto_id = p.id ORDER BY ordem ASC LIMIT 1) AS imagem
    FROM produto p
    WHERE p.visivel_catalogo = 1 AND (p.stock IS NULL OR p.stock > 0)
    ORDER BY p.id DESC
    LIMIT 4
");
$produtosDestaque = $stmtDestaques->fetchAll(PDO::FETCH_ASSOC);

// === Depoimentos: 3 melhores avaliações aprovadas com comentário ===
$depoimentos = [];
$avalMedia = 0;
$avalTotal = 0;
if (avaliacoes_disponiveis($conn)) {
    $stmtDepoimentos = $conn->query("
        SELECT a.estrelas, a.comentario, a.data, u.nome AS cliente,
               p.nome AS produto, p.id AS produto_id
        FROM avaliacao a
        JOIN utilizador u ON u.id = a.utilizador_id
        LEFT JOIN produto p ON p.id = a.produto_id
        WHERE a.aprovado = 1 AND a.comentario IS NOT NULL AND a.comentario != ''
        ORDER BY a.estrelas DESC, a.data DESC
        LIMIT 3
    ");
    $depoimentos = $stmtDepoimentos->fetchAll(PDO::FETCH_ASSOC);

    try {
        $r = $conn->query("SELECT AVG(estrelas) m, COUNT(*) t FROM avaliacao WHERE aprovado=1")->fetch(PDO::FETCH_ASSOC);
        $avalMedia = $r['m'] !== null ? round((float)$r['m'], 1) : 0;
        $avalTotal = (int)$r['t'];
    } catch (Exception $e) { /* ignora */ }
}

// === Estatísticas para a faixa social proof ===
$pecasEntregues = 0;
try {
    $pecasEntregues = (int)$conn->query("SELECT COUNT(*) FROM pedido WHERE estado IN ('entregue','concluido')")->fetchColumn();
} catch (Exception $e) { /* ignora */ }
$totalCategorias = (int)$conn->query("SELECT COUNT(*) FROM categoria")->fetchColumn();
?>

<style>
:root {
    /* Paleta rosa — mesmos valores usados em header.php e admin_style.css */
    --rosa: #d66d7f;
    --rosa-soft: #bf5b6d;
    --rosa-tinte: rgba(214,109,127,0.08);
    --rosa-borda: rgba(214,109,127,0.18);
    --neutro-fundo: #fafaf8;
    --neutro-card: #ffffff;
    --neutro-borda: #ececea;
    --escuro: #1f2937;
    --texto-titulo: #1f2937;
    --texto-corpo: #4b5563;
    --texto-suave: #6b7280;
}

main { padding: 0 !important; max-width: 100% !important; background: var(--neutro-fundo); }

.home-section { max-width: 1200px; margin: 0 auto; padding: 90px 24px; }
.home-section.compact { padding: 60px 24px; }

/* Linha decorativa subtil para títulos */
.linha-rosa {
    width: 40px; height: 2px; background: var(--rosa);
    margin: 0 auto 18px; border-radius: 2px;
}

/* ===== HERO ===== */
.home-hero {
    background: #fff;
    padding: 90px 24px 100px;
    position: relative;
    overflow: hidden;
    border-bottom: 1px solid var(--neutro-borda);
}
.home-hero::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(194,93,114,0.05) 0%, transparent 70%);
    pointer-events: none;
}
.home-hero-grid {
    max-width: 1200px; margin: 0 auto;
    display: grid; grid-template-columns: 1.1fr 1fr; gap: 60px; align-items: center;
}
.home-hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--rosa-tinte);
    color: var(--rosa);
    padding: 6px 14px; border-radius: 4px;
    font-size: 11.5px; font-weight: 700; letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 24px;
}
.home-hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 60px; line-height: 1.05; font-weight: 700;
    color: var(--texto-titulo);
    margin: 0 0 22px;
    letter-spacing: -1px;
}
.home-hero h1 em { color: var(--rosa); font-style: italic; }
.home-hero p {
    font-size: 18px; line-height: 1.65;
    color: var(--texto-corpo);
    margin: 0 0 36px; max-width: 520px;
}
.home-hero-ctas { display: flex; gap: 14px; flex-wrap: wrap; align-items: center; }

.btn-rosa {
    display: inline-flex; align-items: center; gap: 10px;
    background: var(--rosa);
    color: #fff !important;
    padding: 15px 30px; border-radius: 6px;
    font-weight: 600; font-size: 15px; text-decoration: none;
    transition: all 0.2s;
    border: 1px solid var(--rosa);
}
.btn-rosa:hover { background: #ad4d61; border-color: #ad4d61; transform: translateY(-1px); }

.btn-ghost {
    display: inline-flex; align-items: center; gap: 10px;
    background: transparent; color: var(--escuro) !important;
    padding: 14px 28px; border-radius: 6px;
    font-weight: 600; font-size: 15px; text-decoration: none;
    border: 1px solid var(--neutro-borda); transition: all 0.2s;
}
.btn-ghost:hover { border-color: var(--escuro); background: #fff; }

.home-hero-img { position: relative; }
.home-hero-img img {
    width: 100%; height: 500px; object-fit: cover;
    border-radius: 4px;
    display: block;
    box-shadow: 0 25px 50px -20px rgba(0,0,0,0.18);
}
.home-hero-img::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 4px;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.10);
    pointer-events: none;
}

/* ===== ESTATÍSTICAS ===== */
.home-stats {
    background: #fff;
    border-bottom: 1px solid var(--neutro-borda);
    padding: 50px 24px; text-align: center;
}
.home-stats-grid {
    max-width: 1100px; margin: 0 auto;
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px;
}
.home-stat { position: relative; }
.home-stat:not(:last-child)::after {
    content: ''; position: absolute; right: -12px; top: 20%; bottom: 20%;
    width: 1px; background: var(--neutro-borda);
}
.home-stat .num {
    font-family: 'Playfair Display', serif;
    font-size: 42px; font-weight: 700; line-height: 1;
    color: var(--rosa);
}
.home-stat .label {
    font-size: 12px;
    color: var(--texto-suave);
    text-transform: uppercase; letter-spacing: 1.5px;
    margin-top: 10px;
    font-weight: 500;
}

/* ===== CABEÇALHOS DE SECÇÃO ===== */
.home-titulo { text-align: center; margin-bottom: 60px; }
.home-titulo .eyebrow {
    display: block;
    font-size: 12px; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--rosa);
    margin-bottom: 14px;
}
.home-titulo h2 {
    font-family: 'Playfair Display', serif;
    font-size: 42px; font-weight: 600;
    color: var(--texto-titulo); margin: 0 0 12px;
    letter-spacing: -0.5px;
}
.home-titulo p {
    color: var(--texto-suave); font-size: 16px;
    max-width: 600px; margin: 0 auto;
    line-height: 1.6;
}

/* ===== O QUE CRIAMOS ===== */
.servicos-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;
}
.servico-card {
    background: var(--neutro-card);
    border: 1px solid var(--neutro-borda);
    border-radius: 8px;
    padding: 36px 28px; text-align: center; transition: all 0.25s;
    position: relative;
    overflow: hidden;
}
.servico-card::before {
    content: ''; position: absolute; top: 0; left: 0;
    width: 100%; height: 2px; background: var(--rosa);
    transform: scaleX(0); transform-origin: left;
    transition: transform 0.3s;
}
.servico-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px -10px rgba(0,0,0,0.10);
}
.servico-card:hover::before { transform: scaleX(1); }
.servico-icone {
    display: inline-flex; align-items: center; justify-content: center;
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--rosa-tinte);
    color: var(--rosa); font-size: 22px; margin-bottom: 20px;
}
.servico-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 22px; margin: 0 0 10px; color: var(--texto-titulo);
    font-weight: 600;
}
.servico-card p { color: var(--texto-corpo); font-size: 14.5px; line-height: 1.65; margin: 0; }

/* ===== COMO FUNCIONA ===== */
.home-passos { background: #fff; border-top: 1px solid var(--neutro-borda); border-bottom: 1px solid var(--neutro-borda); }
.passos-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
    border: 1px solid var(--neutro-borda); border-radius: 8px;
    overflow: hidden;
}
.passo {
    background: #fff; padding: 36px 24px;
    text-align: center; position: relative;
    border-right: 1px solid var(--neutro-borda);
    transition: background 0.2s;
}
.passo:last-child { border-right: none; }
.passo:hover { background: #fafafa; }
.passo-num {
    display: inline-block;
    color: var(--rosa);
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 8px;
}
.passo i { color: var(--rosa); font-size: 24px; margin: 0 0 14px; display: block; }
.passo h4 {
    font-family: 'Playfair Display', serif;
    font-size: 18px; font-weight: 600; color: var(--texto-titulo);
    margin: 0 0 8px;
}
.passo p { font-size: 13.5px; color: var(--texto-corpo); line-height: 1.6; margin: 0; }

/* ===== TRABALHOS EM DESTAQUE ===== */
.destaques-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
.destaque-card {
    background: var(--neutro-card); border-radius: 8px; overflow: hidden;
    text-decoration: none; color: inherit;
    border: 1px solid var(--neutro-borda); transition: all 0.25s; display: block;
}
.destaque-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 36px -10px rgba(0,0,0,0.12);
    color: inherit;
}
.destaque-card .img { height: 240px; overflow: hidden; background: #f5f5f3; }
.destaque-card img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.destaque-card:hover img { transform: scale(1.04); }
.destaque-card .info { padding: 18px 20px; }
.destaque-card h5 {
    font-family: 'Playfair Display', serif;
    margin: 0 0 8px; color: var(--texto-titulo); font-size: 18px;
    font-weight: 600;
}
.destaque-card .ver {
    color: var(--rosa); font-size: 13px; font-weight: 600;
    text-transform: uppercase; letter-spacing: 1px;
}

/* ===== DEPOIMENTOS ===== */
.home-depoimentos { background: #fff; border-top: 1px solid var(--neutro-borda); border-bottom: 1px solid var(--neutro-borda); }
.depoimentos-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
.depoimento-card {
    background: var(--neutro-fundo);
    border: 1px solid var(--neutro-borda);
    border-radius: 8px; padding: 32px;
    position: relative;
    transition: all 0.25s;
}
.depoimento-card:hover {
    background: #fff;
    box-shadow: 0 16px 36px -10px rgba(0,0,0,0.08);
}
.depoimento-card::before {
    content: '"'; position: absolute; top: 12px; right: 24px;
    font-family: 'Playfair Display', serif; font-size: 80px;
    color: var(--rosa); opacity: 0.20; line-height: 1;
}
.depoimento-card .estrelas { margin-bottom: 16px; }
.depoimento-card p {
    color: #374151; line-height: 1.7; margin: 0 0 20px;
    font-size: 15px;
}
.depoimento-rodape { border-top: 1px solid var(--neutro-borda); padding-top: 14px; }
.depoimento-rodape strong { color: var(--texto-titulo); display: block; font-size: 14px; }
.depoimento-rodape .meta { font-size: 12.5px; color: var(--texto-suave); margin-top: 2px; }
.depoimento-rodape .meta a { color: var(--rosa); text-decoration: none; }

/* ===== PORQUÊ ===== */
.porque-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 32px; }
.porque-item { text-align: center; padding: 0 8px; }
.porque-item i {
    font-size: 22px; color: var(--rosa);
    background: var(--rosa-tinte);
    width: 52px; height: 52px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    margin-bottom: 16px;
}
.porque-item h5 {
    margin: 0 0 6px; color: var(--texto-titulo);
    font-size: 16px; font-weight: 600;
}
.porque-item p { margin: 0; color: var(--texto-corpo); font-size: 14px; line-height: 1.6; }

/* ===== CTA FINAL ===== */
.home-cta-final {
    background: var(--escuro);
    color: #fff; text-align: center; padding: 90px 24px;
    position: relative;
    overflow: hidden;
}
.home-cta-final::before {
    content: '';
    position: absolute;
    top: -50%; left: 50%; transform: translateX(-50%);
    width: 800px; height: 600px;
    background: radial-gradient(ellipse, rgba(194,93,114,0.20) 0%, transparent 60%);
    pointer-events: none;
}
.home-cta-final-inner { position: relative; z-index: 1; }
.home-cta-final .eyebrow {
    display: block;
    font-size: 12px; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--rosa-soft);
    margin-bottom: 18px;
}
.home-cta-final h2 {
    font-family: 'Playfair Display', serif;
    font-size: 44px; margin: 0 0 16px; font-weight: 600;
    letter-spacing: -0.5px;
}
.home-cta-final p {
    font-size: 17px; opacity: 0.80;
    margin: 0 auto 36px; max-width: 600px;
    line-height: 1.6;
}
.btn-branco {
    display: inline-flex; align-items: center; gap: 10px;
    background: var(--rosa); color: #fff !important;
    padding: 16px 36px; border-radius: 6px;
    font-weight: 600; font-size: 15px; text-decoration: none;
    transition: all 0.2s;
    border: 1px solid var(--rosa);
}
.btn-branco:hover { background: #ad4d61; border-color: #ad4d61; transform: translateY(-1px); }

/* ===== RESPONSIVO ===== */
@media (max-width: 992px) {
    .home-hero h1 { font-size: 42px; }
    .home-hero-grid { grid-template-columns: 1fr; gap: 40px; text-align: center; }
    .home-hero p { margin-left: auto; margin-right: auto; }
    .home-hero-ctas { justify-content: center; }
    .home-hero-img img { height: 360px; }
    .home-hero-img::before { display: none; }
    .home-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .servicos-grid, .destaques-grid, .depoimentos-grid, .porque-grid { grid-template-columns: repeat(2, 1fr); }
    .passos-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
    .home-hero { padding: 50px 20px 60px; }
    .home-hero h1 { font-size: 32px; }
    .home-titulo h2, .home-cta-final h2 { font-size: 28px; }
    .servicos-grid, .destaques-grid, .depoimentos-grid, .porque-grid, .passos-grid { grid-template-columns: 1fr; }
    .home-section { padding: 50px 20px; }
}
</style>

<!-- ============================================================ -->
<!-- 1. HERO                                                        -->
<!-- ============================================================ -->
<section class="home-hero">
    <div class="home-hero-grid">
        <div>
            <span class="home-hero-badge">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Bordados Personalizados
            </span>
            <h1>Peças únicas,<br>feitas com <em>amor</em>.</h1>
            <p>
                Transformamos tecidos em memórias. Enxovais de bebé, toalhas bordadas
                e presentes personalizados — cada peça é feita à mão, sob medida para si.
            </p>
            <div class="home-hero-ctas">
                <a href="pedir-orcamento.php" class="btn-rosa">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Pedir Orçamento
                </a>
                <a href="catalogo.php" class="btn-ghost">
                    Ver Portfólio
                </a>
            </div>
        </div>
        <div class="home-hero-img">
            <!-- fetchpriority="high": esta é a imagem mais importante da página (hero),
                 o browser deve carregá-la primeiro para melhorar o LCP (tempo até ao primeiro conteúdo visível) -->
            <img src="imagens/toalha/toalha01.jpg" alt="Bordado SylviArtes"
                 fetchpriority="high" decoding="async"
                 onerror="this.src='imagens/1.jpg'; this.onerror=null;">
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- 2. ESTATÍSTICAS                                                -->
<!-- ============================================================ -->
<section class="home-stats">
    <div class="home-stats-grid">

        <!-- Peças entregues — se ainda não há, mostra "Desde 2020" (sempre verdade) -->
        <div class="home-stat">
            <?php if ($pecasEntregues > 0): ?>
                <div class="num"><?= $pecasEntregues ?>+</div>
                <div class="label">Peças Entregues</div>
            <?php else: ?>
                <div class="num">2020</div>
                <div class="label">Fundada em Olhão</div>
            <?php endif; ?>
        </div>

        <!-- Avaliações — se não há, mostra classificação baseada em qualidade artesanal -->
        <div class="home-stat">
            <?php if ($avalTotal > 0): ?>
                <div class="num"><?= $avalMedia ?> ★</div>
                <div class="label">Média em <?= $avalTotal ?> avaliação<?= $avalTotal > 1 ? 'ões' : '' ?></div>
            <?php else: ?>
                <div class="num">5 ★</div>
                <div class="label">Qualidade Artesanal</div>
            <?php endif; ?>
        </div>

        <!-- Número de categorias -->
        <div class="home-stat">
            <div class="num"><?= $totalCategorias ?: '6' ?></div>
            <div class="label">Categorias</div>
        </div>

        <!-- Envio — facto concreto sempre verdadeiro -->
        <div class="home-stat">
            <div class="num"><i class="fas fa-shipping-fast" style="font-size:1.8rem;"></i></div>
            <div class="label">Envio para todo Portugal</div>
        </div>

    </div>
</section>

<!-- ============================================================ -->
<!-- 3. O QUE CRIAMOS                                               -->
<!-- ============================================================ -->
<section class="home-section">
    <div class="home-titulo">
        <span class="eyebrow">Os nossos serviços</span>
        <h2>O que criamos para si</h2>
        <p>Cada ponto conta uma história. Veja o que mais pedem.</p>
    </div>
    <div class="servicos-grid">
        <div class="servico-card">
            <div class="servico-icone"><i class="fa-solid fa-baby-carriage"></i></div>
            <h3>Enxovais de Bebé</h3>
            <p>Conjuntos completos com babetes, fraldas e bodies, tudo coordenado e bordado com o nome do bebé.</p>
        </div>
        <div class="servico-card">
            <div class="servico-icone"><i class="fa-solid fa-star"></i></div>
            <h3>Bordados Personalizados</h3>
            <p>Toalhas, mantas e portas-documentos com nomes ou iniciais. A oferta perfeita para qualquer idade.</p>
        </div>
        <div class="servico-card">
            <div class="servico-icone"><i class="fa-solid fa-gift"></i></div>
            <h3>Presentes Temáticos</h3>
            <p>Datas especiais merecem lembranças únicas. Batizados, aniversários, dia da mãe, casamentos.</p>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- 4. COMO FUNCIONA — 4 passos                                    -->
<!-- ============================================================ -->
<section class="home-passos">
    <div class="home-section">
        <div class="home-titulo">
            <span class="eyebrow">Processo</span>
            <h2>Como funciona</h2>
            <p>Simples, transparente e sem sair de casa.</p>
        </div>
        <div class="passos-grid">
            <div class="passo">
                <span class="passo-num">1</span>
                <i class="fa-solid fa-pencil"></i>
                <h4>Pedir Orçamento</h4>
                <p>Conte-nos o que pretende e envie fotos de inspiração.</p>
            </div>
            <div class="passo">
                <span class="passo-num">2</span>
                <i class="fa-solid fa-phone"></i>
                <h4>Receber Valor</h4>
                <p>Contactamos consigo com o valor final por email.</p>
            </div>
            <div class="passo">
                <span class="passo-num">3</span>
                <i class="fa-solid fa-credit-card"></i>
                <h4>Pagar Online</h4>
                <p>Link Stripe seguro com cartão ou MB Way.</p>
            </div>
            <div class="passo">
                <span class="passo-num">4</span>
                <i class="fa-solid fa-truck-fast"></i>
                <h4>Receber em Casa</h4>
                <p>Produzimos à mão e enviamos para a sua morada.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- 5. TRABALHOS EM DESTAQUE                                       -->
<!-- ============================================================ -->
<?php if (!empty($produtosDestaque)): ?>
<section class="home-section">
    <div class="home-titulo">
        <span class="eyebrow">Portfólio</span>
        <h2>Trabalhos em destaque</h2>
        <p>Algumas das nossas peças favoritas, feitas à mão com todo o cuidado.</p>
    </div>
    <div class="destaques-grid">
        <?php foreach ($produtosDestaque as $pd):
            // Imagem principal do produto (nome de ficheiro guardado na BD)
            $imgSrc = 'imagens/logo_sylviartes.png'; // fallback se não houver imagem
            if (!empty($pd['imagem'])) {
                $caminhoLocal = __DIR__ . '/imagens/produtos/' . $pd['imagem'];
                if (file_exists($caminhoLocal)) {
                    $imgSrc = 'imagens/produtos/' . $pd['imagem'];
                }
                // Ramo BLOB removido — imagens são sempre nomes de ficheiro.
            }
            $estatsP = calcular_media_estrelas($conn, (int)$pd['id']);
        ?>
            <a href="produto.php?id=<?= (int)$pd['id'] ?>" class="destaque-card">
                <div class="img">
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($pd['nome']) ?>" loading="lazy" decoding="async">
                </div>
                <div class="info">
                    <h5><?= htmlspecialchars($pd['nome']) ?></h5>
                    <?php if ($estatsP['total'] > 0): ?>
                        <div style="margin-bottom:6px;"><?= render_estrelas($estatsP['media'], true) ?></div>
                    <?php endif; ?>
                    <span class="ver">Ver detalhes →</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <div style="text-align:center; margin-top:36px;">
        <a href="catalogo.php" class="btn-ghost">Ver portfólio completo</a>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================ -->
<!-- 6. DEPOIMENTOS                                                 -->
<!-- ============================================================ -->
<?php if (!empty($depoimentos)): ?>
<section class="home-depoimentos">
    <div class="home-section">
        <div class="home-titulo">
            <span class="eyebrow">Testemunhos</span>
            <h2>O que dizem as nossas clientes</h2>
            <p>Avaliações reais de quem já comprou.</p>
        </div>
        <div class="depoimentos-grid">
            <?php foreach ($depoimentos as $d): ?>
                <div class="depoimento-card">
                    <div class="estrelas"><?= render_estrelas((float)$d['estrelas']) ?></div>
                    <p>"<?= htmlspecialchars($d['comentario']) ?>"</p>
                    <div class="depoimento-rodape">
                        <strong><?= htmlspecialchars($d['cliente']) ?></strong>
                        <?php if (!empty($d['produto'])): ?>
                            <div class="meta">
                                sobre <a href="produto.php?id=<?= (int)$d['produto_id'] ?>"><?= htmlspecialchars($d['produto']) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php else: ?>
<!-- Fallback: enquanto não há avaliações, mostra prova social via redes sociais -->
<section class="home-depoimentos">
    <div class="home-section" style="text-align:center;">
        <div class="home-titulo">
            <span class="eyebrow">Acompanhe-nos</span>
            <h2>Veja os nossos trabalhos nas redes</h2>
            <p>Estamos a recolher as primeiras avaliações. Entretanto, siga a SylviArtes para ver peças reais e novidades.</p>
        </div>
        <div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap; margin-top:10px;">
            <a href="https://www.instagram.com/sylvi.artes/" target="_blank" rel="noopener noreferrer"
               style="display:inline-flex; align-items:center; gap:10px; padding:14px 26px; border-radius:999px;
                      background:linear-gradient(135deg,#d66d7f,#bf5b6d); color:#fff; text-decoration:none; font-weight:600;">
                <i class="fab fa-instagram" style="font-size:20px;"></i> Ver no Instagram
            </a>
            <a href="https://www.facebook.com/people/SylviArtes/61565302160232/" target="_blank" rel="noopener noreferrer"
               style="display:inline-flex; align-items:center; gap:10px; padding:14px 26px; border-radius:999px;
                      background:#fff; color:#d66d7f; border:1.5px solid #f0c8d2; text-decoration:none; font-weight:600;">
                <i class="fab fa-facebook" style="font-size:20px;"></i> Ver no Facebook
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================ -->
<!-- 7. PORQUÊ A SYLVIARTES                                         -->
<!-- ============================================================ -->
<section class="home-section compact">
    <div class="home-titulo" style="margin-bottom:40px;">
        <span class="eyebrow">Vantagens</span>
        <h2>Porquê a SylviArtes</h2>
    </div>
    <div class="porque-grid">
        <div class="porque-item">
            <i class="fa-solid fa-hand-holding-heart"></i>
            <h5>100% Artesanal</h5>
            <p>Cada peça feita à mão, com tempo e dedicação.</p>
        </div>
        <div class="porque-item">
            <i class="fa-solid fa-medal"></i>
            <h5>Materiais Premium</h5>
            <p>Tecidos e linhas selecionados de alta qualidade.</p>
        </div>
        <div class="porque-item">
            <i class="fa-regular fa-comments"></i>
            <h5>Acompanhamento</h5>
            <p>Falamos consigo para garantir que fica perfeito.</p>
        </div>
        <div class="porque-item">
            <i class="fa-solid fa-shield-halved"></i>
            <h5>Pagamento Seguro</h5>
            <p>Stripe — cartão ou MB Way, totalmente protegido.</p>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- 8. CTA FINAL                                                   -->
<!-- ============================================================ -->
<section class="home-cta-final">
    <div class="home-cta-final-inner">
        <span class="eyebrow">Vamos começar</span>
        <h2>Pronto para criar uma peça única?</h2>
        <p>Conte-nos o que pretende e fazemos o orçamento sob medida — sem compromisso.</p>
        <a href="pedir-orcamento.php" class="btn-branco">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            Pedir Orçamento
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
