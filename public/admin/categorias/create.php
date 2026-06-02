<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

$mensagem = '';
$tipo_msg = '';

if (isset($_POST['nova_categoria'])) {
    csrf_validate();
    $nome = trim($_POST['nome_cat'] ?? '');
    $desc = trim($_POST['desc_cat'] ?? '');
    // Preço indicativo (opcional) — vazio fica NULL
    $precoRefRaw = trim(str_replace(',', '.', $_POST['preco_ref'] ?? ''));
    $precoRef = ($precoRefRaw !== '' && is_numeric($precoRefRaw)) ? (float)$precoRefRaw : null;

    if (!empty($nome)) {
        $stmt = $conn->prepare("INSERT INTO categoria (nome, descricao, preco_referencia) VALUES (:nome, :descricao, :preco)");
        $stmt->execute([
            ':nome' => $nome,
            ':descricao' => $desc,
            ':preco' => $precoRef
        ]);

        header("Location: index.php");
        exit;
    } else {
        $mensagem = 'Nome da categoria obrigatório.';
        $tipo_msg = 'erro';
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Categoria - SylviArtes</title>
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Estilos do formulário centralizados em admin_style.css (.form-card, .form-field, etc.) -->
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main-content">

    <!-- Cabeçalho: título à esquerda, botão Voltar à direita -->
    <!-- Usa .admin-page-header definido em admin_style.css -->
    <div class="admin-page-header">
        <div>
            <h1><i class="fas fa-plus-circle"></i> Nova Categoria</h1>
            <div class="subtitulo">Cria uma nova categoria para organizar o portfólio</div>
        </div>
        <a href="index.php" class="btn-action btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <!-- Mensagem de erro (aparece se o nome estiver vazio) -->
    <?php if ($mensagem): ?>
        <div class="msg-box <?= $tipo_msg === 'sucesso' ? 'msg-sucesso' : 'msg-erro' ?>">
            <i class="fas fa-<?= $tipo_msg === 'sucesso' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- Formulário de nova categoria -->
    <div class="form-card">
        <form method="POST">
            <?= csrf_input() ?>

            <!-- Nome — ocupa a linha toda (campo mais importante) -->
            <div class="form-field form-field-full">
                <label for="nome_cat">Nome da Categoria <span class="req">*</span></label>
                <input type="text" id="nome_cat" name="nome_cat"
                       placeholder="Ex: Toalhas" required autocomplete="off">
            </div>

            <!-- Grelha 2 colunas: Descrição + Preço lado a lado -->
            <div class="form-grid">
                <!-- Descrição (coluna esquerda) -->
                <div class="form-field">
                    <label for="desc_cat">Descrição <span class="opt">(opcional)</span></label>
                    <textarea id="desc_cat" name="desc_cat" rows="4"
                              placeholder="Descrição da categoria..."></textarea>
                </div>

                <!-- Preço indicativo (coluna direita) -->
                <div class="form-field">
                    <label for="preco_ref">Preço Indicativo <span class="opt">(opcional)</span></label>
                    <div class="input-prefix-wrapper">
                        <span class="input-prefix">€</span>
                        <input type="text" id="preco_ref" name="preco_ref"
                               placeholder="25.00" class="input-with-prefix">
                    </div>
                    <!-- Explica onde este preço aparece no site público -->
                    <div class="form-hint">Aparece como "A partir de €X" na página do produto.</div>
                </div>
            </div>

            <!-- Botão em secção própria com fundo levemente diferente -->
            <div class="form-actions">
                <button type="submit" name="nova_categoria" class="btn-submit">
                    <i class="fas fa-save"></i> Criar Categoria
                </button>
            </div>

        </form>
    </div><!-- /form-card -->

</div><!-- /main-content -->

</body>
</html>
