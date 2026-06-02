<?php
/**
 * =============================================================================
 *  ADMIN SIDEBAR — Navegação Lateral
 * =============================================================================
 *
 *  Incluída em todas as páginas admin (index, produtos, encomendas, etc.).
 *  Detecta se está numa subpasta para ajustar prefixos relativos dos links.
 * =============================================================================
 */

// Detecta se estamos numa subpasta (produtos/, encomendas/, categorias/, avaliacoes/)
$diretorio_atual = dirname($_SERVER['PHP_SELF']);
$prefixo = (
    strpos($diretorio_atual, 'produtos')    !== false ||
    strpos($diretorio_atual, 'encomendas')  !== false ||
    strpos($diretorio_atual, 'categorias')  !== false ||
    strpos($diretorio_atual, 'avaliacoes')  !== false ||
    strpos($diretorio_atual, 'ferramentas') !== false
) ? '../' : '';

// Caminho relativo para "ver loja"
$caminho_loja = ($prefixo === '../') ? '../../index.php' : '../index.php';

// Conta pedidos a aguardar orçamento (badge no menu Encomendas)
$badgeEncomendas = 0;
try {
    if (isset($conn) && $conn instanceof PDO) {
        $check = $conn->query("SHOW COLUMNS FROM pedido LIKE 'estado'");
        $col = $check->fetch(PDO::FETCH_ASSOC);
        $estadoFiltro = ($col && stripos($col['Type'] ?? '', 'aguarda_orcamento') !== false)
            ? 'aguarda_orcamento'
            : 'em_analise';
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedido WHERE estado = ?");
        $stmt->execute([$estadoFiltro]);
        $badgeEncomendas = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) { /* ignora */ }

// Conta avaliações pendentes
$badgeAvaliacoes = 0;
try {
    if (isset($conn) && $conn instanceof PDO) {
        $badgeAvaliacoes = (int)$conn->query("SELECT COUNT(*) FROM avaliacao WHERE aprovado = 0")->fetchColumn();
    }
} catch (Exception $e) { /* ignora */ }

// Função utilitária para classe ativa
function isActive($padrao) {
    return strpos($_SERVER['PHP_SELF'], $padrao) !== false ? 'active' : '';
}
?>
<!-- Botão hamburger e overlay para mobile -->
<button id="sidebar-toggle" class="sidebar-toggle-btn" aria-label="Abrir menu">
    <i class="fas fa-bars"></i>
</button>
<div id="sidebar-overlay" class="sidebar-overlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-area">
            <?php
            // Caminho relativo da imagem da logo (depende da subpasta atual)
            $logoSrc = ($prefixo === '../') ? '../../imagens/logo_sylviartes.png' : '../imagens/logo_sylviartes.png';
            ?>
            <img src="<?= htmlspecialchars($logoSrc) ?>" alt="SylviArtes" class="sidebar-logo-img">
            <h3>SylviArtes</h3>
        </div>
        <small><i class="fas fa-user-circle"></i> Olá, <?= htmlspecialchars($_SESSION['admin_nome'] ?? 'Admin') ?></small>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= $prefixo ?>index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' && $prefixo === '' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>

        <a href="<?= $prefixo ?>encomendas/index.php" class="<?= isActive('encomendas/') ?>">
            <i class="fas fa-box-open"></i>
            <span>Encomendas</span>
            <?php if ($badgeEncomendas > 0): ?>
                <span class="sidebar-badge"><?= $badgeEncomendas ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= $prefixo ?>produtos/index.php" class="<?= isActive('produtos/') ?>">
            <i class="fas fa-images"></i>
            <span>Portfólio</span>
        </a>

        <a href="<?= $prefixo ?>categorias/index.php" class="<?= isActive('categorias/') ?>">
            <i class="fas fa-folder"></i>
            <span>Categorias</span>
        </a>

        <a href="<?= $prefixo ?>avaliacoes/index.php" class="<?= isActive('avaliacoes/') ?>">
            <i class="fas fa-star"></i>
            <span>Avaliações</span>
            <?php if ($badgeAvaliacoes > 0): ?>
                <span class="sidebar-badge"><?= $badgeAvaliacoes ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= $prefixo ?>ferramentas/migrar.php" class="<?= isActive('ferramentas/') ?>">
            <i class="fas fa-database"></i>
            <span>Migrações BD</span>
        </a>

        <!-- rel="noopener noreferrer": boa prática de segurança para links target="_blank"
             Impede que a nova aba aceda ao objeto window da aba de origem (proteção contra tabnabbing) -->
        <a href="<?= htmlspecialchars($caminho_loja) ?>" target="_blank"
           rel="noopener noreferrer"
           title="Abre o site público numa nova aba"
           class="sidebar-loja">
            <i class="fas fa-external-link-alt"></i>
            <span>Ver Site</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= $prefixo ?>logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </div>
</div>

<style>
/* === HAMBURGER MOBILE === */
.sidebar-toggle-btn {
    display: none;
    position: fixed;
    top: 16px; left: 16px;
    z-index: 1100;
    width: 44px; height: 44px;
    border: none; border-radius: 10px;
    background: #fff;
    color: #d66d7f;
    box-shadow: 0 4px 14px rgba(0,0,0,0.10);
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
}
.sidebar-toggle-btn:hover { background: #fff8fa; }

.sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.40);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.25s;
}
.sidebar-overlay.show {
    display: block; opacity: 1;
}

