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
    <style>
        body { font-family: 'Poppins', sans-serif; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; color: #2d3436; font-weight: 600; }
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
        <h1><i class="fas fa-plus-circle"></i> Nova Categoria</h1>
        <a href="index.php" class="btn-action" style="background:#6c757d;"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if($mensagem): ?>
    <div class="msg-box <?php echo $tipo_msg === 'sucesso' ? 'msg-sucesso' : 'msg-erro'; ?>">
        <?php echo htmlspecialchars($mensagem); ?>
    </div>
    <?php endif; ?>

    <div class="card" style="max-width: 500px;">
        <form method="POST">
            <?= csrf_input() ?>
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Nome da Categoria:</label>
                <input type="text" name="nome_cat" placeholder="Ex: Toalhas" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Descrição (opcional):</label>
                <textarea name="desc_cat" placeholder="Descrição da categoria..." rows="3"></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-euro-sign"></i> Preço indicativo (opcional):</label>
                <input type="text" name="preco_ref" placeholder="Ex: 25.00">
                <small style="color:#888;">Mostrado como "A partir de €X" na página do produto. Deixe vazio se não quiser mostrar.</small>
            </div>
            <button type="submit" name="nova_categoria" class="btn-action" style="width:100%;">
                <i class="fas fa-save"></i> Criar Categoria
            </button>
        </form>
    </div>
</div>

</body>
</html>
