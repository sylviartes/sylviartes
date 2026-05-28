<?php
// Título e descrição para esta página
$pageTitle       = 'Sobre Nós';
$pageDescription = 'Conheça a história da SylviArtes — bordados artesanais feitos com amor desde 2020.';
require_once __DIR__ . '/header.php';
?>

<!-- ========================================================
     PÁGINA SOBRE NÓS
     Conta a história da SylviArtes e os valores da marca.
     ======================================================== -->
<div style="background:#fff; padding-bottom: 60px;">

    <!-- Secção de boas-vindas / hero -->
    <div style="background: linear-gradient(135deg, #d66d7f, #bf5b6d); color: white; padding: 60px 20px; text-align: center;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 2.5rem; margin-bottom: 10px;">
            Sobre a SylviArtes
        </h1>
        <p style="font-size: 1.15rem; opacity: 0.9; max-width: 550px; margin: 0 auto;">
            Costura artesanal com amor e dedicação desde 2020.
        </p>
    </div>

    <div class="container" style="max-width: 960px; margin-top: 50px;">

        <!-- ---- A nossa história ---- -->
        <div class="row g-5 align-items-center mb-5">
            <!-- Texto -->
            <div class="col-lg-7">
                <h2 style="font-family:'Playfair Display',serif; color:#d66d7f; margin-bottom: 15px;">
                    A nossa história
                </h2>
                <p style="color:#555; line-height:1.8; margin-bottom: 15px;">
                    A arte e a criatividade sempre correram nas veias da fundadora da SylviArtes.
                    Esta paixão começou muito cedo: com apenas 5 anos, já pintava pequenos azulejos
                    à mão para vender às amigas da avó. Esse dom natural e amor pela criação
                    acompanharam-na toda a vida.
                </p>
                <p style="color:#555; line-height:1.8;">
                    Hoje, essa mesma dedicação reflete-se na costura criativa. A SylviArtes é o
                    culminar de uma vida inteira de amor pela arte — cada peça é feita à mão com
                    materiais de qualidade e um acabamento impecável.
                </p>
            </div>
            <!-- Foto da artesã no atelier -->
            <!-- Para colocar a foto real: guardar a imagem como
                 public/imagens/sylvia_atelier.jpg — aparece automaticamente.
                 Enquanto não existir, mostra o logo (onerror). -->
            <div class="col-lg-5 text-center">
                <img src="imagens/sylvia_atelier.jpg"
                     alt="Sylvia, fundadora da SylviArtes, no seu atelier em Olhão"
                     loading="lazy"
                     style="max-width: 320px; width: 100%; border-radius: 16px;
                            box-shadow: 0 10px 40px rgba(214,109,127,0.15); object-fit: cover;"
                     onerror="this.onerror=null; this.src='imagens/logo_sylviartes.png'; this.alt='Logo SylviArtes';">
            </div>
        </div>

        <!-- ---- Cards de valores ---- -->
        <h2 style="font-family:'Playfair Display',serif; color:#333; text-align:center; margin-bottom: 30px;">
            O que nos define
        </h2>
        <div class="row g-4 mb-5">

            <!-- Card 1 -->
            <div class="col-md-4">
                <div style="background:#fff; border-radius:16px; padding:30px; text-align:center;
                            box-shadow: 0 8px 30px rgba(214,109,127,0.10); height:100%;">
                    <div style="font-size:2rem; color:#d66d7f; margin-bottom:12px;">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <h5 style="color:#333; margin-bottom:8px;">Feito à Mão</h5>
                    <p style="color:#777; font-size:0.9rem; line-height:1.6;">
                        Cada peça é criada manualmente, com atenção ao detalhe e cuidado especial.
                    </p>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="col-md-4">
                <div style="background:#fff; border-radius:16px; padding:30px; text-align:center;
                            box-shadow: 0 8px 30px rgba(214,109,127,0.10); height:100%;">
                    <div style="font-size:2rem; color:#d66d7f; margin-bottom:12px;">
                        <i class="fas fa-star"></i>
                    </div>
                    <h5 style="color:#333; margin-bottom:8px;">Personalizado</h5>
                    <p style="color:#777; font-size:0.9rem; line-height:1.6;">
                        Criamos peças únicas para datas especiais, presentes e momentos inesquecíveis.
                    </p>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="col-md-4">
                <div style="background:#fff; border-radius:16px; padding:30px; text-align:center;
                            box-shadow: 0 8px 30px rgba(214,109,127,0.10); height:100%;">
                    <div style="font-size:2rem; color:#d66d7f; margin-bottom:12px;">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h5 style="color:#333; margin-bottom:8px;">Feito em Portugal</h5>
                    <p style="color:#777; font-size:0.9rem; line-height:1.6;">
                        Atelier em Olhão, no Algarve. Envio para todo o território nacional.
                    </p>
                </div>
            </div>
        </div>

        <!-- ---- CTA final ---- -->
        <div style="background: linear-gradient(135deg, #fff0f3, #fce4ea); border-radius:16px;
                    padding: 40px; text-align: center;">
            <h3 style="color:#d66d7f; font-family:'Playfair Display',serif; margin-bottom:10px;">
                Quer uma peça personalizada?
            </h3>
            <p style="color:#666; margin-bottom:20px;">
                Conte-nos o que imagina e nós criamos.
            </p>
            <a href="pedir-orcamento.php"
               style="background: linear-gradient(135deg, #d66d7f, #bf5b6d); color: white;
                      padding: 14px 36px; border-radius: 999px; text-decoration: none;
                      font-weight: 600; display: inline-block;">
                <i class="fas fa-paint-brush"></i> Pedir Orçamento Grátis
            </a>
        </div>

    </div><!-- /container -->
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
