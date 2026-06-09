<?php
/**
 * =============================================================================
 *  ADMIN - Moderação de Avaliações
 * =============================================================================
 *
 *  Página onde o administrador vê todas as avaliações deixadas pelos clientes
 *  e decide aprovar (mostrar no produto) ou rejeitar/eliminar.
 *
 *  Mostra duas listas:
 *    1. Pendentes (aprovado = 0)  → precisam de decisão
 *    2. Aprovadas (aprovado = 1)  → já visíveis no site
 * =============================================================================
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

// Verifica se a SQL alter_avaliacoes.sql foi aplicada (precisa da coluna produto_id)
$temColunaProduto = false;
try {
    $stmt = $conn->query("SHOW COLUMNS FROM avaliacao LIKE 'produto_id'");
    $temColunaProduto = (bool)$stmt->fetch();
} catch (Exception $e) { /* ignora */ }

if (!$temColunaProduto) {
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Avaliações - SylviArtes Admin</title>
        <!-- Favicon: logotipo no separador do browser -->
        <link rel="icon" type="image/png" href="../../imagens/logo_sylviartes.png">
        <link rel="stylesheet" href="../admin_style.css?v=2">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
        <style>body { font-family: 'Poppins', sans-serif; }</style>
    </head>
    <body class="admin-body">
        <?php require_once __DIR__ . '/../sidebar.php'; ?>
        <div class="main-content">
            <h1 style="font-family:'Playfair Display',serif; color:#2d3436; margin-bottom:20px;">
                <i class="fas fa-star"></i> Moderar Avaliações
            </h1>
            <div style="background:#fff8e1; border:1px solid #ffe082; border-left:4px solid #f59e0b; border-radius:12px; padding:24px; max-width:780px;">
                <h3 style="color:#92400e; margin:0 0 12px;">
                    <i class="fas fa-exclamation-triangle"></i> Configuração necessária
                </h3>
                <p style="color:#555; margin:0 0 14px;">
                    A funcionalidade de avaliações requer aplicar a SQL <code style="background:#fff; padding:2px 6px; border-radius:4px;">docs/db/alter_avaliacoes.sql</code> à base de dados.
                </p>
                <p style="color:#555; margin:0 0 14px;">
                    Abra o phpMyAdmin → BD <strong>sylviartes</strong> → SQL → cole este código:
                </p>
                <pre style="background:#fff; padding:16px; border-radius:8px; overflow-x:auto; font-size:13px; border:1px solid #f0e3e7; color:#333; line-height:1.5;">ALTER TABLE avaliacao
  ADD COLUMN produto_id INT NULL AFTER utilizador_id,
  ADD INDEX idx_aval_produto (produto_id);
ALTER TABLE avaliacao
  ADD UNIQUE KEY uniq_aval_user_produto (utilizador_id, produto_id);</pre>
                <p style="color:#888; font-size:13px; margin:14px 0 0;">
                    Após aplicar a SQL, atualize esta página.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// === AÇÕES (aprovar / rejeitar) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $accao = $_POST['accao'] ?? '';
    $avalId = (int)($_POST['id'] ?? 0);

    if ($avalId > 0) {
        if ($accao === 'aprovar') {
            $stmt = $conn->prepare("UPDATE avaliacao SET aprovado = 1 WHERE id = ?");
            $stmt->execute([$avalId]);
        } elseif ($accao === 'rejeitar') {
            // Em vez de só desaprovar, eliminamos - o cliente pode submeter nova
            $stmt = $conn->prepare("DELETE FROM avaliacao WHERE id = ?");
            $stmt->execute([$avalId]);
        } elseif ($accao === 'desaprovar') {
            // Volta a esconder uma avaliação aprovada (sem eliminar)
            $stmt = $conn->prepare("UPDATE avaliacao SET aprovado = 0 WHERE id = ?");
            $stmt->execute([$avalId]);
        }
    }
    header("Location: index.php");
    exit;
}

