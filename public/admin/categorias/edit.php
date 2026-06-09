<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$mensagem = '';
$tipo_msg = '';

// Fetch categoria
$stmt = $conn->prepare('SELECT * FROM categoria WHERE id = :id');
$stmt->execute([':id' => $id]);
$cat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cat) {
    header('Location: index.php');
    exit;
}

if ($_POST && isset($_POST['guardar'])) {
    csrf_validate();
    $nome = trim($_POST['nome_cat'] ?? '');
    $desc = trim($_POST['desc_cat'] ?? '');

    if (!empty($nome)) {
        // Site por orçamento - categorias não têm preço (removido).
        $stmt = $conn->prepare('UPDATE categoria SET nome = :nome, descricao = :descricao WHERE id = :id');
        $ok = $stmt->execute([
            ':nome' => $nome,
            ':descricao' => $desc,
            ':id' => $id
        ]);

        if ($ok) {
            header('Location: index.php');
            exit;
        }
    } else {
        $mensagem = 'Nome obrigatório.';
        $tipo_msg = 'erro';
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Categoria - SylviArtes</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../../imagens/logo_sylviartes.png">
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Estilos do formulário centralizados em admin_style.css (.form-card, .form-field, etc.) -->
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main-content">

    <!-- Cabeçalho: ID da categoria + botão Voltar -->
    <div class="admin-page-header">
        <div>
            <h1><i class="fas fa-edit"></i> Editar Categoria #<?= $id ?></h1>
            <div class="subtitulo">Altera o nome ou a descrição desta categoria</div>
        </div>
        <a href="index.php" class="btn-action btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <!-- Mensagem de erro (ex: nome vazio ao guardar) -->
    <?php if ($mensagem): ?>
        <div class="msg-box <?= $tipo_msg === 'sucesso' ? 'msg-sucesso' : 'msg-erro' ?>">
            <i class="fas fa-<?= $tipo_msg === 'sucesso' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- Formulário de edição -->
    <div class="form-card">
        <form method="POST">
            <?= csrf_input() ?>

            <!-- Nome - linha toda, pré-preenchido com o valor da BD -->
            <div class="form-field form-field-full">
                <label for="nome_cat">Nome da Categoria <span class="req">*</span></label>
                <input type="text" id="nome_cat" name="nome_cat"
                       value="<?= htmlspecialchars($cat['nome']) ?>" required>
            </div>

            <!-- Descrição (preço removido - site é por orçamento personalizado) -->
            <div class="form-field form-field-full">
                <label for="desc_cat">Descrição <span class="opt">(opcional)</span></label>
                <textarea id="desc_cat" name="desc_cat"
                          rows="4"><?= htmlspecialchars($cat['descricao'] ?? '') ?></textarea>
            </div>

            <!-- Botão guardar em secção própria -->
            <div class="form-actions">
                <button type="submit" name="guardar" class="btn-submit">
                    <i class="fas fa-save"></i> Guardar Alterações
                </button>
            </div>

        </form>
    </div><!-- /form-card -->

</div><!-- /main-content -->

</body>
</html>
