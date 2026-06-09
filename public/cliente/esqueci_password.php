<?php
/**
 * =============================================================================
 *  ESQUECI-ME DA PASSWORD - Pedido de Recuperação
 * =============================================================================
 *
 *  Cliente introduz o seu email; se existir conta:
 *    1. Geramos um token aleatório seguro (32 bytes hex = 64 chars)
 *    2. Guardamos na tabela password_reset com expiração de 1 hora
 *    3. Enviamos email com link para nova_password.php?token=...
 *
 *  POR SEGURANÇA: respondemos sempre com a mesma mensagem (mesmo se o email
 *  não existir) - para não permitir descobrir que emails têm conta no site.
 * =============================================================================
 */

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/email.php';

// Já logado → não faz sentido pedir reset
if (isset($_SESSION['cliente_id'])) {
    header("Location: index.php");
    exit;
}

$mensagem = "";
$tipo = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Introduza um email válido.";
        $tipo = "erro";
    } else {
        // Procura utilizador (cliente, não admin)
        $stmt = $conn->prepare("SELECT id, nome FROM utilizador WHERE email = ? AND nivel_acesso = 'cliente'");
        $stmt->execute([$email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($u) {
            // Gera token aleatório criptograficamente seguro
            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Apaga tokens antigos não usados deste utilizador
            $stmt = $conn->prepare("DELETE FROM password_reset WHERE utilizador_id = ? AND usado = 0");
            $stmt->execute([$u['id']]);

            // Cria novo token
            $stmt = $conn->prepare("
                INSERT INTO password_reset (utilizador_id, token, data_expiracao)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$u['id'], $token, $expira]);

            // Constrói URL completa (assume mesmo host que esta página)
            $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $url = $protocolo . "://" . $_SERVER['HTTP_HOST']
                 . dirname($_SERVER['PHP_SELF']) . "/nova_password.php?token=" . $token;

            // === Composição do email HTML (mesmo estilo polido dos outros emails) ===
            $primeiroNome = htmlspecialchars(explode(' ', $u['nome'])[0] ?? 'Cliente');
            $corpo = "
                <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:30px; background:#fff;'>
                    <div style='text-align:center; margin-bottom:30px;'>
                        <h1 style='color:#d66d7f; font-family:Georgia,serif; margin:0;'>SylviArtes</h1>
                        <p style='color:#888; margin:4px 0 0; font-size:13px;'>Costura Criativa &middot; Bordados Personalizados</p>
                    </div>

                    <h2 style='color:#2d3436; margin:0 0 16px;'>Recuperação de password</h2>

                    <p style='color:#555; line-height:1.6;'>Olá, " . $primeiroNome . "!</p>
                    <p style='color:#555; line-height:1.6;'>
                        Recebemos um pedido para redefinir a password da sua conta.
                        Clique no botão abaixo para criar uma nova. Este link é válido durante <strong>1 hora</strong>.
                    </p>

                    <p style='text-align:center; margin:35px 0;'>
                        <a href='" . htmlspecialchars($url) . "'
                           style='background:#d66d7f; color:#fff; padding:16px 40px; border-radius:999px;
                                  text-decoration:none; font-weight:bold; font-size:16px; display:inline-block;'>
                            Criar nova password
                        </a>
                    </p>

                    <p style='color:#888; font-size:13px; line-height:1.6;'>
                        Se não foi você que fez este pedido, ignore este email com segurança.
                        A sua password permanece inalterada.
                    </p>

                    <hr style='border:none; border-top:1px solid #eee; margin:30px 0;'>
                    <p style='color:#999; font-size:12px; text-align:center;'>
                        SylviArtes &middot; Costura Criativa
                    </p>
                </div>
            ";

            enviar_email($email, "Recuperação de password - SylviArtes", $corpo);
        }

        // Mensagem genérica (NÃO revela se o email existe ou não)
        $mensagem = "Se o email estiver registado, vai receber instruções para recuperar a password.";
        $tipo = "ok";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci-me da password - SylviArtes</title>
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
            <h2>Esqueceu-se da password?</h2>
            <p class="auth-subtitle">Indique o seu email e enviamos-lhe um link para criar uma nova.</p>

            <?php if ($mensagem): ?>
                <div class="<?= $tipo === 'ok' ? 'auth-sucesso' : 'auth-erro' ?>">
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <label>Email</label>
                <input type="email" name="email" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

                <button type="submit" class="auth-btn">Enviar link de recuperação</button>
            </form>

            <p class="auth-foot">
                <a href="login.php">← Voltar ao login</a>
            </p>
        </div>
    </div>
</body>
</html>
