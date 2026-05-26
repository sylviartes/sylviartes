<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';

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
    $nome = trim($_POST['nome_cat'] ?? '');
    $desc = trim($_POST['desc_cat'] ?? '');

    if (!empty($nome)) {
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
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .msg-box { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
        .msg-sucesso { background: rgba(40, 167, 69, 0.15); color: #28a745; }
        .msg-erro { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; }
        .form-group input, .form-group textarea { 
            width: 100%; padding: 12px; border: 2px solid #eee; border-radius: 10px; font-size: 14px;
            transition: all 0.3s;
        }
        .form-group input:focus, .form-group textarea:focus {
            border-color: #d66d7f; outline: none; box-shadow: 0 0 0 4px rgba(214, 109, 127, 0.1);
        }
    </style>
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main-content">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
        <h1><i class="fas fa-edit"></i> Editar Categoria #<?php echo $id; ?></h1>
        <a href="index.php" class="btn-action" style="background:#6c757d;"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if($mensagem): ?>
    <div class="msg-box <?php echo $tipo_msg === 'sucesso' ? 'msg-sucesso' : 'msg-erro'; ?>">
        <?php echo htmlspecialchars($mensagem); ?>
    </div>
    <?php endif; ?>

    <div class="card" style="max-width: 500px;">
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Nome da Categoria:</label>
                <input type="text" name="nome_cat" value="<?php echo htmlspecialchars($cat['nome']); ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Descrição (opcional):</label>
                <textarea name="desc_cat" rows="3"><?php echo htmlspecialchars($cat['descricao'] ?? ''); ?></textarea>
            </div>
            <button type="submit" name="guardar" class="btn-action" style="width:100%;">
                <i class="fas fa-save"></i> Guardar Alterações
            </button>
        </form>
    </div>
</div>

</body>
</html>
