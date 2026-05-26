<?php
/**
 * =============================================================================
 *  LOGIN DO ADMIN
 * =============================================================================
 *
 *  Página onde os administradores entram para gerir o site (produtos, categorias,
 *  encomendas, etc.). A diferença para o login de cliente é apenas que aqui só
 *  são aceites utilizadores com `nivel_acesso = 'admin'`.
 *
 *  Após sucesso, grava na sessão:
 *      $_SESSION['admin_id']    → identificador
 *      $_SESSION['admin_nome']  → nome a mostrar na sidebar
 * =============================================================================
 */

session_start();
require_once __DIR__ . '/../../config/db.php';

// Já logado → vai direto para o dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email !== '' && $password !== '') {
        // Procura utilizador APENAS com nível admin
        // (clientes nunca entram por aqui, mesmo com email/password corretos)
        $stmt = $conn->prepare("
            SELECT id, nome, password
            FROM utilizador
            WHERE email = :email AND nivel_acesso = 'admin'
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $email
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Tenta verificar com hash bcrypt (forma segura)
            if (password_verify($password, $row['password'])) {
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_nome'] = $row['nome'];
                header("Location: index.php");
                exit;
            } else {
                // Fallback: comparar texto plano (para contas antigas em que a
                // password ainda não foi convertida para hash). NÃO usar em
                // novos utilizadores — só compatibilidade.
                if ($password === $row['password']) {
                    $_SESSION['admin_id'] = $row['id'];
                    $_SESSION['admin_nome'] = $row['nome'];
                    header("Location: index.php");
                    exit;
                } else {
                    $erro = "Password incorreta.";
                }
            }
        } else {
            // Mensagem deliberadamente vaga: não dizemos se foi o email ou a
            // password que está errado — protege contra enumeração de emails.
            $erro = "Email não encontrado ou sem permissão.";
        }
    } else {
        $erro = "Preencha o email e a password.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin — SylviArtes</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background:
                radial-gradient(ellipse at top left, #fff8fa 0%, transparent 60%),
                radial-gradient(ellipse at bottom right, #fdf0f4 0%, transparent 60%),
                #fafafa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .admin-login-card {
            background: #fff;
            padding: 50px 40px;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(214, 109, 127, 0.15);
            width: 100%;
            max-width: 420px;
            border: 1px solid #f0e3e7;
            text-align: center;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(15px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .admin-login-icone {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #d66d7f, #e8a4b0);
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-size: 28px;
            margin-bottom: 16px;
            box-shadow: 0 12px 30px rgba(214, 109, 127, 0.30);
        }
        .admin-login-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            color: #2d3436;
            margin: 0 0 4px;
        }
        .admin-login-card .subtitulo {
            color: #888;
            font-size: 13px;
            margin-bottom: 28px;
        }
        .admin-login-card form {
            text-align: left;
        }
        .admin-login-card label {
            display: block;
            font-size: 12px;
            color: #666;
            margin: 12px 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        .admin-login-card input {
            width: 100%;
            padding: 13px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            background: #fff;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .admin-login-card input:focus {
            outline: none;
            border-color: #d66d7f;
            box-shadow: 0 0 0 3px rgba(214, 109, 127, 0.10);
        }
        .admin-login-card button {
            width: 100%;
            margin-top: 20px;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #c95f7a, #d6788b);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            letter-spacing: 0.3px;
        }
        .admin-login-card button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(201, 95, 122, 0.30);
        }
        .admin-login-erro {
            background: #fef2f2;
            color: #b91c1c;
            border-left: 3px solid #ef4444;
            padding: 11px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 13.5px;
            text-align: left;
        }
        .admin-login-voltar {
            display: inline-block;
            margin-top: 24px;
            color: #999;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }
        .admin-login-voltar:hover {
            color: #d66d7f;
        }
    </style>
</head>
<body>
    <div class="admin-login-card">
        <div class="admin-login-icone">
            <i class="fas fa-lock"></i>
        </div>
        <h2>SylviArtes</h2>
        <div class="subtitulo">Painel de Administração</div>

        <?php if ($erro): ?>
            <div class="admin-login-erro">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" required autofocus
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>

        <a href="../index.php" class="admin-login-voltar">
            <i class="fas fa-arrow-left"></i> Voltar ao site
        </a>
    </div>
</body>
</html>