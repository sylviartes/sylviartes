<?php
/**
 * =============================================================================
 *  HEADER — Topo de TODAS as páginas públicas
 * =============================================================================
 *
 *  Cabeçalho comum: logo, menu de navegação, link "Entrar"/"Minha Conta",
 *  ícone do carrinho com contador. Incluído com require_once em cada página
 *  pública (index, catalogo, produto, carrinho, pedido, etc.).
 *
 *  Variáveis disponíveis depois deste include:
 *      $cartCount  → número total de unidades no carrinho (para o badge)
 *
 *  O link de utilizador muda dinamicamente:
 *      - Sem login → "Entrar"
 *      - Com login → dropdown "Olá, {nome}" com Minha Conta / Sair
 * =============================================================================
 */

// Inicia sessão se ainda não iniciada (necessário para ler $_SESSION abaixo)
require_once __DIR__ . '/../config/session.php';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Título da página: cada página define $pageTitle antes de incluir este ficheiro.
         Se não definir, usa o título padrão. -->
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — SylviArtes' : 'SylviArtes - Costura Criativa'; ?></title>

    <!-- Meta description: aparece no resultado do Google por baixo do título.
         Cada página pode definir $pageDescription para personalizar. -->
    <?php if (!empty($pageDescription)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <?php else: ?>
    <meta name="description" content="SylviArtes — Costura criativa e bordados personalizados feitos à mão em Portugal.">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Animações e loading states personalizados -->
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/cliente/') !== false) ? '../imagens/animacoes.css' : 'imagens/animacoes.css'; ?>">

    <style>
        :root {
            --bs-primary: #d66d7f !important;
            --bs-primary-rgb: 214, 109, 127 !important;
            --cor-primaria: #d66d7f;
            --cor-primaria-hover: #bf5b6d;
            --cor-secundaria: #e8a4b0;
            --cor-fundo-suave: #fff0f3;
            --cor-texto-titulo: #2d3436;
            --cor-texto-corpo: #636e72;
            --cor-branco: #ffffff;
            --sombra-card: 0 10px 40px rgba(214, 109, 127, 0.12);
            --sombra-hover: 0 20px 60px rgba(214, 109, 127, 0.2);
            --raio-borda: 16px;
            --gradiente-rosa: linear-gradient(135deg, #d66d7f 0%, #e8a4b0 50%, #d66d7f 100%);
            --gradiente-rosa-hover: linear-gradient(135deg, #bf5b6d 0%, #d66d7f 100%);
            --transicao-suave: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            --transicao-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fafbfc;
            color: var(--cor-texto-corpo);
            line-height: 1.7;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: 
                radial-gradient(ellipse at 0% 0%, rgba(214, 109, 127, 0.05) 0%, transparent 50%),
                radial-gradient(ellipse at 100% 100%, rgba(214, 109, 127, 0.08) 0%, transparent 50%),
                linear-gradient(180deg, #fafbfc 0%, #fff5f7 100%);
        }

        header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: var(--transicao-suave);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
        }

        header:hover { 
            box-shadow: 0 8px 40px rgba(214, 109, 127, 0.15); 
        }

        .topbar { 
            max-width: 1300px; 
            margin: 0 auto; 
            padding: 10px 30px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
        }

        .logo-area { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            text-decoration: none; 
            transition: var(--transicao-suave); 
        }
        .logo-area:hover { 
            transform: scale(1.03); 
        }

        .logo-img { 
            height: 70px; 
            width: auto; 
            filter: drop-shadow(0 3px 6px rgba(0,0,0,0.1)); 
            transition: var(--transicao-suave); 
            border-radius: 12px;
        }
        .logo-area:hover .logo-img { 
            filter: drop-shadow(0 8px 20px rgba(214, 109, 127, 0.35)); 
            transform: translateY(-3px) scale(1.02);
        }

        .logo-texto { 
            font-size: 26px; 
            font-weight: 700; 
            background: var(--gradiente-rosa); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            background-clip: text; 
            letter-spacing: -0.5px;
            font-family: 'Playfair Display', serif;
        }

        .btn { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px; 
            padding: 14px 32px; 
            border-radius: 50px; 
            font-weight: 600; 
            text-decoration: none; 
            transition: var(--transicao-bounce); 
            font-size: 14px; 
            cursor: pointer; 
            border: none; 
            position: relative; 
            overflow: hidden; 
            z-index: 1;
        }

        .btn::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: -100%; 
            width: 100%; 
            height: 100%; 
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent); 
            transition: 0.6s; 
            z-index: -1;
        }
        .btn:hover::before { 
            left: 100%; 
        }

        .btn-primary { 
            background: var(--gradiente-rosa); 
            color: white; 
            box-shadow: 0 6px 20px rgba(214, 109, 127, 0.35); 
        }
        .btn-primary:hover { 
            background: var(--gradiente-rosa-hover); 
            transform: translateY(-4px) scale(1.02); 
            box-shadow: 0 12px 35px rgba(214, 109, 127, 0.45); 
        }

        .btn-secondary { 
            background-color: white; 
            color: var(--cor-primaria); 
            border: 2px solid var(--cor-fundo-suave); 
        }
        .btn-secondary:hover { 
            border-color: var(--cor-primaria); 
            background-color: var(--cor-fundo-suave); 
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(214, 109, 127, 0.2);
        }

        .container { 
            width: 100%; 
            max-width: 1300px; 
            margin: 0 auto; 
            padding: 50px 30px; 
            flex: 1; 
        }

        .section-header { 
            text-align: center; 
            margin-bottom: 60px; 
            margin-top: 30px; 
        }

        .section-title { 
            font-size: 42px; 
            color: var(--cor-texto-titulo); 
            font-weight: 700; 
            margin-bottom: 15px; 
            position: relative; 
            display: inline-block;
            font-family: 'Playfair Display', serif;
        }
        .section-title::after { 
            content: ''; 
            position: absolute; 
            bottom: -10px; 
            left: 50%; 
            transform: translateX(-50%); 
            width: 80px; 
            height: 4px; 
            background: var(--gradiente-rosa); 
            border-radius: 3px; 
            transition: var(--transicao-suave); 
        }
        .section-header:hover .section-title::after { 
            width: 120px; 
        }

        .section-subtitle { 
            font-size: 17px; 
            color: #999; 
            max-width: 650px; 
            margin: 25px auto 0; 
            font-weight: 400;
        }

        .card-form { 
            background: white; 
            padding: 40px 50px; 
            border-radius: 24px; 
            box-shadow: var(--sombra-card); 
            margin-top: 30px; 
            transition: var(--transicao-suave); 
            border: 1px solid rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
        }
        .card-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradiente-rosa);
        }
        .card-form:hover { 
            box-shadow: var(--sombra-hover); 
            transform: translateY(-8px); 
        }
        .card-form label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 8px; 
            color: var(--cor-texto-titulo); 
            transition: var(--transicao-suave); 
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        .card-form input, .card-form textarea { 
            width: 100%; 
            padding: 14px 18px; 
            margin-bottom: 20px; 
            border: 2px solid #f0f0f0; 
            border-radius: 12px; 
            box-sizing: border-box; 
            transition: var(--transicao-suave); 
            font-family: inherit;
            font-size: 15px;
        }
        .card-form input:focus, .card-form textarea:focus { 
            border-color: var(--cor-primaria); 
            outline: none; 
            box-shadow: 0 0 0 5px rgba(214, 109, 127, 0.12);
        }
        .card-form input::placeholder, .card-form textarea::placeholder { 
            color: #bbb; 
        }
        .card-form button[type="submit"] { 
            background: var(--gradiente-rosa); 
            color: white; 
            border: none; 
            padding: 16px 40px; 
            border-radius: 50px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: var(--transicao-bounce); 
            box-shadow: 0 6px 25px rgba(214, 109, 127, 0.35);
            font-size: 15px;
            letter-spacing: 0.5px;
        }
        .card-form button[type="submit"]:hover { 
            background: var(--gradiente-rosa-hover); 
            transform: translateY(-4px) scale(1.02); 
            box-shadow: 0 15px 40px rgba(214, 109, 127, 0.45); 
        }

        footer { 
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%); 
            border-top: 1px solid rgba(0,0,0,0.04); 
            padding: 50px 30px; 
            text-align: center; 
            margin-top: auto; 
            font-size: 14px; 
            color: #888; 
            position: relative;
        }
        footer::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            height: 4px; 
            background: var(--gradiente-rosa); 
        }
        footer a { 
            color: var(--cor-primaria); 
            text-decoration: none; 
            transition: var(--transicao-suave); 
        }
        footer a:hover { 
            color: var(--cor-primaria-hover); 
            transform: translateY(-2px);
        }

        ::-webkit-scrollbar { 
            width: 10px; 
        }
        ::-webkit-scrollbar-track { 
            background: #f1f1f1; 
        }
        ::-webkit-scrollbar-thumb { 
            background: var(--gradiente-rosa); 
            border-radius: 5px; 
        }
        ::-webkit-scrollbar-thumb:hover { 
            background: var(--cor-primaria-hover); 
        }

        @keyframes fadeInUp { 
            from { opacity: 0; transform: translateY(30px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        .animate-fade-in { 
            animation: fadeInUp 0.7s ease forwards; 
        }

        @media (max-width: 992px) {
            .topbar { 
                flex-direction: column; 
                gap: 15px; 
                padding: 15px 20px; 
            }
            nav { 
                width: 100%; 
                justify-content: center; 
                gap: 8px; 
                flex-wrap: wrap; 
            }
            .container { 
                padding: 40px 20px; 
            }
            .section-title { 
                font-size: 32px; 
            }
            .logo-img { 
                height: 55px; 
            }
        }

        @media (max-width: 576px) {
            .section-title {
                font-size: 26px;
            }
            .card-form {
                padding: 25px;
            }
        }

        img { 
            transition: var(--transicao-suave); 
        }
        ::selection { 
            background: var(--cor-primaria); 
            color: white; 
        }
        .navbar-brand {
            color: #d66d7f !important;
        }
        .text-primary {
            color: #d66d7f !important;
        }

        /* === NAVBAR — limpa, refinada, com hover animation === */
        .navbar { padding-top: 12px !important; padding-bottom: 12px !important; }

        .navbar-nav { gap: 4px; align-items: center; }

        .navbar-nav .nav-link {
            color: #1f2937 !important;
            font-size: 15px;
            font-weight: 500;
            padding: 8px 14px !important;
            position: relative;
            transition: color 0.2s;
        }

        /* Linha animada por baixo dos links — aparece no hover/active */
        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            left: 14px; right: 14px; bottom: 4px;
            height: 2px;
            border-radius: 2px;
            background: #bf5b6d;
            transform: scaleX(0);
            transform-origin: center;
            transition: transform 0.25s ease;
        }
        .navbar-nav .nav-link:hover {
            color: #bf5b6d !important;
        }
        .navbar-nav .nav-link:hover::after {
            transform: scaleX(1);
        }
        .navbar-nav .nav-link.active {
            color: #bf5b6d !important;
            font-weight: 600;
        }
        .navbar-nav .nav-link.active::after {
            transform: scaleX(1);
        }

        /* Sociais não têm sublinhado e ficam alinhados */
        .navbar-nav .facebook-link::after,
        .navbar-nav .instagram-link::after { display: none; }
        .navbar-nav .facebook-link,
        .navbar-nav .instagram-link {
            padding: 8px 10px !important;
            color: #6b7280 !important;
        }
        .navbar-nav .facebook-link:hover { color: #1877F2 !important; }
        .navbar-nav .instagram-link:hover { color: #e4405f !important; }

        /* === Botão "Pedir Orçamento" — pílula refinada === */
        .btn-pedir-orcamento {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #bf5b6d;
            color: #fff !important;
            padding: 10px 22px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s;
            border: 1px solid #bf5b6d;
            line-height: 1.2;
            box-shadow: 0 4px 14px rgba(194, 93, 114, 0.25);
            white-space: nowrap;
        }
        .btn-pedir-orcamento:hover {
            background: #ad4d61;
            border-color: #ad4d61;
            color: #fff !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(194, 93, 114, 0.35);
        }
        .btn-pedir-orcamento i { font-size: 12px; }

        /* Dropdown da conta — limpo */
        .navbar-nav .dropdown-menu {
            border: 1px solid #ececea;
            border-radius: 10px;
            box-shadow: 0 16px 36px rgba(0,0,0,0.08);
            padding: 8px;
            margin-top: 8px !important;
        }
        .navbar-nav .dropdown-item {
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 14px;
            color: #1f2937;
        }
        .navbar-nav .dropdown-item:hover {
            background: #fff8fa;
            color: #bf5b6d;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top py-2">
    <div class="container">
        <a href="index.php" class="navbar-brand d-flex align-items-center" style="transition: opacity 0.2s;" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
            <img src="imagens/logo_sylviartes.png" alt="Logo" class="me-2" style="height: 44px; border-radius: 10px;" onerror="this.style.display='none';">
            <span class="fw-bold text-primary" style="font-size: 1.35rem; font-family: 'Playfair Display', serif; letter-spacing: 0.3px;">SylviArtes</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">

            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">Início</a>
                </li>
                <li class="nav-item">
                    <a href="catalogo.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'catalogo.php' ? 'active' : ''; ?>">Portfólio</a>
                </li>
                <li class="nav-item d-flex align-items-center" style="margin: 0 6px 0 10px;">
                    <a href="pedir-orcamento.php" class="btn-pedir-orcamento">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Pedir Orçamento
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sobre.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'sobre.php' ? 'active' : ''; ?>">Sobre</a>
                </li>
                <li class="nav-item">
                    <a href="contacto.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'contacto.php' ? 'active' : ''; ?>">Contacto</a>
                </li>
<?php // Carrinho removido — modelo é por orçamento personalizado. ?>

                <?php // === ZONA DE LOGIN/CONTA — muda conforme estado da sessão === ?>
                <?php if (isset($_SESSION['cliente_id'])): ?>
                    <?php // Cliente AUTENTICADO: dropdown com opções da conta ?>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars(explode(' ', $_SESSION['cliente_nome'] ?? '')[0] ?? 'Conta'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="cliente/index.php">Minha Conta</a></li>
                            <li><a class="dropdown-item" href="cliente/encomendas.php">As minhas encomendas</a></li>
                            <li><a class="dropdown-item" href="cliente/perfil.php">Os meus dados</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="cliente/logout.php">Sair</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <?php // Visitante anónimo: link simples "Entrar" ?>
                    <li class="nav-item">
                        <a href="cliente/login.php" class="nav-link">
                            <i class="fas fa-user"></i> Entrar
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a href="https://www.facebook.com/people/SylviArtes/61565302160232/" target="_blank" rel="noopener noreferrer" class="nav-link facebook-link" title="Siga-nos no Facebook">
                        <i class="fab fa-facebook fa-lg"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="https://www.instagram.com/sylvi.artes/" target="_blank" rel="noopener noreferrer" class="nav-link instagram-link" title="Siga-nos no Instagram">
                        <i class="fab fa-instagram fa-lg"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container">

