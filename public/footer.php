</main>

<footer>
    <div style="margin-bottom: 15px;">
        <p>&copy; <?php echo date("Y"); ?> SylviArtes - Costura Criativa</p>
    </div>
    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 15px;">
        <a href="https://www.facebook.com/people/SylviArtes/61565302160232/" target="_blank" rel="noopener noreferrer" title="Siga-nos no Facebook" aria-label="Facebook" style="color: #1877F2; font-size: 1.8rem; transition: var(--transicao-suave); padding: 10px; border-radius: 50%;">
            <i class="fab fa-facebook"></i>
        </a>
        <a href="https://www.instagram.com/sylvi.artes/" target="_blank" rel="noopener noreferrer" title="Siga-nos no Instagram" aria-label="Instagram" style="color: #e4405f; font-size: 1.8rem; transition: var(--transicao-suave); padding: 10px; border-radius: 50%;">
            <i class="fab fa-instagram"></i>
        </a>
        <!-- WhatsApp - muito usado em Portugal para contacto rápido com artesãos -->
        <a href="https://wa.me/351914058129" target="_blank" rel="noopener noreferrer" title="Fale connosco no WhatsApp" aria-label="WhatsApp" style="color: #25D366; font-size: 1.8rem; transition: var(--transicao-suave); padding: 10px; border-radius: 50%;">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>
    <div style="margin-top: 20px; font-size: 12px; opacity: 0.7;">
        <p>Feito com <i class="fas fa-heart" style="color: var(--cor-primaria);"></i> em Portugal</p>
    </div>
</footer>

<?php
// === Dados estruturados (SEO) ===
// JSON-LD diz ao Google quem somos. Aparece em todas as páginas públicas.
// Construímos o URL base a partir do servidor (funciona em localhost e em produção).
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'sylviartes.pt');
?>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "SylviArtes",
    "description": "Costura criativa e bordados personalizados feitos à mão em Portugal.",
    "url": "<?= htmlspecialchars($baseUrl) ?>",
    "logo": "<?= htmlspecialchars($baseUrl) ?>/public/imagens/logo_sylviartes.png",
    "telephone": "+351914058129",
    "areaServed": "PT",
    "sameAs": [
        "https://www.facebook.com/people/SylviArtes/61565302160232/",
        "https://www.instagram.com/sylvi.artes/"
    ]
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
/**
 * Loading state automático em formulários POST.
 * Quando o utilizador submete um form, o botão de submit:
 *   - é desativado (não permite duplo-clique)
 *   - ganha a classe .loading que mostra um spinner via CSS
 * Volta automaticamente ao normal se a página tiver erros (recarregar).
 */
document.addEventListener('submit', function(e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    // Skip forms com data-no-loading (ex: filtros do catálogo)
    if (form.hasAttribute('data-no-loading')) return;

    const btn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (btn && !btn.disabled) {
        btn.classList.add('loading');
        // Pequeno delay para o utilizador ver o spinner antes de o browser
        // sair desta página (em forms que fazem redirect)
        setTimeout(() => { btn.disabled = true; }, 50);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    // Shake automático em alertas de erro
    document.querySelectorAll('.alert-danger, .auth-erro').forEach(el => {
        el.classList.add('shake');
        setTimeout(() => el.classList.remove('shake'), 500);
    });

    // Fecha o menu hamburger ao clicar num link de navegação (mobile)
    document.querySelectorAll('#navbarNav .nav-link:not(.dropdown-toggle)').forEach(link => {
        link.addEventListener('click', () => {
            const nav = document.getElementById('navbarNav');
            if (nav && nav.classList.contains('show')) {
                bootstrap.Collapse.getInstance(nav)?.hide();
            }
        });
    });
});
</script>

</body>
</html>
