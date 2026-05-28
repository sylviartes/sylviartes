<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$mensagem = "";
$tipo_msg = "";

// Apanha o sinal de sucesso após o recarregamento da página
if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1') {
    $mensagem = "Alterações guardadas com sucesso!";
    if (isset($_GET['imgs']) && (int)$_GET['imgs'] > 0) {
        $mensagem .= " " . (int)$_GET['imgs'] . " imagem(ns) adicionada(s).";
    }
    $tipo_msg = "sucesso";
}

// Apanha erros de upload guardados em sessão
$errosUploadFlash = [];
if (isset($_GET['err']) && $_GET['err'] == '1') {
    require_once __DIR__ . '/../../../config/session.php';
    if (!empty($_SESSION['upload_erros'])) {
        $errosUploadFlash = $_SESSION['upload_erros'];
        unset($_SESSION['upload_erros']);
    }
}

// Função para obter src da imagem
function imagem_src_produto_admin(array $row, string $campo = 'imagem'): ?string {
    if (!empty($row[$campo])) {
        $caminho = __DIR__ . '/../../imagens/produtos/' . $row[$campo];
        if (file_exists($caminho)) {
            return '../../imagens/produtos/' . $row[$campo];
        }
    }
    return null;
}

// Função para obter todas as imagens de um produto
function obter_imagens_produto(PDO $conn, int $produto_id): array {
    $imagens = [];

    $stmt = $conn->prepare("SELECT * FROM produto_imagem WHERE produto_id = :produto_id ORDER BY ordem ASC");
    $stmt->execute([':produto_id' => $produto_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        if (!empty($row['imagem'])) {
            $caminho = __DIR__ . '/../../imagens/produtos/' . $row['imagem'];
            if (file_exists($caminho)) {
                $imagens[] = [
                    'id' => $row['id'],
                    'ficheiro' => $row['imagem'],
                    'src' => '../../imagens/produtos/' . $row['imagem'],
                    'ordem' => $row['ordem']
                ];
            }
        }
    }

    // fallback para sistema antigo
    if (empty($imagens) && $produto_id > 0) {
        $stmt = $conn->prepare("SELECT imagem FROM produto_imagem WHERE id = :id");
        $stmt->execute([':id' => $produto_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['imagem'])) {
            $caminho = __DIR__ . '/../../imagens/produtos/' . $row['imagem'];
            if (file_exists($caminho)) {
                $stmt2 = $conn->prepare("
                    INSERT INTO produto_imagem (produto_id, imagem, ordem)
                    VALUES (:produto_id, :imagem, 1)
                ");
                $stmt2->execute([
                    ':produto_id' => $produto_id,
                    ':imagem' => $row['imagem']
                ]);

                $imagens[] = [
                    'id' => 0,
                    'ficheiro' => $row['imagem'],
                    'src' => '../../imagens/produtos/' . $row['imagem'],
                    'ordem' => 1
                ];
            }
        }
    }

    return $imagens;
}

// buscar produto
$stmt = $conn->prepare("SELECT * FROM produto WHERE id = :id");
$stmt->execute([':id' => $id]);
$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    header("Location: index.php");
    exit;
}

// Processar remoção de imagem
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
}

if (isset($_POST['remover_imagem_id']) && (int)$_POST['remover_imagem_id'] > 0) {
    $img_id = (int)$_POST['remover_imagem_id'];

    $stmt = $conn->prepare("SELECT imagem FROM produto_imagem WHERE id = :img_id AND produto_id = :produto_id");
    $stmt->execute([
        ':img_id' => $img_id,
        ':produto_id' => $id
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['imagem'])) {
        @unlink(__DIR__ . '/../../imagens/produtos/' . $row['imagem']);
    }

    $stmt = $conn->prepare("DELETE FROM produto_imagem WHERE id = :img_id AND produto_id = :produto_id");
    $stmt->execute([
        ':img_id' => $img_id,
        ':produto_id' => $id
    ]);

    $mensagem = "Imagem removida com sucesso.";
    $tipo_msg = "sucesso";
}

