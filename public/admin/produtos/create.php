<?php
require_once __DIR__ . '/../../../config/db.php'; 
require_once __DIR__ . '/../auth.php';  
$mensagem = '';
$tipo_msg = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = str_replace(',', '.', ($_POST['preco'] ?? ''));
    $categoria_id = (int)($_POST['categoria'] ?? 0);
    $stock_inicial = (int)($_POST['stock'] ?? 0);
    $visivel = isset($_POST['visivel']) ? 1 : 0;

    if ($nome === '' || $preco === '' || $categoria_id === 0) {
        $mensagem = "Preenche o nome, o preço, o stock e escolhe uma categoria.";
        $tipo_msg = "erro";
    } else {
        // === Upload de IMAGENS (suporta múltiplas) ===
        // O form usa name="fotos[]" multiple, por isso $_FILES['fotos'] vem como array
        $imagensCarregadas = [];   // lista de nomes de ficheiro guardados em public/imagens/produtos/
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        // Caminho: a partir de public/admin/produtos/, subir 2 níveis até public/, depois entrar em imagens/produtos/
        $pasta_produtos = __DIR__ . '/../../imagens/produtos';

        if (isset($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
            if (!is_dir($pasta_produtos)) {
                mkdir($pasta_produtos, 0755, true);
            }

            $totalFotos = count($_FILES['fotos']['name']);
            for ($i = 0; $i < $totalFotos; $i++) {
                if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;

                $ext = strtolower(pathinfo($_FILES['fotos']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $permitidas, true)) continue;

                $nome_foto = uniqid('prod_', true) . '.' . $ext;
                $caminho = $pasta_produtos . '/' . $nome_foto;

                if (move_uploaded_file($_FILES['fotos']['tmp_name'][$i], $caminho)) {
                    $imagensCarregadas[] = $nome_foto;
                }
            }
        }
        // Compatibilidade retroativa: ainda aceita campo "foto" único
        elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $permitidas, true)) {
                if (!is_dir($pasta_produtos)) mkdir($pasta_produtos, 0755, true);
                $nome_foto = uniqid('prod_', true) . '.' . $ext;
                $caminho = $pasta_produtos . '/' . $nome_foto;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminho)) {
                    $imagensCarregadas[] = $nome_foto;
                }
            }
        }

        if (empty($imagensCarregadas)) {
            $mensagem = "É obrigatório adicionar pelo menos uma foto do produto.";
            $tipo_msg = "erro";
        } else {
            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("
                    INSERT INTO produto
                    (nome, descricao, preco_base, categoria_id, visivel_catalogo, stock)
                    VALUES
                    (:nome, :descricao, :preco, :categoria_id, :visivel, :stock)
                ");

                $ok = $stmt->execute([
                    ':nome' => $nome,
                    ':descricao' => $descricao,
                    ':preco' => $preco,
                    ':categoria_id' => $categoria_id,
                    ':visivel' => $visivel,
                    ':stock' => $stock_inicial
                ]);

                $produto_id = (int)$conn->lastInsertId();

                if ($ok && $produto_id > 0) {
                    // Insere cada imagem com a ordem incremental (1, 2, 3...)
                    // A primeira (ordem=1) é a principal mostrada no catálogo.
                    $stmtImg = $conn->prepare("
                        INSERT INTO produto_imagem (produto_id, imagem, ordem)
                        VALUES (:produto_id, :imagem, :ordem)
                    ");
                    foreach ($imagensCarregadas as $idx => $nomeFich) {
                        $stmtImg->execute([
                            ':produto_id' => $produto_id,
                            ':imagem' => $nomeFich,
                            ':ordem' => $idx + 1,
                        ]);
                    }
                }

                $conn->commit();

                header("Location: index.php");
                exit;
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $mensagem = "Erro ao inserir: " . $e->getMessage();
                $tipo_msg = "erro";
            }
        }
    }
}

// categorias
$stmtCats = $conn->query("SELECT * FROM categoria ORDER BY nome");
$categorias = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Produto - SylviArtes</title>
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        .msg-box { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
        .msg-sucesso { background: rgba(40, 167, 69, 0.15); color: #28a745; }
        .msg-erro { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; padding: 12px; border: 2px solid #eee; border-radius: 10px; font-size: 14px; 
            transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #d66d7f; outline: none; box-shadow: 0 0 0 4px rgba(214, 109, 127, 0.1);
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main-content">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px;">
        <h1><i class="fas fa-plus-circle"></i> Adicionar Novo Produto</h1>
        <a href="index.php" class="btn-action" style="background:#6c757d;"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if($mensagem): ?>
        <div class="msg-box <?php echo ($tipo_msg === 'sucesso') ? 'msg-sucesso' : 'msg-erro'; ?>">
            <?php echo htmlspecialchars($mensagem); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Nome do Produto:</label>
                    <input type="text" name="nome" required placeholder="Ex: Toalha Bordada">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-euro-sign"></i> Preço (€):</label>
                    <input type="text" name="preco" required placeholder="Ex: 15.50">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Categoria:</label>
                    <select name="categoria" required>
                        <option value="">-- Selecionar --</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>">
                                <?php echo htmlspecialchars($c['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-boxes"></i> Stock:</label>
                    <input type="number" name="stock" value="10" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-images"></i> Fotos do Produto (pode escolher várias):</label>
                    <input type="file" name="fotos[]" accept="image/*" multiple required>
                    <small style="color:#888;">Formatos: jpg, png, gif ou webp. A 1ª foto é a principal no catálogo.</small>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-top:30px;">
                    <input type="checkbox" name="visivel" id="v" checked style="width:auto;">
                    <label for="v" style="margin:0; cursor:pointer;">Mostrar no Catálogo</label>
                </div>
                <div class="full-width form-group">
                    <label><i class="fas fa-align-left"></i> Descrição:</label>
                    <textarea name="descricao" rows="2" placeholder="Detalhes do produto..."></textarea>
                </div>
            </div>
            <button type="submit" class="btn-action" style="margin-top:15px; width:100%;">
                <i class="fas fa-save"></i> Gravar Produto
            </button>
        </form>
    </div>
</div>

</body>
</html>
