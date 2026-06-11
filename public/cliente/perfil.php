<?php
/**
 * =============================================================================
 *  PERFIL DO CLIENTE - Editar dados pessoais e password
 * =============================================================================
 *  Permite ao cliente alterar:
 *    1. Dados pessoais: nome, telefone, morada, código postal, localidade
 *       (o email NÃO se pode mudar - é a chave de identificação da conta)
 *    2. Password: exige a password atual antes de permitir a alteração
 * =============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';

$clienteId = $_SESSION['cliente_id'];

// Regex de validação - iguais ao registo e ao checkout (consistência total)
$regexPostal   = "/^[1-9]\d{3}(-\d{3})?$/";
$regexTelefone = "/^(\+351)?(2\d{8}|9[1236]\d{7})$/";
$regexNome     = "/(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)+$)|(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)*( ((de)|(dos)|(da)|(do Ó)))?( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)+$)/u";
$regexMorada   = "/^\S+( \S+)*$/";

$erros  = [];
$sucesso = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    // Campo oculto "accao" indica qual dos dois forms foi submetido
    $accao = $_POST['accao'] ?? 'dados';

    // === FORM 1: Dados pessoais ===
    if ($accao === 'dados') {
        $nome          = trim($_POST['nome'] ?? '');
        $telefone      = preg_replace('/\s+/', '', trim($_POST['telefone'] ?? ''));
        $morada        = trim($_POST['morada'] ?? '');
        $codigo_postal = trim($_POST['codigo_postal'] ?? '');
        $localidade    = trim($_POST['localidade'] ?? '');

        if (!preg_match($regexNome, $nome))         $erros[] = "Nome inválido.";
        if (!preg_match($regexTelefone, $telefone)) $erros[] = "Telefone inválido.";
        if (!preg_match($regexMorada, $morada))     $erros[] = "Morada inválida.";
        if (!preg_match($regexPostal, $codigo_postal)) $erros[] = "Código Postal inválido.";
        if ($localidade === '')                      $erros[] = "Localidade obrigatória.";

        if (empty($erros)) {
            $stmt = $conn->prepare("
                UPDATE utilizador
                SET nome=?, telefone=?, morada=?, codigo_postal=?, localidade=?
                WHERE id=?
            ");
            $stmt->execute([$nome, $telefone, $morada, $codigo_postal, $localidade, $clienteId]);
            // Atualiza o nome na sessão para o menu do topo refletir imediatamente
            $_SESSION['cliente_nome'] = $nome;
            $sucesso = "Dados atualizados com sucesso.";
        }
    }
    // === FORM 2: Alterar password ===
    elseif ($accao === 'password') {
        $atual = $_POST['password_atual'] ?? '';
        $nova  = $_POST['password_nova']  ?? '';
        $conf  = $_POST['password_conf']  ?? '';

        $stmt = $conn->prepare("SELECT password FROM utilizador WHERE id=?");
        $stmt->execute([$clienteId]);
        $hashAtual = $stmt->fetchColumn();

        if (!password_verify($atual, $hashAtual)) $erros[] = "Password atual incorreta.";
        if (strlen($nova) < 6)  $erros[] = "Nova password tem de ter pelo menos 6 caracteres.";
        if ($nova !== $conf)    $erros[] = "A confirmação não coincide.";

        if (empty($erros)) {
            $stmt = $conn->prepare("UPDATE utilizador SET password=? WHERE id=?");
            $stmt->execute([password_hash($nova, PASSWORD_DEFAULT), $clienteId]);
            $sucesso = "Password alterada com sucesso.";
        }
    }
}

// Recarrega os dados (depois do UPDATE para mostrar valores atualizados)
$stmt = $conn->prepare("SELECT nome, email, telefone, morada, codigo_postal, localidade FROM utilizador WHERE id=?");
$stmt->execute([$clienteId]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Os meus dados - SylviArtes</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../imagens/logo_sylviartes.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cliente_style.css?v=<?= @filemtime(__DIR__ . '/cliente_style.css') ?: 1 ?>">
    <style>
    /* ================================================================
       OS MEUS DADOS - estilos específicos desta página
       ================================================================ */

    body { background: #f4f6f9; }

    /* === HEADER COM GRADIENTE === */
    .perfil-topo {
        background: linear-gradient(135deg, #d66d7f 0%, #bf5b6d 100%);
        padding: 20px 24px 56px;
        position: relative;
    }
    .perfil-voltar {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 24px;
        transition: color 0.2s;
    }
    .perfil-voltar:hover { color: #fff; }
    .perfil-topo-info {
        display: flex;
        align-items: center;
        gap: 18px;
        max-width: 800px;
        margin: 0 auto;
    }
    .perfil-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: rgba(255,255,255,0.20);
        border: 2px solid rgba(255,255,255,0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        color: #fff;
        flex-shrink: 0;
    }
    .perfil-topo-info h1 {
        font-family: 'Playfair Display', serif;
        font-size: 1.75rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 4px;
    }
    .perfil-topo-info p {
        color: rgba(255,255,255,0.82);
        font-size: 14px;
        margin: 0;
    }

    /* === ÁREA DE CONTEÚDO === */
    .perfil-conteudo {
        max-width: 800px;
        margin: -28px auto 0;
        padding: 0 20px 60px;
        position: relative;
        z-index: 1;
    }

    /* === ALERTAS === */
    .perfil-alerta {
        border-radius: 12px;
        padding: 14px 18px;
        margin-bottom: 18px;
        font-size: 14px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .perfil-alerta.erro   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .perfil-alerta.sucesso{ background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .perfil-alerta i { flex-shrink: 0; margin-top: 2px; }
    .perfil-alerta ul { margin: 6px 0 0 16px; padding: 0; }

    /* === CARDS DE SECÇÃO === */
    .perfil-card {
        background: #fff;
        border-radius: 18px;
        border: 1px solid #ede7ea;
        box-shadow: 0 4px 20px rgba(0,0,0,0.055);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .perfil-card-cabecalho {
        padding: 20px 28px;
        border-bottom: 1px solid #f0e8eb;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .perfil-card-icone {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #fff0f3;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #d66d7f;
        font-size: 17px;
        flex-shrink: 0;
    }
    .perfil-card-cabecalho h2 {
        font-family: 'Playfair Display', serif;
        font-size: 1.2rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
    }
    .perfil-card-corpo {
        padding: 28px;
    }

    /* === CAMPOS DO FORMULÁRIO === */
    .campo {
        margin-bottom: 18px;
    }
    .campo label {
        display: block;               /* label por cima do input */
        font-size: 12.5px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-bottom: 7px;
    }
    .campo input,
    .campo select {
        display: block;
        width: 100%;
        padding: 13px 16px;
        border: 1.5px solid #e5e7eb;
        border-radius: 10px;
        font-size: 15px;
        font-family: 'Poppins', sans-serif;
        color: #1f2937;
        background: #fff;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }
    .campo input:focus,
    .campo select:focus {
        outline: none;
        border-color: #d66d7f;
        box-shadow: 0 0 0 3px rgba(214, 109, 127, 0.12);
    }
    .campo input:disabled {
        background: #f9fafb;
        color: #9ca3af;
        cursor: not-allowed;
    }

    /* Grelha de dois campos lado a lado */
    .campos-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0 20px;
    }
    .campo-full { grid-column: 1 / -1; }

    /* Campo com botão mostrar/ocultar password */
    .campo-senha { position: relative; }
    .campo-senha input { padding-right: 48px; }
    .senha-toggle {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        border: none;
        background: none;
        cursor: pointer;
        color: #9ca3af;
        font-size: 16px;
        padding: 4px;
        line-height: 1;
        transition: color 0.2s;
    }
    .senha-toggle:hover { color: #d66d7f; }

    /* Botão de submissão */
    .perfil-btn-submit {
        width: 100%;
        padding: 14px;
        margin-top: 8px;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, #d66d7f, #bf5b6d);
        color: #fff;
        font-family: 'Poppins', sans-serif;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        letter-spacing: 0.3px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .perfil-btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(214, 109, 127, 0.30);
    }

    /* === RESPONSIVO === */
    @media (max-width: 600px) {
        .perfil-topo { padding: 16px 16px 52px; }
        .perfil-topo-info h1 { font-size: 1.4rem; }
        .campos-grid { grid-template-columns: 1fr; }
        .perfil-card-corpo { padding: 20px 18px; }
        .perfil-card-cabecalho { padding: 16px 18px; }
        .perfil-conteudo { padding: 0 12px 48px; }
    }
    </style>
</head>
<body>

<!-- Header com gradiente rosa -->
<div class="perfil-topo">
    <a href="index.php" class="perfil-voltar">
        <i class="fas fa-arrow-left"></i> Minha Conta
    </a>
    <div class="perfil-topo-info">
        <div class="perfil-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <h1>Os meus dados</h1>
            <p><?= htmlspecialchars($c['email']) ?></p>
        </div>
    </div>
</div>

<div class="perfil-conteudo">

    <!-- Alertas (erro ou sucesso após submissão) -->
    <?php if ($sucesso): ?>
        <div class="perfil-alerta sucesso">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($sucesso) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($erros)): ?>
        <div class="perfil-alerta erro">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                Corrija os seguintes erros:
                <ul><?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         CARD 1 - Informação Pessoal
         ============================================================ -->
    <div class="perfil-card">
        <div class="perfil-card-cabecalho">
            <div class="perfil-card-icone"><i class="fas fa-id-card"></i></div>
            <h2>Informação Pessoal</h2>
        </div>
        <div class="perfil-card-corpo">
            <form method="POST" novalidate>
                <?= csrf_input() ?>
                <input type="hidden" name="accao" value="dados">

                <div class="campos-grid">

                    <!-- Email - só leitura, é a chave de login -->
                    <div class="campo campo-full">
                        <label>Email (não editável)</label>
                        <input type="email" value="<?= htmlspecialchars($c['email']) ?>" disabled>
                    </div>

                    <div class="campo campo-full">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome"
                               value="<?= htmlspecialchars($c['nome']) ?>"
                               placeholder="Ex: Maria Silva" required>
                    </div>

                    <div class="campo">
                        <label>Telemóvel *</label>
                        <input type="text" name="telefone"
                               value="<?= htmlspecialchars($c['telefone']) ?>"
                               placeholder="Ex: 912 345 678" required>
                    </div>

                    <div class="campo">
                        <label>Código Postal *</label>
                        <input type="text" name="codigo_postal"
                               value="<?= htmlspecialchars($c['codigo_postal']) ?>"
                               placeholder="Ex: 8700-123" required>
                    </div>

                    <div class="campo campo-full">
                        <label>Morada *</label>
                        <input type="text" name="morada"
                               value="<?= htmlspecialchars($c['morada']) ?>"
                               placeholder="Rua, número, andar" required>
                    </div>

                    <div class="campo campo-full">
                        <label>Localidade *</label>
                        <input type="text" name="localidade"
                               value="<?= htmlspecialchars($c['localidade']) ?>"
                               placeholder="Ex: Olhão" required>
                    </div>

                </div>

                <button type="submit" class="perfil-btn-submit">
                    <i class="fas fa-save"></i> Guardar alterações
                </button>
            </form>
        </div>
    </div>

    <!-- ============================================================
         CARD 2 - Alterar Password
         ============================================================ -->
    <div class="perfil-card">
        <div class="perfil-card-cabecalho">
            <div class="perfil-card-icone"><i class="fas fa-lock"></i></div>
            <h2>Alterar Password</h2>
        </div>
        <div class="perfil-card-corpo">
            <form method="POST" novalidate>
                <?= csrf_input() ?>
                <input type="hidden" name="accao" value="password">

                <div class="campos-grid">

                    <div class="campo campo-full">
                        <label>Password Atual</label>
                        <div class="campo-senha">
                            <input type="password" name="password_atual" id="pass_atual"
                                   placeholder="••••••••" required>
                            <button type="button" class="senha-toggle"
                                    onclick="toggleSenha('pass_atual', this)"
                                    aria-label="Mostrar/ocultar password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="campo">
                        <label>Nova Password (mín. 6)</label>
                        <div class="campo-senha">
                            <input type="password" name="password_nova" id="pass_nova"
                                   placeholder="••••••••" required>
                            <button type="button" class="senha-toggle"
                                    onclick="toggleSenha('pass_nova', this)"
                                    aria-label="Mostrar/ocultar nova password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="campo">
                        <label>Confirmar Nova Password</label>
                        <div class="campo-senha">
                            <input type="password" name="password_conf" id="pass_conf"
                                   placeholder="••••••••" required>
                            <button type="button" class="senha-toggle"
                                    onclick="toggleSenha('pass_conf', this)"
                                    aria-label="Confirmar password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                </div>

                <button type="submit" class="perfil-btn-submit">
                    <i class="fas fa-key"></i> Alterar password
                </button>
            </form>
        </div>
    </div>

</div><!-- /perfil-conteudo -->

<script>
// Alterna visibilidade do campo de password (eye icon)
function toggleSenha(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
