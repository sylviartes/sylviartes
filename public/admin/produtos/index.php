<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

$mensagem = '';
$tipo_msg = '';

// Redirect from deprecated produto.php
if (basename($_SERVER['PHP_SELF']) === 'produto.php') {
    header('Location: index.php');
    exit;
}

function imagem_src_produto(array $row): ?string {
    if (!empty($row['imagem'])) {
        // 1. Primeiro tenta na pasta com o nome da categoria
        if (!empty($row['cat_nome'])) {
            $nome_pasta = strtolower(trim($row['cat_nome']));
            $caminho_categoria_fisico = __DIR__ . '/../../imagens/' . $nome_pasta . '/' . $row['imagem'];
            
            if (file_exists($caminho_categoria_fisico)) {
                return '../../imagens/' . $nome_pasta . '/' . $row['imagem'];
            }
        }

        // 2. Se não encontrar, tenta na pasta genérica "produtos" (fallback)
        $caminho_fisico = __DIR__ . '/../../imagens/produtos/' . $row['imagem'];
        if (file_exists($caminho_fisico)) {
            return '../../imagens/produtos/' . $row['imagem'];
        }
    }

    if (empty($row['imagem']) || $row['imagem'] === "NULL") {
        return null;
    }

    $mime = $row['imagem_mime'] ?? "image/jpeg";
    // Tratamento de recurso caso o LONGBLOB venha como stream
    $imagem_dados = is_resource($row['imagem']) ? stream_get_contents($row['imagem']) : $row['imagem'];
    
    if (empty($imagem_dados)) return null;

    return "data:" . $mime . ";base64," . base64_encode($imagem_dados);
}

// categorias para compatibilidade
$stmtCats = $conn->query("SELECT * FROM categoria ORDER BY nome");
$categorias = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// listar produtos (agora vai buscar a primeira imagem do produto à tabela produto_imagem)
$sql = "
    SELECT p.*, c.nome AS cat_nome,
           (SELECT imagem FROM produto_imagem WHERE produto_id = p.id ORDER BY ordem ASC LIMIT 1) AS imagem
    FROM produto p
    LEFT JOIN categoria c ON p.categoria_id = c.id
    ORDER BY p.id DESC
