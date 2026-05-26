<?php
/**
 * =============================================================================
 *  NOVA PASSWORD — Validação de Token + Definir Password
 * =============================================================================
 *
 *  Página acedida pelo link enviado no email. Validações:
 *    1. Token tem de existir, não estar usado, e não ter expirado
 *    2. Nova password com mín. 6 caracteres + confirmação
 *
 *  Após sucesso:
 *    - Atualiza utilizador.password com novo hash
 *    - Marca token como usado
 *    - Faz login automático e manda para dashboard
 * =============================================================================
 */

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$mensagem = "";
$tipo = "";
$tokenValido = false;
$utilizadorInfo = null;

// === Validar o token ===
if ($token !== '') {
    $stmt = $conn->prepare("
        SELECT pr.id, pr.utilizador_id, pr.data_expiracao, pr.usado,
               u.nome, u.email
        FROM password_reset pr
        JOIN utilizador u ON u.id = pr.utilizador_id
        WHERE pr.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !$row['usado'] && strtotime($row['data_expiracao']) > time()) {
        $tokenValido = true;
        $utilizadorInfo = $row;
    } else {
        $mensagem = "Este link expirou ou já foi usado. Peça uma nova recuperação.";
        $tipo = "erro";
    }
} else {
    $mensagem = "Link inválido.";
    $tipo = "erro";
}

// === Processar nova password ===
if ($tokenValido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova = $_POST['nova'] ?? '';
    $conf = $_POST['conf'] ?? '';

    if (strlen($nova) < 6) {
        $mensagem = "Password tem de ter pelo menos 6 caracteres.";
        $tipo = "erro";
    } elseif ($nova !== $conf) {
        $mensagem = "As passwords não coincidem.";
        $tipo = "erro";
    } else {
        // Hash + UPDATE + marcar token como usado (numa transação)
        $hash = password_hash($nova, PASSWORD_DEFAULT);
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("UPDATE utilizador SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $utilizadorInfo['utilizador_id']]);

            $stmt = $conn->prepare("UPDATE password_reset SET usado = 1 WHERE id = ?");
            $stmt->execute([$utilizadorInfo['id']]);

            $conn->commit();

            // Login automático
            $_SESSION['cliente_id']   = $utilizadorInfo['utilizador_id'];
            $_SESSION['cliente_nome'] = $utilizadorInfo['nome'];

            header("Location: index.php?pwd_resetada=1");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $mensagem = "Erro ao gravar a nova password: " . $e->getMessage();
            $tipo = "erro";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova password — SylviArtes</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cliente_style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <a href="../index.php" class="auth-logo">SylviArtes</a>
            <h2>Criar nova password</h2>

            <?php if ($mensagem): ?>
                <div class="<?= $tipo === 'ok' ? 'auth-sucesso' : 'auth-erro' ?>">
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <?php if ($tokenValido): ?>
                <p class="auth-subtitle">
                    Conta: <strong><?= htmlspecialchars($utilizadorInfo['email']) ?></strong>
                </p>

                <form method="POST" novalidate>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <label>Nova password (mín. 6 caracteres)</label>
                    <input type="password" name="nova" required autofocus>

                    <label>Confirmar password</label>
                    <input type="password" name="conf" required>

                    <button type="submit" class="auth-btn">Gravar nova password</button>
                </form>
            <?php else: ?>
                <p class="auth-foot">
                    <a href="esqueci_password.php">← Pedir novo link de recuperação</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