// Processar ordenação de imagens
if (isset($_POST['ordenar_imagens']) && is_array($_POST['ordem_img'])) {
    foreach ($_POST['ordem_img'] as $img_id => $ordem) {
        $stmt = $conn->prepare("
            UPDATE produto_imagem
            SET ordem = :ordem
            WHERE id = :img_id AND produto_id = :produto_id
        ");
        $stmt->execute([
            ':ordem' => (int)$ordem,
            ':img_id' => (int)$img_id,
            ':produto_id' => $id
        ]);
    }

    $mensagem = "Ordem das imagens atualizada.";
    $tipo_msg = "sucesso";
}

// Processar atualização do produto
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['remover_imagem_id']) && !isset($_POST['ordenar_imagens'])) {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria_id = (int)($_POST['categoria'] ?? 0);
    $visivel = isset($_POST['visivel']) ? 1 : 0;

    if ($nome === '' || $categoria_id === 0) {
        $mensagem = "Preenche o nome e escolhe uma categoria.";
        $tipo_msg = "erro";
    } else {
        // Site é portfólio: não mexemos em preco_base nem stock (não têm uso).
        $sql = "
            UPDATE produto
            SET nome = :nome,
                descricao = :descricao,
                categoria_id = :categoria_id,
                visivel_catalogo = :visivel
            WHERE id = :id
        ";

        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute([
            ':nome' => $nome,
            ':descricao' => $descricao,
            ':categoria_id' => $categoria_id,
            ':visivel' => $visivel,
            ':id' => $id
        ]);

        // Processar upload novas imagens
        $errosUpload = [];
        $totalCarregadas = 0;
        if (isset($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
            $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            $stmtMax = $conn->prepare("SELECT MAX(ordem) AS max_ordem FROM produto_imagem WHERE produto_id = :produto_id");
            $stmtMax->execute([':produto_id' => $id]);
            $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
            $ordem_atual = (!empty($rowMax['max_ordem'])) ? (int)$rowMax['max_ordem'] : 0;

            $pasta_produtos = __DIR__ . '/../../imagens/produtos';
            if (!is_dir($pasta_produtos)) {
                mkdir($pasta_produtos, 0755, true);
            }

            $total_fotos = count($_FILES['fotos']['name']);
            for ($i = 0; $i < $total_fotos; $i++) {
                $erroCode = $_FILES['fotos']['error'][$i];
                $nomeOrig = $_FILES['fotos']['name'][$i];

                // Reporta erros para o utilizador (em vez de falhar silenciosamente)
                if ($erroCode === UPLOAD_ERR_INI_SIZE || $erroCode === UPLOAD_ERR_FORM_SIZE) {
                    $errosUpload[] = "$nomeOrig: ficheiro demasiado grande (máx " . ini_get('upload_max_filesize') . ").";
                    continue;
                }
                if ($erroCode === UPLOAD_ERR_PARTIAL) {
                    $errosUpload[] = "$nomeOrig: upload incompleto.";
                    continue;
                }
                if ($erroCode !== UPLOAD_ERR_OK) {
                    if ($erroCode !== UPLOAD_ERR_NO_FILE) {
                        $errosUpload[] = "$nomeOrig: erro de upload (código $erroCode).";
                    }
                    continue;
                }

                $ext = strtolower(pathinfo($nomeOrig, PATHINFO_EXTENSION));
                if (!in_array($ext, $permitidas, true)) {
                    $errosUpload[] = "$nomeOrig: formato não permitido (use jpg, png, gif ou webp).";
                    continue;
                }

                $imageInfo = @getimagesize($_FILES['fotos']['tmp_name'][$i]);
                if ($imageInfo === false) {
                    $errosUpload[] = "$nomeOrig: o ficheiro não é uma imagem válida.";
                    continue;
                }

                $ordem_atual++;
                $nome_foto = uniqid('prod_' . $id . '_', true) . '.' . $ext;
                $caminho = $pasta_produtos . '/' . $nome_foto;

                if (move_uploaded_file($_FILES['fotos']['tmp_name'][$i], $caminho)) {
                    $stmtImg = $conn->prepare("
                        INSERT INTO produto_imagem (produto_id, imagem, ordem)
                        VALUES (:produto_id, :imagem, :ordem)
                    ");
                    $stmtImg->execute([
                        ':produto_id' => $id,
                        ':imagem' => $nome_foto,
                        ':ordem' => $ordem_atual
                    ]);
                    $totalCarregadas++;
                } else {
                    $errosUpload[] = "$nomeOrig: falha ao mover ficheiro para a pasta.";
                }
            }
        }

        if ($ok) {
            // Recarrega o edit.php com flags de sucesso/erro
            $params = ['id' => $id, 'sucesso' => 1];
            if ($totalCarregadas > 0) $params['imgs'] = $totalCarregadas;
            if (!empty($errosUpload)) {
                // Guarda os erros em sessão para mostrar após redirect
                require_once __DIR__ . '/../../../config/session.php';
                $_SESSION['upload_erros'] = $errosUpload;
                $params['err'] = 1;
            }
            header("Location: edit.php?" . http_build_query($params));
            exit;
        } else {
            $mensagem = "Erro ao guardar alterações.";
            $tipo_msg = "erro";
        }
    }
}

// recarregar imagens após ações
$imagens_produto = obter_imagens_produto($conn, $id);

// categorias
$stmtCats = $conn->query("SELECT id, nome FROM categoria ORDER BY nome");
$categorias = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - SylviArtes</title>
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; color: #2d3436; font-weight: 600; }
        .msg-box { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
        .msg-sucesso { background: rgba(40, 167, 69, 0.15); color: #28a745; border: 1px solid #28a745; }
        .msg-erro { background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid #dc3545; }
        .row { display: flex; gap: 30px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 280px; }
        .preview { width: 180px; height: 180px; border: 2px solid #eee; border-radius: 12px; overflow: hidden; background: #fff; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; position: relative; }
        .preview img { width: 100%; height: 100%; object-fit: cover; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; padding: 12px; border: 2px solid #eee; border-radius: 10px; font-size: 14px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #d66d7f; outline: none; box-shadow: 0 0 0 4px rgba(214, 109, 127, 0.1);
        }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-top: 12px; }
        .checkbox-group input { width: auto !important; }
        .checkbox-group label { margin: 0 !important; cursor: pointer; }
        @media (max-width: 768px) {
            .main-content { padding: 15px; }
            .row { flex-direction: column; }
        }
    </style>
</head>
<body class="admin-body">
<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main-content">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px;">
        <h1><i class="fas fa-edit"></i> Editar Produto</h1>
        <a href="index.php" class="btn-action" style="background:#6c757d;"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if($mensagem): ?>
        <div class="msg-box <?php echo ($tipo_msg === 'sucesso') ? 'msg-sucesso' : 'msg-erro'; ?>">
            <i class="fas <?php echo ($tipo_msg === 'sucesso') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($mensagem); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errosUploadFlash)): ?>
        <div class="msg-box msg-erro" style="text-align:left;">
            <i class="fas fa-exclamation-triangle"></i> <strong>Algumas imagens não foram guardadas:</strong>
            <ul style="margin:8px 0 0 18px;">
                <?php foreach ($errosUploadFlash as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <div class="row">
                <div class="col">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Nome</label>
                        <input type="text" name="nome" required value="<?php echo htmlspecialchars($produto['nome']); ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-folder"></i> Categoria</label>
                        <select name="categoria" required>
                            <option value="">-- Selecionar --</option>
                            <?php foreach($categorias as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ((int)$produto['categoria_id'] === (int)$c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Descrição</label>
                        <textarea name="descricao" rows="3"><?php echo htmlspecialchars($produto['descricao'] ?? ''); ?></textarea>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="visivel" id="vis" <?php echo ((int)$produto['visivel_catalogo'] === 1) ? 'checked' : ''; ?>>
                        <label for="vis">Visível no catálogo</label>
                    </div>
                </div>

                <div class="col">
                    <div class="form-group">
                        <label><i class="fas fa-images"></i> Imagens do Produto</label>
                        
                        <?php if (!empty($imagens_produto)): ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                            <?php foreach ($imagens_produto as $index => $img): ?>
                            <div style="position: relative; width: 100px; height: 100px; border: 2px solid #eee; border-radius: 8px; overflow: hidden; <?php echo $index === 0 ? 'border-color: #d66d7f;' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($img['src']); ?>" alt="Imagem <?php echo $index + 1; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php if ($index === 0): ?>
                                <span style="position: absolute; bottom: 0; left: 0; right: 0; background: #d66d7f; color: white; font-size: 10px; text-align: center; padding: 2px;">Principal</span>
                                <?php endif; ?>
                                
                                <button type="submit" name="remover_imagem_id" value="<?php echo (int)$img['id']; ?>" formnovalidate onclick="return confirm('Remover esta imagem?');" style="position: absolute; top: 2px; right: 2px; margin: 0; background: rgba(220,53,69,0.9); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px;">
                                    <i class="fas fa-times"></i>
                                </button>
                                
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="color: #888; font-size: 12px; margin-bottom: 10px;">
                            <i class="fas fa-info-circle"></i> Clique no X para remover uma imagem
                        </p>
                        <?php else: ?>
                        <div style="border: 2px dashed #ddd; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 15px; color: #999;">
                            <i class="fas fa-image fa-2x"></i><br>
                            Ainda sem imagens
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-upload"></i> Adicionar Novas Imagens</label>
                        <input type="file" name="fotos[]" accept="image/*" multiple>
                        <small style="color:#888; font-size: 12px;">
                            Selecione várias imagens (jpg, png, gif, webp) — máximo <?= ini_get('upload_max_filesize') ?>B por ficheiro
                        </small>
                    </div>

                    <p style="color:#888; font-size:13px; margin-top:15px;">
                        <i class="fas fa-lightbulb"></i> A primeira imagem será a imagem principal do produto
                    </p>
                </div>
            </div>

            <button type="submit" class="btn-action" style="margin-top:20px; width:100%;">
                <i class="fas fa-save"></i> Guardar alterações
            </button>
        </form>
    </div>
</div>

</body>
</html>