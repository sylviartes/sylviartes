<?php
/**
 * =============================================================================
 *  LOGIN DE CLIENTE
 * =============================================================================
 *
 *  Página onde os clientes inserem email + password para entrar na sua conta.
 *  Após login bem-sucedido, gravam-se na sessão:
 *      $_SESSION['cliente_id']    → ID do utilizador na tabela `utilizador`
 *      $_SESSION['cliente_nome']  → Nome a mostrar no menu
 *
 *  Suporta um parâmetro ?redirect=... para mandar o utilizador de volta à
 *  página de onde veio (ex: do checkout). Por segurança, só são aceites
 *  caminhos relativos - protege contra "open redirect" attacks.
 * =============================================================================
 */

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

// Se já está autenticado, não faz sentido mostrar o form de login → dashboard
if (isset($_SESSION['cliente_id'])) {
    header("Location: index.php");
    exit;
}

$erro = "";
$emailPosto = ""; // mantém o email no form se a tentativa falhar

// Form submetido?
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $emailPosto = $email;

    if ($email !== '' && $password !== '') {
        // Procura o utilizador (apenas com nível 'cliente' - admins entram noutro lado)
        $stmt = $conn->prepare("
            SELECT id, nome, password
            FROM utilizador
            WHERE email = :email AND nivel_acesso = 'cliente'
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Verifica com hash bcrypt (única forma aceite)
            if (password_verify($password, $row['password'])) {
                // ✓ Login bem-sucedido - regenera sessão e grava na sessão
                session_regenerate_id(true);
                $_SESSION['cliente_id'] = $row['id'];
                $_SESSION['cliente_nome'] = $row['nome'];

                // Para onde redirecionar após login:
                $redirect = $_GET['redirect'] ?? 'index.php';

                // SEGURANÇA: só aceita caminhos relativos.
                // Rejeita URLs absolutas e caminhos começados por // (open redirect).
                if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?\=\&]+$/', $redirect)
                    || str_starts_with($redirect, '//')
                    || str_contains($redirect, '://')) {
                    $redirect = 'index.php';
                }
                header("Location: " . $redirect);
                exit;
            } else {
                $erro = "Email ou password incorretos.";
            }
        } else {
            $erro = "Email ou password incorretos.";
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
    <title>Entrar - SylviArtes</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../imagens/logo_sylviartes.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cliente_style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <a href="../index.php" class="auth-logo">SylviArtes</a>
            <h2>Entrar na minha conta</h2>
            <p class="auth-subtitle">Aceda à sua área pessoal</p>

            <?php if ($erro): ?>
                <div class="auth-erro"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($emailPosto); ?>" required autofocus>

                <label>Password</label>
                <input type="password" name="password" required>

                <button type="submit" class="auth-btn">Entrar</button>
            </form>

            <p class="auth-foot">
                <a href="esqueci_password.php">Esqueci-me da password</a>
            </p>

            <p class="auth-foot">
                Ainda não tem conta? <a href="registo.php">Criar conta</a>
            </p>
            <p class="auth-foot">
                <a href="../index.php">← Voltar ao site</a>
            </p>
        </div>
    </div>
</body>
</html>
