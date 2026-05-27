<?php
// Título e descrição para esta página
$pageTitle       = 'Contacto';
$pageDescription = 'Entre em contacto com a SylviArtes — bordados personalizados para todas as ocasiões.';
require_once __DIR__ . '/header.php';
?>

<!-- ========================================================
     PÁGINA DE CONTACTO
     Mostra todos os meios de contacto da SylviArtes.
     ======================================================== -->
<div style="background:#fff; padding-bottom: 60px;">

    <!-- Hero -->
    <div style="background: linear-gradient(135deg, #d66d7f, #bf5b6d); color: white; padding: 60px 20px; text-align: center;">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 2.5rem; margin-bottom: 10px;">
            Fale Connosco
        </h1>
        <p style="font-size: 1.1rem; opacity: 0.9; max-width: 500px; margin: 0 auto;">
            Estamos aqui para ajudar. Resposta rápida garantida!
        </p>
    </div>

    <div class="container" style="max-width: 900px; margin-top: 50px;">

        <div class="row g-4 justify-content-center">

            <!-- Telefone -->
            <div class="col-md-6 col-lg-4">
                <a href="tel:+351912058129" style="text-decoration:none;">
                    <div style="background:#fff; border-radius:16px; padding:30px; text-align:center;
                                box-shadow: 0 8px 30px rgba(214,109,127,0.10);
                                transition: transform 0.2s, box-shadow 0.2s;"
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 16px 40px rgba(214,109,127,0.20)';"
                         onmouseout="this.style.transform=''; this.style.boxShadow='0 8px 30px rgba(214,109,127,0.10)';">
                        <div style="font-size:2rem; color:#d66d7f; margin-bottom:12px;">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h5 style="color:#333; margin-bottom:6px;">Telefone</h5>
                        <p style="color:#d66d7f; font-weight:600; margin:0;">+351 912 058 129</p>
                    </div>
                </a>
            </div>

            <!-- WhatsApp — artesãos em Portugal usam muito o WhatsApp para contacto rápido -->
            <div class="col-md-6 col-lg-4">
                <!-- O link wa.me permite abrir uma conversa no WhatsApp directamente -->
                <a href="https://wa.me/351912058129" target="_blank" rel="noopener" style="text-decoration:none;">
                    <div style="background:#fff; border-radius:16px; padding:30px; text-align:center;
                                box-shadow: 0 8px 30px rgba(214,109,127,0.10);
                                transition: transform 0.2s, box-shadow 0.2s;"
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 16px 40px rgba(214,109,127,0.20)';"
                         onmouseout="this.style.transform=''; this.style.boxShadow='0 8px 30px rgba(214,109,127,0.10)';">
                        <div style="font-size:2rem; color:#25D366; margin-bottom:12px;">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <h5 style="color:#333; margin-bottom:6px;">WhatsApp</h5>
                        <p style="color:#25D366; font-weight:600; margin:0;">Enviar mensagem</p>
                    </div>
                </a>
            </div>

            <!-- Email -->
            <div class="col-md-6 col-lg-4">
                <a href="mailto:info@sylviartes.pt" style="text-decoration:none;">
                    <div style="background:#fff; border-radius:16px; padding:30px; text-align:center;
                                box-shadow: 0 8px 30px rgba(214,109,127,0.10);
                                transition: transform 0.2s, box-shadow 0.2s;"
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 16px 40px rgba(214,109,127,0.20)';"
                         onmouseout="this.style.transform=''; this.style.boxShadow='0 8px 30px rgba(214,109,127,0.10)';">
                        <div style="font-size:2rem; color:#d66d7f; margin-bottom:12px;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h5 style="color:#333; margin-bottom:6px;">Email</h5>
                        <p style="color:#d66d7f; font-weight:600; margin:0;">info@sylviartes.pt</p>
                    </div>
                </a>
            </div>

            <!-- Localização -->
            <div class="col-md-6 col-lg-4">
                <div style="background:#fff; border-radius:16px; padding:30px; text-align:center;
                            box-shadow: 0 8px 30px rgba(214,109,127,0.10);">
                    <div style="font-size:2rem; color:#d66d7f; margin-bottom:12px;">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h5 style="color:#333; margin-bottom:6px;">Localização</h5>
                    <p style="color:#666; margin:0;">Olhão, Algarve<br>Portugal</p>
                </div>
            </div>

            <!-- Horário -->
            <div class="col-md-6 col-lg-4">
                <div style="background:#fff; border-radius:16px; padding:30px; text-align:center;
                            box-shadow: 0 8px 30px rgba(214,109,127,0.10);">
                    <div style="font-size:2rem; color:#d66d7f; margin-bottom:12px;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5 style="color:#333; margin-bottom:6px;">Horário</h5>
                    <p style="color:#666; margin:0;">Segunda a Sexta<br>9h00 – 18h00</p>
                </div>
            </div>

        </div><!-- /row -->

        <!-- CTA para orçamento -->
        <div style="margin-top: 50px; background: linear-gradient(135deg, #fff0f3, #fce4ea);
                    border-radius: 16px; padding: 40px; text-align: center;">
            <h3 style="color:#d66d7f; font-family:'Playfair Display',serif; margin-bottom:10px;">
                Prefere pedir por formulário?
            </h3>
            <p style="color:#666; margin-bottom:20px;">
                Preencha os detalhes do seu pedido e respondemos em menos de 24 horas.
            </p>
            <a href="pedir-orcamento.php"
               style="background: linear-gradient(135deg, #d66d7f, #bf5b6d); color: white;
                      padding: 14px 36px; border-radius: 999px; text-decoration: none;
                      font-weight: 600; display: inline-block;">
                <i class="fas fa-file-alt"></i> Pedir Orçamento Grátis
            </a>
        </div>

    </div><!-- /container -->
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
