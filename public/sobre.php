<?php
// Título e descrição para esta página
$pageTitle       = 'Sobre Nós';
$pageDescription = 'Conheça a história da SylviArtes — bordados artesanais feitos com amor desde 2020.';
require_once __DIR__ . '/header.php';
?>
<div class="container">
    <div class="section-header">
        <h2 class="section-title">Sobre a SylviArtes</h2>
        <p class="section-subtitle">Costura artesanal com amor e dedicação desde 2020.</p>
    </div>
    <div class="row g-5">
        <div class="col-lg-6">
            <div class="card-form">
                <h3>A nossa história</h3>
                <p>A arte e a criatividade sempre correram nas veias da fundadora da SylviArtes. Esta paixão começou muito cedo: com apenas 5 anos, já pintava pequenos azulejos à mão para vender às amigas da avó. Esse dom natural e amor pela criação acompanharam-na toda a vida.</p>
                <p>Hoje, essa mesma dedicação reflete-se na costura criativa. A SylviArtes é o culminar de uma vida inteira de amor pela arte, onde cada peça é feita à mão com materiais premium e um acabamento impecável. Especializamo-nos em enxovais personalizados, bordados únicos e lembranças pensadas ao pormenor para datas especiais.</p>
            </div>
        </div>
        <div class="col-lg-6">
            <img src="imagens/logo_sylviartes.png" alt="SylviArtes" style="width: 100%; border-radius: var(--raio-borda); box-shadow: var(--sombra-card);" onerror="this.src='imagens/1.jpg';">
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>