";
$stmtProdutos = $conn->query($sql);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfólio — SylviArtes Admin</title>
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .pagina-header {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 16px;
            margin-bottom: 24px; padding-bottom: 18px;
            border-bottom: 1px solid #f0e3e7;
        }
        .pagina-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px; color: #2d3436; margin: 0;
            font-weight: 600;
        }
        .pagina-header .total { color: #888; font-size: 14px; margin-top: 4px; }

        .btn-add {
            background: linear-gradient(135deg, #c95f7a, #d6788b);
            color: #fff; padding: 11px 22px; border-radius: 999px;
            text-decoration: none; font-weight: 600; font-size: 14px;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }
        .btn-add:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(201,95,122,0.30); }

        .msg-box { padding: 13px 18px; border-radius: 10px; margin-bottom: 18px; }
        .msg-sucesso { background: #d1fae5; color: #065f46; border-left: 3px solid #22c55e; }
        .msg-erro    { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }

        /* Grelha de itens */
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 18px;
        }
        .item-card {
            background: #fff;
            border: 1px solid #f0e3e7;
            border-radius: 14px;
            overflow: hidden;
            transition: all 0.25s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .item-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(214,109,127,0.10); }
        .item-imagem {
            height: 200px; background: #f5e9ec;
            display: flex; align-items: center; justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .item-imagem img { width: 100%; height: 100%; object-fit: cover; }
        .item-imagem .placeholder { color: #d4a3ad; font-size: 36px; }
        .item-vis-badge {
            position: absolute; top: 10px; right: 10px;
            padding: 4px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 600;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
        }
        .item-vis-badge.oculto { background: #fee2e2; color: #991b1b; }
        .item-vis-badge.visivel { background: #d1fae5; color: #065f46; }
        .item-info { padding: 16px 18px; }
        .item-info h4 {
            margin: 0 0 4px;
            font-size: 15px;
            color: #2d3436;
            font-weight: 600;
        }
        .item-info .categoria {
            color: #d66d7f; font-size: 12px; font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .item-info .desc {
            color: #888; font-size: 13px; margin-top: 8px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 36px;
        }
        .item-acoes {
            padding: 12px 18px; border-top: 1px solid #f0e3e7;
            display: flex; gap: 8px;
        }
        .item-acoes a {
            flex: 1; text-align: center; padding: 8px 12px;
            border-radius: 8px; text-decoration: none; font-weight: 600;
            font-size: 13px; transition: all 0.15s;
        }
        .btn-editar { background: #fff8fa; color: #d66d7f; border: 1px solid #f0c8d2; }
        .btn-editar:hover { background: #d66d7f; color: #fff; }
        .btn-apagar { background: #fff; color: #ef4444; border: 1px solid #fecaca; }
        .btn-apagar:hover { background: #ef4444; color: #fff; }

        .vazio {
            text-align: center; padding: 60px 20px; color: #888;
            background: #fff; border-radius: 14px; border: 2px dashed #f0e3e7;
        }
        .vazio i { font-size: 48px; color: #d4a3ad; margin-bottom: 12px; }
    </style>
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main-content">
    <div class="pagina-header">
        <div>
            <h1><i class="fas fa-images"></i> Portfólio</h1>
            <div class="total">
                <?= count($produtos) ?> <?= count($produtos) === 1 ? 'item no portfólio' : 'itens no portfólio' ?>
                · Bordados que a SylviArtes já fez
            </div>
        </div>
        <a href="create.php" class="btn-add">
            <i class="fas fa-plus"></i> Adicionar trabalho
        </a>
    </div>

    <?php if ($mensagem): ?>
        <div class="msg-box <?= $tipo_msg === 'sucesso' ? 'msg-sucesso' : 'msg-erro' ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($produtos)): ?>
        <div class="vazio">
            <i class="fas fa-images"></i>
            <h3 style="margin:0 0 6px; font-family:'Playfair Display',serif; color:#2d3436;">Portfólio vazio</h3>
            <p>Ainda não adicionou nenhum trabalho. Comece por adicionar uma peça que já tenha feito.</p>
            <a href="create.php" class="btn-add" style="margin-top:14px;">
                <i class="fas fa-plus"></i> Adicionar primeiro trabalho
            </a>
        </div>
    <?php else: ?>
        <div class="portfolio-grid">
            <?php foreach ($produtos as $row): ?>
                <?php
                $srcImagem = imagem_src_produto($row);
                $visivel = (int)$row['visivel_catalogo'] === 1;
                ?>
                <div class="item-card">
                    <div class="item-imagem">
                        <?php if ($srcImagem): ?>
                            <img src="<?= htmlspecialchars($srcImagem) ?>" alt="<?= htmlspecialchars($row['nome']) ?>">
                        <?php else: ?>
                            <i class="fas fa-image placeholder"></i>
                        <?php endif; ?>
                        <span class="item-vis-badge <?= $visivel ? 'visivel' : 'oculto' ?>">
                            <i class="fas fa-<?= $visivel ? 'eye' : 'eye-slash' ?>"></i>
                            <?= $visivel ? 'Público' : 'Oculto' ?>
                        </span>
                    </div>
                    <div class="item-info">
                        <h4><?= htmlspecialchars($row['nome']) ?></h4>
                        <?php if (!empty($row['cat_nome'])): ?>
                            <div class="categoria"><?= htmlspecialchars($row['cat_nome']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($row['descricao'])): ?>
                            <div class="desc"><?= htmlspecialchars($row['descricao']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="item-acoes">
                        <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn-editar">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Apagar este produto?');">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn-apagar">
                                <i class="fas fa-trash"></i> Apagar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>