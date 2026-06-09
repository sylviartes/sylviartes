<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

$mensagem = '';

// Lista categorias + nº de itens do portfólio em cada uma
$stmt = $conn->query("
    SELECT c.*, (SELECT COUNT(*) FROM produto WHERE categoria_id = c.id) AS total_itens
    FROM categoria c
    ORDER BY c.nome ASC
");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - SylviArtes Admin</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../../imagens/logo_sylviartes.png">
    <link rel="stylesheet" href="../admin_style.css?v=2">
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
            background: linear-gradient(135deg, #d66d7f, #bf5b6d);
            color: #fff !important; padding: 11px 22px;
            border-radius: 999px; text-decoration: none;
            font-weight: 600; font-size: 14px;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }
        .btn-add:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(201,95,122,0.30); }

        /* Cards de categoria */
        .cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .cat-card {
            background: #fff;
            border: 1px solid #f0e3e7;
            border-radius: 14px;
            padding: 22px;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        .cat-card::before {
            content: '';
            position: absolute; top: 0; left: 0;
            width: 4px; height: 100%;
            background: #d66d7f;
            transform: scaleY(0);
            transition: transform 0.2s;
            transform-origin: bottom;
        }
        .cat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(214,109,127,0.10); }
        .cat-card:hover::before { transform: scaleY(1); }

        .cat-card .icone {
            width: 42px; height: 42px;
            background: #fff8fa; color: #d66d7f;
            border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 18px;
            margin-bottom: 12px;
        }
        .cat-card h4 {
            margin: 0 0 4px;
            font-size: 17px;
            color: #2d3436;
            font-weight: 600;
        }
        .cat-card .qtd {
            color: #888; font-size: 13px;
        }
        .cat-card .desc {
            color: #666; font-size: 13.5px;
            margin: 12px 0;
            line-height: 1.5;
            min-height: 42px;
        }
        .cat-card .acoes {
            display: flex; gap: 8px; align-items: stretch;
            padding-top: 14px; border-top: 1px solid #f0e3e7;
        }
        /* O form do botão apagar passa a ser um item flex compacto */
        .cat-card .acoes form { flex: 0 0 auto; display: flex; margin: 0; }
        .cat-card .acoes a, .cat-card .acoes button {
            padding: 9px 12px;
            border-radius: 8px; text-decoration: none;
            font-weight: 600; font-size: 13px; cursor: pointer;
            font-family: inherit;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            transition: all 0.15s;
        }
        .btn-cat-edit  { flex: 1; background: #fff8fa; color: #d66d7f; border: 1px solid #f0c8d2; }
        .btn-cat-edit:hover { background: #d66d7f; color: #fff; }
        .btn-cat-del   { background: #fff; color: #ef4444; border: 1px solid #fecaca; padding: 9px 14px; }
        .btn-cat-del:hover { background: #ef4444; color: #fff; }

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
                <h1><i class="fas fa-folder"></i> Categorias</h1>
                <div class="total">
                    <?= count($categorias) ?> <?= count($categorias) === 1 ? 'categoria' : 'categorias' ?>
                    · Tipos de bordados (Babetes, Fraldas, Toalhas...)
                </div>
            </div>
            <a href="create.php" class="btn-add">
                <i class="fas fa-plus"></i> Nova categoria
            </a>
        </div>

        <?php if (empty($categorias)): ?>
            <div class="vazio">
                <i class="fas fa-folder-open"></i>
                <h3 style="margin:0 0 6px; font-family:'Playfair Display',serif; color:#2d3436;">Sem categorias</h3>
                <p>Crie categorias para organizar o portfólio (ex: Babetes, Fraldas, Toalhas).</p>
                <a href="create.php" class="btn-add" style="margin-top:14px;">
                    <i class="fas fa-plus"></i> Criar primeira categoria
                </a>
            </div>
        <?php else: ?>
            <div class="cat-grid">
                <?php foreach ($categorias as $row): ?>
                    <div class="cat-card">
                        <div class="icone"><i class="fas fa-folder"></i></div>
                        <h4><?= htmlspecialchars($row['nome']) ?></h4>
                        <div class="qtd">
                            <i class="fas fa-image"></i>
                            <?= (int)$row['total_itens'] ?>
                            <?= (int)$row['total_itens'] === 1 ? 'item' : 'itens' ?> no portfólio
                        </div>
                        <div class="desc">
                            <?= !empty($row['descricao']) ? htmlspecialchars($row['descricao']) : '<em style="color:#bbb;">Sem descrição</em>' ?>
                        </div>
                        <div class="acoes">
                            <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn-cat-edit">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Apagar a categoria \'<?= htmlspecialchars(addslashes($row['nome'])) ?>\'? Itens associados ficam sem categoria.');">
                                <?= csrf_input() ?>
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn-cat-del">
                                    <i class="fas fa-trash"></i>
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