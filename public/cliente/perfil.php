<?php
/**
 * =============================================================================
 *  PERFIL DO CLIENTE — Editar dados pessoais e password
 * =============================================================================
 *
 *  Permite ao cliente alterar:
 *    1. Dados pessoais: nome, telefone, morada, código postal, localidade
 *       (o email NÃO se pode mudar — é a chave de identificação da conta)
 *    2. Password: pede a password atual + nova + confirmação para evitar
 *       que alguém mude a password se o computador ficar desbloqueado
 *
 *  Usa um campo oculto "accao" (dados ou password) para distinguir qual
 *  dos dois forms foi submetido.
 * =============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';

$clienteId = $_SESSION['cliente_id'];

// Mesmas regex usadas no registo e no checkout (consistência total)
$regexPostal   = "/^[1-9]\d{3}(-\d{3})?$/";
$regexTelefone = "/^(\+351)?(2\d{8}|9[1236]\d{7})$/";
$regexNome = "/(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)+$)|(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)*( ((de)|(dos)|(da)|(do Ó)))?( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)+$)/u";
$regexMorada = "/^\S+( \S+)*$/";

$erros = [];
$sucesso = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // O campo oculto "accao" diz-nos qual dos forms foi submetido
    $accao = $_POST['accao'] ?? 'dados';

    // === FORM 1: Atualizar dados pessoais ===
    if ($accao === 'dados') {
        $nome = trim($_POST['nome'] ?? '');
        $telefone = preg_replace('/\s+/', '', trim($_POST['telefone'] ?? ''));
        $morada = trim($_POST['morada'] ?? '');
        $codigo_postal = trim($_POST['codigo_postal'] ?? '');
        $localidade = trim($_POST['localidade'] ?? '');

        if (!preg_match($regexNome, $nome)) $erros[] = "Nome inválido.";
        if (!preg_match($regexTelefone, $telefone)) $erros[] = "Telefone inválido.";
        if (!preg_match($regexMorada, $morada)) $erros[] = "Morada inválida.";
        if (!preg_match($regexPostal, $codigo_postal)) $erros[] = "Código Postal inválido.";
        if ($localidade === '') $erros[] = "Localidade obrigatória.";

        if (empty($erros)) {
            $stmt = $conn->prepare("
                UPDATE utilizador
                SET nome=?, telefone=?, morada=?, codigo_postal=?, localidade=?
                WHERE id=?
            ");
            $stmt->execute([$nome, $telefone, $morada, $codigo_postal, $localidade, $clienteId]);
            // Atualiza também o nome na sessão para o menu mostrar imediatamente
            $_SESSION['cliente_nome'] = $nome;
            $sucesso = "Dados atualizados com sucesso.";
        }
    }
    // === FORM 2: Alterar password ===
    elseif ($accao === 'password') {
        $atual = $_POST['password_atual'] ?? '';
        $nova = $_POST['password_nova'] ?? '';
        $conf = $_POST['password_conf'] ?? '';

        // Vai buscar o hash atual para comparar
        $stmt = $conn->prepare("SELECT password FROM utilizador WHERE id=?");
        $stmt->execute([$clienteId]);
        $hashAtual = $stmt->fetchColumn();

        // Aceita hash bcrypt (normal) ou texto plano (fallback para contas antigas)
        $okAtual = password_verify($atual, $hashAtual) || $atual === $hashAtual;
        if (!$okAtual) $erros[] = "Password atual incorreta.";
        if (strlen($nova) < 6) $erros[] = "Nova password tem de ter pelo menos 6 caracteres.";
        if ($nova !== $conf) $erros[] = "A confirmação não coincide.";

        if (empty($erros)) {
            $novoHash = password_hash($nova, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE utilizador SET password=? WHERE id=?");
            $stmt->execute([$novoHash, $clienteId]);
            $sucesso = "Password alterada com sucesso.";
        }
    }
}

// Recarrega os dados atuais da BD para os mostrar no form
// (importante chamar depois do UPDATE para refletir as alterações)
$stmt = $conn->prepare("SELECT nome, email, telefone, morada, codigo_postal, localidade FROM utilizador WHERE id=?");
$stmt->execute([$clienteId]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Os meus dados — SylviArtes</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cliente_style.css">
</head>
<body>
    <div class="cli-wrapper">
        <a href="index.php" class="cli-back">← Minha Conta</a>

        <?php if ($sucesso): ?>
            <div class="auth-sucesso"><?php echo htmlspecialchars($sucesso); ?></div>
        <?php endif; ?>
        <?php if (!empty($erros)): ?>
            <div class="auth-erro">
                <ul>
                    <?php foreach ($erros as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="cli-section">
            <h2>Os meus dados</h2>
            <form method="POST" novalidate>
                <input type="hidden" name="accao" value="dados">
                <div class="form-grid">
                    <div class="full">
                        <label>Email (não editável)</label>
                        <input type="email" value="<?php echo htmlspecialchars($c['email']); ?>" disabled>
                    </div>
                    <div class="full">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($c['nome']); ?>" required>
                    </div>
                    <div>
                        <label>Telemóvel</label>
                        <input type="text" name="telefone" value="<?php echo htmlspecialchars($c['telefone']); ?>" required>
                    </div>
                    <div>
                        <label>Código Postal</label>
                        <input type="text" name="codigo_postal" value="<?php echo htmlspecialchars($c['codigo_postal']); ?>" required>
                    </div>
                    <div class="full">
                        <label>Morada</label>
                        <input type="text" name="morada" value="<?php echo htmlspecialchars($c['morada']); ?>" required>
                    </div>
                    <div class="full">
                        <label>Localidade</label>
                        <input type="text" name="localidade" value="<?php echo htmlspecialchars($c['localidade']); ?>" required>
                    </div>
                </div>
                <button type="submit" class="auth-btn">Guardar alterações</button>
            </form>
        </div>

        <div class="cli-section">
            <h2>Alterar password</h2>
            <form method="POST" novalidate>
                <input type="hidden" name="accao" value="password">
                <div class="form-grid">
                    <div class="full">
                        <label>Password atual</label>
                        <input type="password" name="password_atual" required>
                    </div>
                    <div>
                        <label>Nova password (mín. 6)</label>
                        <input type="password" name="password_nova" required>
                    </div>
                    <div>
                        <label>Confirmar nova password</label>
                        <input type="password" name="password_conf" required>
                    </div>
                </div>
                <button type="submit" class="auth-btn">Alterar password</button>
            </form>
        </div>
    </div>
</body>
</html>
