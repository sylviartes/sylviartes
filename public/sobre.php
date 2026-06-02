<?php
// Título e descrição para esta página (usados no <head> do header.php)
$pageTitle       = 'Sobre Nós';
$pageDescription = 'Conheça a história da SylviArtes — bordados artesanais feitos com amor desde 2020.';
require_once __DIR__ . '/header.php';
?>

<style>
/* ================================================================
   SOBRE NÓS — estilos específicos desta página
   ================================================================ */

/* Anula o padding do .pagina-main para permitir secções full-width
   (igual ao que index.php faz para o hero e secções de fundo branco) */
main { padding: 0 !important; max-width: 100% !important; }

/* === HERO === */
.sobre-hero {
    background: linear-gradient(135deg, #d66d7f 0%, #bf5b6d 100%);
    color: #fff;
    padding: 72px 24px;
    text-align: center;
}
.sobre-hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.6rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    margin: 0 0 12px;
}
.sobre-hero p {
    font-size: 1.1rem;
    opacity: 0.9;
    max-width: 540px;
    margin: 0 auto;
    line-height: 1.6;
}

/* === ÁREA DE CONTEÚDO === */
.sobre-conteudo {
    max-width: 980px;
    margin: 0 auto;
    padding: 64px 24px 80px;
}

/* === HISTÓRIA — grelha texto + foto === */
.sobre-historia {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 56px;
    align-items: center;
    margin-bottom: 72px;
}
.sobre-historia h2 {
    font-family: 'Playfair Display', serif;
    color: #d66d7f;
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 20px;
}
.sobre-historia p {
    color: #555;
    line-height: 1.8;
    font-size: 15.5px;
    margin-bottom: 14px;
}
.sobre-historia p:last-child { margin-bottom: 0; }

/* Foto da artesã */
.sobre-foto {
    width: 100%;
    max-width: 320px;
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(214, 109, 127, 0.18);
    object-fit: cover;
    display: block;
    margin: 0 auto;
}

/* === TÍTULO DE SECÇÃO === */
.sobre-secao-titulo {
    font-family: 'Playfair Display', serif;
    color: #333;
    text-align: center;
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 36px;
}

/* === CARDS DE VALORES === */
.sobre-valores {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-bottom: 64px;
}
.sobre-valor-card {
    background: #fff;
    border-radius: 16px;
    padding: 32px 24px;
    text-align: center;
    box-shadow: 0 8px 30px rgba(214, 109, 127, 0.10);
    border: 1px solid #f5e6ea;
    transition: transform 0.25s, box-shadow 0.25s;
}
.sobre-valor-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(214, 109, 127, 0.18);
}
.sobre-valor-icone {
    font-size: 2rem;
    color: #d66d7f;
    margin-bottom: 14px;
    display: block;
}
.sobre-valor-card h5 {
    color: #333;
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0 0 10px;
}
.sobre-valor-card p {
    color: #777;
    font-size: 0.9rem;
    line-height: 1.65;
    margin: 0;
}

/* === CTA FINAL === */
.sobre-cta {
    background: linear-gradient(135deg, #fff0f3, #fce4ea);
    border-radius: 16px;
    padding: 44px 32px;
    text-align: center;
}
.sobre-cta h3 {
    font-family: 'Playfair Display', serif;
    color: #d66d7f;
    font-size: 1.65rem;
    font-weight: 700;
    margin: 0 0 12px;
}
.sobre-cta p {
    color: #666;
    font-size: 15.5px;
    margin: 0 0 24px;
}
.sobre-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #d66d7f, #bf5b6d);
    color: #fff;
    padding: 15px 38px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.sobre-cta-btn:hover {
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(214, 109, 127, 0.35);
}

/* === RESPONSIVO === */

/* Tablet */
@media (max-width: 768px) {
    .sobre-hero { padding: 52px 20px; }
    .sobre-hero h1 { font-size: 2rem; }
    .sobre-historia {
        grid-template-columns: 1fr;
        gap: 32px;
        text-align: center;
    }
    .sobre-valores { grid-template-columns: repeat(2, 1fr); }
    .sobre-conteudo { padding: 44px 20px 60px; }
    .sobre-secao-titulo { font-size: 1.7rem; }
}

/* Telemóvel */
@media (max-width: 480px) {
    .sobre-hero h1 { font-size: 1.75rem; }
    .sobre-valores { grid-template-columns: 1fr; }
    .sobre-cta { padding: 32px 20px; }
    .sobre-secao-titulo { font-size: 1.5rem; }
}
</style>

<!-- =============================================
     SOBRE NÓS — História e valores da SylviArtes
     ============================================= -->

<!-- Hero de topo — fundo gradient rosa, título e tagline -->
<section class="sobre-hero">
    <h1>Sobre a SylviArtes</h1>
    <p>Costura artesanal com amor e dedicação desde 2020.</p>
</section>

<div class="sobre-conteudo">

    <!-- ---- A nossa história (texto à esquerda, foto à direita) ---- -->
    <div class="sobre-historia">
        <div>
            <h2>A nossa história</h2>
            <p>
                A arte e a criatividade sempre correram nas veias da fundadora da SylviArtes.
                Esta paixão começou muito cedo: com apenas 5 anos, já pintava pequenos azulejos
                à mão para vender às amigas da avó. Esse dom natural e amor pela criação
                acompanharam-na toda a vida.
            </p>
            <p>
                Hoje, essa mesma dedicação reflete-se na costura criativa. A SylviArtes é o
                culminar de uma vida inteira de amor pela arte — cada peça é feita à mão com
                materiais de qualidade e um acabamento impecável.
            </p>
        </div>

        <!-- Foto da artesã: guardar a imagem como public/imagens/sylvia_atelier.jpg
             para que apareça aqui. Enquanto não existir, mostra o logo (onerror). -->
        <img src="imagens/sylvia_atelier.jpg"
             alt="Sylvia, fundadora da SylviArtes, no seu atelier em Olhão"
             class="sobre-foto"
             loading="lazy"
             onerror="this.onerror=null; this.src='imagens/logo_sylviartes.png';">
    </div>

    <!-- ---- O que nos define (3 cards) ---- -->
    <h2 class="sobre-secao-titulo">O que nos define</h2>
    <div class="sobre-valores">

        <div class="sobre-valor-card">
            <span class="sobre-valor-icone"><i class="fas fa-hand-holding-heart"></i></span>
            <h5>Feito à Mão</h5>
            <p>Cada peça é criada manualmente, com atenção ao detalhe e cuidado especial.</p>
        </div>

        <div class="sobre-valor-card">
            <span class="sobre-valor-icone"><i class="fas fa-star"></i></span>
            <h5>Personalizado</h5>
            <p>Criamos peças únicas para datas especiais, presentes e momentos inesquecíveis.</p>
        </div>

        <div class="sobre-valor-card">
            <span class="sobre-valor-icone"><i class="fas fa-map-marker-alt"></i></span>
            <h5>Feito em Portugal</h5>
            <p>Atelier em Olhão, no Algarve. Envio para todo o território nacional.</p>
        </div>

    </div>

    <!-- ---- CTA final ---- -->
    <div class="sobre-cta">
        <h3>Quer uma peça personalizada?</h3>
        <p>Conte-nos o que imagina e nós criamos.</p>
        <a href="pedir-orcamento.php" class="sobre-cta-btn">
            <i class="fas fa-paint-brush"></i> Pedir Orçamento Grátis
        </a>
    </div>

</div><!-- /sobre-conteudo -->

<?php require_once __DIR__ . '/footer.php'; ?>
