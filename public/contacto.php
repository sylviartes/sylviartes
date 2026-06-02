<?php
// Título e descrição para esta página (usados no <head> do header.php)
$pageTitle       = 'Contacto';
$pageDescription = 'Entre em contacto com a SylviArtes — bordados personalizados para todas as ocasiões.';
require_once __DIR__ . '/header.php';
?>

<style>
/* ================================================================
   CONTACTO — estilos específicos desta página
   ================================================================ */

/* Anula o padding do .pagina-main para secções full-width */
main { padding: 0 !important; max-width: 100% !important; }

/* === HERO === */
.contacto-hero {
    background: linear-gradient(135deg, #d66d7f 0%, #bf5b6d 100%);
    color: #fff;
    padding: 72px 24px;
    text-align: center;
}
.contacto-hero h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.6rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    margin: 0 0 12px;
}
.contacto-hero p {
    font-size: 1.1rem;
    opacity: 0.9;
    max-width: 500px;
    margin: 0 auto;
    line-height: 1.6;
}

/* === ÁREA DE CONTEÚDO === */
.contacto-conteudo {
    max-width: 960px;
    margin: 0 auto;
    padding: 64px 24px 80px;
}

/* === GRELHA DE CARDS DE CONTACTO === */
.contacto-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-bottom: 52px;
}

/* Card base — tanto <a> como <div> partilham estes estilos */
.contacto-card {
    background: #fff;
    border-radius: 16px;
    padding: 32px 24px;
    text-align: center;
    box-shadow: 0 8px 30px rgba(214, 109, 127, 0.10);
    border: 1px solid #f5e6ea;
    /* Transição CSS em vez de onmouseover/onmouseout JS — mais limpo e acessível */
    transition: transform 0.25s, box-shadow 0.25s;
    /* Reset para <a> */
    text-decoration: none;
    color: inherit;
    display: block;
}
.contacto-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(214, 109, 127, 0.22);
    color: inherit;
}

.contacto-card-icone {
    font-size: 2rem;
    color: #d66d7f;
    margin-bottom: 14px;
    display: block;
}
/* Cor específica para o ícone do WhatsApp */
.contacto-card-icone.whatsapp { color: #25D366; }

.contacto-card h5 {
    color: #333;
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 8px;
}
.contacto-card p {
    margin: 0;
    font-size: 14.5px;
    line-height: 1.6;
}
.val-rosa  { color: #d66d7f; font-weight: 600; }
.val-verde { color: #25D366; font-weight: 600; }
.val-cinza { color: #666; }

/* === CTA FINAL === */
.contacto-cta {
    background: linear-gradient(135deg, #fff0f3, #fce4ea);
    border-radius: 16px;
    padding: 44px 32px;
    text-align: center;
}
.contacto-cta h3 {
    font-family: 'Playfair Display', serif;
    color: #d66d7f;
    font-size: 1.65rem;
    font-weight: 700;
    margin: 0 0 12px;
}
.contacto-cta p {
    color: #666;
    font-size: 15.5px;
    margin: 0 0 24px;
}
.contacto-cta-btn {
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
.contacto-cta-btn:hover {
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(214, 109, 127, 0.35);
}

/* === RESPONSIVO === */

/* Tablet */
@media (max-width: 768px) {
    .contacto-hero { padding: 52px 20px; }
    .contacto-hero h1 { font-size: 2rem; }
    /* 2 colunas em tablet — mais legível do que 3 */
    .contacto-grid { grid-template-columns: repeat(2, 1fr); }
    .contacto-conteudo { padding: 44px 20px 60px; }
}

/* Telemóvel */
@media (max-width: 480px) {
    .contacto-hero h1 { font-size: 1.75rem; }
    .contacto-grid { grid-template-columns: 1fr; }
    .contacto-cta { padding: 32px 20px; }
}
</style>

<!-- ==================================================
     CONTACTO — Meios de contacto da SylviArtes
     ================================================== -->

<!-- Hero de topo -->
<section class="contacto-hero">
    <h1>Fale Connosco</h1>
    <p>Estamos aqui para ajudar. Resposta rápida garantida!</p>
</section>

<div class="contacto-conteudo">

    <!-- Grelha de cards de contacto -->
    <div class="contacto-grid">

        <!-- Telefone — link tel: abre discador em telemóvel -->
        <a href="tel:+351912058129" class="contacto-card">
            <span class="contacto-card-icone"><i class="fas fa-phone"></i></span>
            <h5>Telefone</h5>
            <p class="val-rosa">+351 912 058 129</p>
        </a>

        <!-- WhatsApp — wa.me abre conversa direta no WhatsApp -->
        <a href="https://wa.me/351912058129" target="_blank" rel="noopener noreferrer" class="contacto-card">
            <span class="contacto-card-icone whatsapp"><i class="fab fa-whatsapp"></i></span>
            <h5>WhatsApp</h5>
            <p class="val-verde">Enviar mensagem</p>
        </a>

        <!-- Email -->
        <a href="mailto:sylviartes.pt@gmail.com" class="contacto-card">
            <span class="contacto-card-icone"><i class="fas fa-envelope"></i></span>
            <h5>Email</h5>
            <p class="val-rosa">sylviartes.pt@gmail.com</p>
        </a>

        <!-- Localização — não é clicável, usa <div> em vez de <a> -->
        <div class="contacto-card">
            <span class="contacto-card-icone"><i class="fas fa-map-marker-alt"></i></span>
            <h5>Localização</h5>
            <p class="val-cinza">Olhão, Algarve<br>Portugal</p>
        </div>

        <!-- Horário de atendimento -->
        <div class="contacto-card">
            <span class="contacto-card-icone"><i class="fas fa-clock"></i></span>
            <h5>Horário</h5>
            <p class="val-cinza">Segunda a Sexta<br>9h00 – 18h00</p>
        </div>

    </div><!-- /contacto-grid -->

    <!-- CTA para pedir orçamento via formulário -->
    <div class="contacto-cta">
        <h3>Prefere pedir por formulário?</h3>
        <p>Preencha os detalhes do seu pedido e respondemos em menos de 24 horas.</p>
        <a href="pedir-orcamento.php" class="contacto-cta-btn">
            <i class="fas fa-file-alt"></i> Pedir Orçamento Grátis
        </a>
    </div>

</div><!-- /contacto-conteudo -->

<?php require_once __DIR__ . '/footer.php'; ?>
