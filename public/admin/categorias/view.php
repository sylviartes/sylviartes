<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$cat_id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("SELECT * FROM categoria WHERE id = ?");
    $stmt->execute([$cat_id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$categoria) {
        die("Categoria não encontrada.");
    }

    // Buscar produtos
    $stmt_prod = $conn->prepare("SELECT p.*, pi.imagem 
                                FROM produto p 
                                LEFT JOIN produto_imagem pi ON p.id = pi.produto_id 
                                WHERE p.categoria_id = ? 
                                GROUP BY p.id");
    $stmt_prod->execute([$cat_id]);
    $produtos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Categoria - <?php echo htmlspecialchars($categoria['nome']); ?></title>
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .cat-header { background: #fff; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; border-left: 5px solid #d66d7f; }
        .cat-header h2 { color: #d66d7f; margin: 0; text-transform: capitalize; }
        .stats-badge { display: inline-block; padding: 5px 15px; background: #fdf2f4; color: #d66d7f; border-radius: 15px; font-weight: bold; font-size: 0.9em; margin-top: 15px; }
        .prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .prod-card { background: white; border-radius: 15px; padding: 15px; text-align: center; border: 1px solid #eee; display: flex; flex-direction: column; justify-content: space-between; min-height: 320px; }
        .prod-img { width: 100%; height: 180px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; background: #fdf2f4; }
        .btn-edit-cat { background: linear-gradient(135deg, #e07a8b 0%, #d66d7f 100%); color: white !important; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/../sidebar.php'; ?>

    <div class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1><i class="fas fa-folder-open"></i> Detalhes da Categoria</h1>
            <div style="display:flex; gap:10px;">
                <a href="edit.php?id=<?php echo $cat_id; ?>" class="btn-edit-cat"><i class="fas fa-edit"></i> Editar Categoria</a>
                <a href="index.php" class="btn-action" style="background:#6c757d; color:white; padding:10px 20px; border-radius:25px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <div class="cat-header">
            <h2><?php echo htmlspecialchars($categoria['nome']); ?></h2>
            <div class="stats-badge">
                <i class="fas fa-box"></i> <?php echo count($produtos); ?> Produtos
            </div>
        </div>

        <div class="card">
            <h3 style="color: #d66d7f; margin-bottom: 20px;"><i class="fas fa-th-large"></i> PRODUTOS ASSOCIADOS</h3>
            
            <div class="prod-grid">
                <?php foreach($produtos as $p): 
                    
                    if (!empty($p['imagem'])) {
                        // Converte o nome da categoria para minúsculas (ex: "Toalha" passa a "toalha")
                        // para coincidir com o nome da pasta no Windows
                        $nome_pasta = strtolower(trim($categoria['nome']));
                        
                        // Correção: recua duas pastas e entra direto em "imagens"
                        $imgSrc = "../../imagens/" . $nome_pasta . "/" . $p['imagem'];
                    } else {
                        // Imagem por defeito caso o produto não tenha foto
                        $imgSrc = '../imagens/placeholder.png';
                    }
                    
                    $preco_exibir = $p['preco_base'] ?? 0; 
                ?>
                    <div class="prod-card">
                        <div>
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="prod-img" alt="Foto do produto">
                            <div style="font-weight:600; font-size: 0.95em; color: #333;">
                                <?php echo htmlspecialchars($p['nome']); ?>
                            </div>
                        </div>
                        
                        <div>
                            <div style="color:#d66d7f; font-weight:800; font-size: 1.2em; margin: 15px 0;">
                                <?php echo number_format((float)$preco_exibir, 2, ',', ' '); ?> €
                            </div>
                            <a href="../produtos/edit.php?id=<?php echo $p['id']; ?>" style="font-size: 0.85em; color: #d66d7f; text-decoration: none; font-weight: 600;">
                                <i class="fas fa-pen"></i> EDITAR PRODUTO
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>