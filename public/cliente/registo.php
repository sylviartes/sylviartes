<?php
/**
 * =============================================================================
 *  REGISTO DE NOVO CLIENTE
 * =============================================================================
 *
 *  Form para criar uma conta nova. Recolhe todos os dados necessários para
 *  futuras compras (nome, contacto, morada) e a password.
 *
 *  Validações (regex idênticas ao pedido.php para manter consistência):
 *    - Nome: pelo menos 2 palavras com maiúsculas iniciais
 *    - Email: formato válido
 *    - Telefone PT: começa por 2/9 com 9 dígitos, ou +351 prefixo
 *    - Código Postal PT: NNNN-NNN
 *    - Password: mínimo 6 caracteres + confirmação
 *
 *  Após registo bem-sucedido, faz login automático e redireciona para o
 *  dashboard com ?bemvindo=1 para mostrar mensagem de boas-vindas.
 * =============================================================================
 */

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

// Já logado → não faz sentido mostrar form de registo
if (isset($_SESSION['cliente_id'])) {
    header("Location: index.php");
    exit;
}

// === REGEX DE VALIDAÇÃO (fornecidas pelo professor; iguais a pedido.php) ===
$regexPostal   = "/^[1-9]\d{3}(-\d{3})?$/";                                                   // 4000-123
$regexTelefone = "/^(\+351)?(2\d{8}|9[1236]\d{7})$/";                                         // +351 912345678
$regexEmail    = "/^[a-zA-Z0-9\-]+(\.[a-zA-Z0-9]+)*@[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)*$/";          // email simples
$regexNome     = "/(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)+$)|(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)*( ((de)|(dos)|(da)|(do Ó)))?( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)+$)/u";
$regexMorada   = "/^\S+( \S+)*$/";

$erros = [];
// Array com todos os campos do form (vazios por defeito; preenchidos no POST)
$dados = [
    'nome' => '', 'email' => '', 'telefone' => '',
    'morada' => '', 'codigo_postal' => '', 'localidade' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lê e limpa todos os campos
    foreach ($dados as $k => $_) {
        $dados[$k] = trim($_POST[$k] ?? '');
    }
    $password  = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    // === VALIDAÇÕES ===
    if (!preg_match($regexNome, $dados['nome'])) {
        $erros[] = "Introduza um nome válido (Ex: Maria Silva).";
    }
    if (!preg_match($regexEmail, $dados['email'])) {
        $erros[] = "Email inválido.";
    }
    // Remove espaços do telefone antes de validar (ex: "912 345 678" → "912345678")
    $telLimpo = preg_replace('/\s+/', '', $dados['telefone']);
    $dados['telefone'] = $telLimpo;
    if (!preg_match($regexTelefone, $telLimpo)) {
        $erros[] = "Telefone inválido (Ex: 912345678).";
    }
    if (!preg_match($regexMorada, $dados['morada'])) {
        $erros[] = "Morada inválida.";
    }
    if (!preg_match($regexPostal, $dados['codigo_postal'])) {
        $erros[] = "Código Postal inválido (Ex: 4000-123).";
    }
    if ($dados['localidade'] === '') {
        $erros[] = "Indique a localidade.";
    }
    if (strlen($password) < 6) {
        $erros[] = "A password tem de ter pelo menos 6 caracteres.";
    }
    if ($password !== $confirmar) {
        $erros[] = "As passwords não coincidem.";
    }

    // Se passou todas as validações, tenta criar a conta
    if (empty($erros)) {
        // Verifica se já existe conta com este email
        // (a coluna `email` na tabela tem UNIQUE, mas verificamos antes para
        // dar mensagem amigável em vez de erro SQL feio)
        $stmt = $conn->prepare("SELECT id, password FROM utilizador WHERE email = ?");
        $stmt->execute([$dados['email']]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $erros[] = "Já existe uma conta com este email. <a href='login.php'>Entrar</a>";
        } else {
            // password_hash usa bcrypt por defeito (PASSWORD_DEFAULT) - algoritmo seguro
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmtIns = $conn->prepare("
                INSERT INTO utilizador
                  (nome, email, password, telefone, morada, codigo_postal, localidade, nivel_acesso)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'cliente')
            ");
            $stmtIns->execute([
                $dados['nome'], $dados['email'], $hash,
                $dados['telefone'], $dados['morada'],
                $dados['codigo_postal'], $dados['localidade']
            ]);

            // Login automático imediatamente após registo
            $_SESSION['cliente_id'] = $conn->lastInsertId();
            $_SESSION['cliente_nome'] = $dados['nome'];

            header("Location: index.php?bemvindo=1");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta - SylviArtes</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../imagens/logo_sylviartes.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cliente_style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box" style="max-width:560px;">
            <a href="../index.php" class="auth-logo">SylviArtes</a>
            <h2>Criar conta</h2>
            <p class="auth-subtitle">Guarde os seus dados para futuras compras mais rápidas</p>

            <?php if (!empty($erros)): ?>
                <div class="auth-erro">
                    <strong>Verifique os campos:</strong>
                    <ul>
                        <?php foreach ($erros as $e): ?>
                            <li><?php echo $e; /* $erros pode ter HTML (link login) */ ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="form-grid">
                    <div class="full">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($dados['nome']); ?>" required>
                    </div>

                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($dados['email']); ?>" required>
                    </div>

                    <div>
                        <label>Telemóvel</label>
                        <input type="text" name="telefone" placeholder="912345678" value="<?php echo htmlspecialchars($dados['telefone']); ?>" required>
                    </div>

                    <div class="full">
                        <label>Morada</label>
                        <input type="text" name="morada" value="<?php echo htmlspecialchars($dados['morada']); ?>" required>
                    </div>

                    <div>
                        <label>Código Postal</label>
                        <input type="text" name="codigo_postal" placeholder="4000-123" value="<?php echo htmlspecialchars($dados['codigo_postal']); ?>" required>
                    </div>

                    <div>
                        <label>Localidade</label>
                        <input type="text" name="localidade" value="<?php echo htmlspecialchars($dados['localidade']); ?>" required>
                    </div>

                    <div>
                        <label>Password (mín. 6)</label>
                        <input type="password" name="password" required>
                    </div>

                    <div>
                        <label>Confirmar Password</label>
                        <input type="password" name="confirmar" required>
                    </div>
                </div>

                <button type="submit" class="auth-btn">Criar conta</button>
            </form>

            <p class="auth-foot">
                Já tem conta? <a href="login.php">Entrar</a>
            </p>
        </div>
    </div>
</body>
</html>