/* Em mobile (<= 768px): sidebar fica oculta por defeito, abre como drawer */
@media (max-width: 768px) {
    .sidebar-toggle-btn { display: flex; align-items: center; justify-content: center; }

    .sidebar {
        width: 280px !important;
        padding: 0 !important;
        transform: translateX(-100%);
        transition: transform 0.28s ease;
        z-index: 1000;
        height: 100vh;
        overflow-y: auto;
    }
    .sidebar.open { transform: translateX(0); }

    /* Mostrar texto e logo no drawer (em vez de só ícones) */
    .sidebar-header h3,
    .sidebar-header small,
    .sidebar a span,
    .sidebar-logo-img { display: block !important; }
    .sidebar a {
        justify-content: flex-start !important;
        padding: 14px 22px !important;
    }

    .main-content {
        margin-left: 0 !important;
        padding: 70px 16px 30px !important;
    }
}

/* Logo da SylviArtes em vez do círculo rosa decorativo */
.sidebar-logo-img {
    width: 80px;
    height: 80px;
    object-fit: contain;
    background: #fff;
    border-radius: 50%;
    padding: 6px;
    box-shadow: 0 6px 20px rgba(214, 109, 127, 0.30);
    border: 2px solid rgba(214, 109, 127, 0.40);
}

/* Tamanho consistente do "Olá, Admin" em todas as páginas
   (o <small> default herda do body que difere entre páginas) */
.sidebar-header small {
    font-size: 13px !important;
    line-height: 1.4 !important;
    color: rgba(255, 255, 255, 0.75);
}

/* Badge para o sidebar (notificações) */
.sidebar-badge {
    background: #d66d7f;
    color: #fff;
    border-radius: 999px;
    padding: 2px 9px;
    font-size: 11px;
    font-weight: 700;
    margin-left: auto;
}
.sidebar-loja {
    background: linear-gradient(135deg, #d66d7f, #e8a4b0) !important;
    color: #fff !important;
    margin-top: 16px !important;
    border-radius: 10px !important;
}
.sidebar-loja:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(214, 109, 127, 0.25);
}

/* Barra lateral do item ativo — visível sem necessitar de hover.
   O ::before existe em todos os links (scaleY 0), mas no ativo deve estar sempre visível. */
.sidebar a.active::before {
    transform: scaleY(1);
}
</style>
<script>
(function () {
    var btn = document.getElementById('sidebar-toggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    if (!btn || !sidebar || !overlay) return;

    function open() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', open);
    overlay.addEventListener('click', close);
    // Fecha drawer ao clicar num link (navegação)
    sidebar.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
            if (window.innerWidth <= 768) close();
        });
    });
    // Fecha automaticamente se redimensionar para desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) close();
    });
})();
</script>