// === Carrega avaliações pendentes ===
$stmt = $conn->query("
    SELECT a.id, a.estrelas, a.comentario, a.data, a.pedido_id,
           u.nome AS cliente, u.email,
           p.id AS produto_id, p.nome AS produto
    FROM avaliacao a
    JOIN utilizador u ON u.id = a.utilizador_id
    LEFT JOIN produto p ON p.id = a.produto_id
    WHERE a.aprovado = 0
    ORDER BY a.data DESC
");
$pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Carrega avaliações aprovadas ===
$stmt = $conn->query("
    SELECT a.id, a.estrelas, a.comentario, a.data, a.pedido_id,
           u.nome AS cliente, u.email,
           p.id AS produto_id, p.nome AS produto
    FROM avaliacao a
    JOIN utilizador u ON u.id = a.utilizador_id
    LEFT JOIN produto p ON p.id = a.produto_id
    WHERE a.aprovado = 1
    ORDER BY a.data DESC
    LIMIT 100
");
$aprovadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/** Devolve HTML de N estrelas cheias (1 a 5). */
function estrelas_html(int $n): string
{
    $html = "<span style='color:#f5b301;'>";
    $html .= str_repeat('★', $n);
    $html .= "<span style='color:#ddd;'>" . str_repeat('★', 5 - $n) . "</span>";
    $html .= "</span>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <!-- Essencial para o layout responsivo (cartoes no telemovel) funcionar -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliações - Admin SylviArtes</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../../imagens/logo_sylviartes.png">
    <link rel="stylesheet" href="../admin_style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .aval-tabela { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
        .aval-tabela th { background: #f8f9fa; padding: 12px; text-align: left; font-size: 12px; text-transform: uppercase; color: #d66d7f; }
        .aval-tabela td { padding: 14px 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        .aval-comentario { color: #555; font-style: italic; max-width: 400px; }
        .btn-aprovar { background: #28a745; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; margin-right: 5px; }
        .btn-rejeitar { background: #dc3545; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-desaprovar { background: #6c757d; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .secao-vazia { text-align: center; padding: 30px; color: #999; font-style: italic; }

        /* ===== TELEMOVEL: cada linha da tabela vira um cartao empilhado =====
           Ate 768px de largura as tabelas sao dificeis de ler/gerir, por isso
           escondemos o cabecalho e mostramos cada avaliacao como um cartao, com
           o nome do campo (data-label) a esquerda e o valor a direita. */
        @media (max-width: 768px) {
            /* Anula o scroll horizontal generico do admin para estas tabelas */
            .card .aval-tabela { display: block; overflow: visible; min-width: 0; }
            .aval-tabela thead { display: none; }
            .aval-tabela tbody,
            .aval-tabela tr,
            .aval-tabela td { display: block; width: 100%; box-sizing: border-box; }

            /* Cada linha = um cartao */
            .aval-tabela tr {
                border: 1px solid #f0e3e7;
                border-radius: 12px;
                padding: 10px 14px;
                margin-bottom: 14px;
                background: #fff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            }

            /* Cada celula: etiqueta a esquerda, valor a direita */
            .aval-tabela td {
                border: none;
                padding: 8px 0;
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 14px;
                text-align: right;
                border-bottom: 1px solid #f5f5f5;
            }
            .aval-tabela td:last-child { border-bottom: none; }
            .aval-tabela td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #d66d7f;
                text-transform: uppercase;
                font-size: 11px;
                letter-spacing: 0.5px;
                text-align: left;
                flex-shrink: 0;
            }

            /* Comentario ocupa a largura toda, alinhado a esquerda */
            .aval-tabela td.aval-comentario {
                flex-direction: column;
                text-align: left;
                max-width: none;
            }

            /* Coluna de acoes: botoes empilhados em largura total */
            .aval-tabela td.aval-acoes { flex-direction: column; gap: 8px; }
            .aval-tabela td.aval-acoes form { display: block !important; width: 100%; margin: 0; }
            .aval-tabela td.aval-acoes button { width: 100%; justify-content: center; margin: 0; }
        }
    </style>
</head>
<body class="admin-body">
    <?php require_once __DIR__ . '/../sidebar.php'; ?>

    <div class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
            <h1><i class="fas fa-star"></i> Moderar Avaliações</h1>
        </div>

        <!-- ============================================================ -->
        <!-- PENDENTES                                                     -->
        <!-- ============================================================ -->
        <div class="card">
            <h3>
                <i class="fas fa-hourglass-half"></i> Pendentes
                <span style="background:#dc3545; color:#fff; border-radius:999px; padding:2px 10px; font-size:12px; margin-left:8px;">
                    <?= count($pendentes) ?>
                </span>
            </h3>

            <?php if (empty($pendentes)): ?>
                <p class="secao-vazia">✓ Sem avaliações pendentes - bom trabalho!</p>
            <?php else: ?>
                <table class="aval-tabela">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Produto</th>
                            <th>Estrelas</th>
                            <th>Comentário</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendentes as $a): ?>
                            <tr>
                                <td data-label="Cliente">
                                    <strong><?= htmlspecialchars($a['cliente']) ?></strong><br>
                                    <small style="color:#666;"><?= htmlspecialchars($a['email']) ?></small>
                                </td>
                                <td data-label="Produto">
                                    <?php if ($a['produto']): ?>
                                        <a href="../../produto.php?id=<?= (int)$a['produto_id'] ?>" target="_blank">
                                            <?= htmlspecialchars($a['produto']) ?>
                                        </a>
                                    <?php elseif (!empty($a['pedido_id'])): ?>
                                        <em style="color:#777;">Encomenda #<?= (int)$a['pedido_id'] ?></em>
                                    <?php else: ?>
                                        <em style="color:#999;">Loja em geral</em>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Estrelas"><?= estrelas_html((int)$a['estrelas']) ?></td>
                                <td class="aval-comentario" data-label="Comentário">
                                    <?= !empty($a['comentario']) ? nl2br(htmlspecialchars($a['comentario'])) : '<em style="color:#999;">(sem comentário)</em>' ?>
                                </td>
                                <td data-label="Data"><?= date('d/m/Y H:i', strtotime($a['data'])) ?></td>
                                <td class="aval-acoes" data-label="Ações">
                                    <form method="POST" style="display:inline;">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <input type="hidden" name="accao" value="aprovar">
                                        <button type="submit" class="btn-aprovar">
                                            <i class="fas fa-check"></i> Aprovar
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Rejeitar esta avaliação? (será eliminada)');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <input type="hidden" name="accao" value="rejeitar">
                                        <button type="submit" class="btn-rejeitar">
                                            <i class="fas fa-times"></i> Rejeitar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- ============================================================ -->
        <!-- APROVADAS                                                     -->
        <!-- ============================================================ -->
        <div class="card" style="margin-top: 30px;">
            <h3><i class="fas fa-check-circle"></i> Aprovadas (últimas 100)</h3>

            <?php if (empty($aprovadas)): ?>
                <p class="secao-vazia">Ainda não há avaliações aprovadas.</p>
            <?php else: ?>
                <table class="aval-tabela">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Produto</th>
                            <th>Estrelas</th>
                            <th>Comentário</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aprovadas as $a): ?>
                            <tr>
                                <td data-label="Cliente"><strong><?= htmlspecialchars($a['cliente']) ?></strong></td>
                                <td data-label="Produto">
                                    <?php if ($a['produto']): ?>
                                        <a href="../../produto.php?id=<?= (int)$a['produto_id'] ?>" target="_blank">
                                            <?= htmlspecialchars($a['produto']) ?>
                                        </a>
                                    <?php elseif (!empty($a['pedido_id'])): ?>
                                        <em style="color:#777;">Encomenda #<?= (int)$a['pedido_id'] ?></em>
                                    <?php else: ?>
                                        <em style="color:#999;">Loja em geral</em>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Estrelas"><?= estrelas_html((int)$a['estrelas']) ?></td>
                                <td class="aval-comentario" data-label="Comentário">
                                    <?= !empty($a['comentario']) ? nl2br(htmlspecialchars($a['comentario'])) : '<em style="color:#999;">(sem comentário)</em>' ?>
                                </td>
                                <td data-label="Data"><?= date('d/m/Y', strtotime($a['data'])) ?></td>
                                <td class="aval-acoes" data-label="Ações">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Esconder esta avaliação?');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <input type="hidden" name="accao" value="desaprovar">
                                        <button type="submit" class="btn-desaprovar">
                                            <i class="fas fa-eye-slash"></i> Esconder
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
