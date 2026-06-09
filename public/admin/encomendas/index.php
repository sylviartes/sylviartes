<?php
// 1. Inclusão da Base de Dados e Autenticação
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../config/csrf.php';

// --- CONFIGURAÇÕES ---
$estadosValidos = ['aguarda_orcamento', 'em_analise', 'aguarda_pagamento', 'em_producao', 'concluido', 'entregue', 'cancelado'];

$estadosLabels = [
    'aguarda_orcamento' => 'Aguarda Orçamento',
    'em_analise' => 'Em Análise',
    'aguarda_pagamento' => 'Aguarda Pagamento',
    'em_producao' => 'Em Produção',
    'concluido' => 'Concluído',
    'entregue' => 'Entregue',
    'cancelado' => 'Cancelado'
];

// --- LÓGICA DE ATUALIZAÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_estado'])) {
    csrf_validate();
    $novo_estado = trim($_POST['estado'] ?? '');
    $pedido_id = (int)($_POST['pedido_id'] ?? 0);

    if ($pedido_id > 0 && in_array($novo_estado, $estadosValidos, true)) {
        $stmt = $conn->prepare("UPDATE pedido SET estado = :estado WHERE id = :id");
        $stmt->execute([
            ':estado' => $novo_estado,
            ':id' => $pedido_id
        ]);
        header("Location: index.php?status=" . ($_GET['status'] ?? 'todos'));
        exit;
    }
}

// --- FUNÇÃO PARA ESTILO DOS BADGES ---
function getStatusBadge($status) {
    switch ($status) {
        case 'aguarda_orcamento': return 'estado-orcamento';
        case 'em_analise': return 'estado-analise';
        case 'aguarda_pagamento': return 'estado-pagamento';
        case 'em_producao': return 'estado-producao';
        case 'concluido': 
        case 'entregue': return 'estado-concluido';
        case 'cancelado': return 'estado-cancelado';
        default: return '';
    }
}

// --- LÓGICA DE FILTRO E CONSULTA ---
$filtro = trim($_GET['status'] ?? '');
$params = [];

$sql = "
    SELECT p.*, u.nome AS nome_cliente, u.email
    FROM pedido p
    JOIN utilizador u ON p.utilizador_id = u.id
    WHERE 1=1
";

if ($filtro !== '' && $filtro !== 'todos' && in_array($filtro, $estadosValidos, true)) {
    $sql .= " AND p.estado = :estado";
    $params[':estado'] = $filtro;
}

$sql .= " ORDER BY p.data DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$encomendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
// Conta o nº de pedidos por estado para os badges nos filtros
$contagens = [];
try {
    $stmtCnt = $conn->query("SELECT estado, COUNT(*) AS qtd FROM pedido GROUP BY estado");
    foreach ($stmtCnt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $contagens[$r['estado']] = (int)$r['qtd'];
    }
} catch (Exception $e) { /* ignora */ }
$totalEncomendas = array_sum($contagens);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encomendas - SylviArtes Admin</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../../imagens/logo_sylviartes.png">
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .pagina-header {
            margin-bottom: 24px; padding-bottom: 18px;
            border-bottom: 1px solid #f0e3e7;
        }
        .pagina-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px; color: #2d3436; margin: 0;
            font-weight: 600;
        }
        .pagina-header .total {
            color: #888; font-size: 14px; margin-top: 4px;
        }

        .filtros { display: flex; gap: 8px; margin-bottom: 22px; flex-wrap: wrap; }
        .filtros a {
            padding: 8px 16px; border-radius: 999px; text-decoration: none;
            font-weight: 500; font-size: 13px; transition: all 0.2s;
            background: #fff; color: #555;
            border: 1px solid #e8e8e8;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .filtros a:hover { border-color: #d66d7f; }
        .filtros a.active {
            background: #d66d7f; color: #fff; border-color: #d66d7f;
        }
        .filtros .badge-count {
            background: rgba(255,255,255,0.25); color: inherit;
            padding: 1px 8px; border-radius: 999px; font-size: 11px;
            font-weight: 700;
        }
        .filtros a:not(.active) .badge-count {
            background: #f0e3e7; color: #d66d7f;
        }

        .panel-tabela {
            background: #fff; border-radius: 14px; padding: 0; overflow: hidden;
            border: 1px solid #f0e3e7; box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        /* Tabela limpa */
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table thead th {
            background: #fdf6f8;
            color: #888;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid #f0e3e7;
        }
        .admin-table tbody td {
            padding: 16px 18px;
            border-bottom: 1px solid #f5e9ec;
            vertical-align: middle;
            font-size: 14px;
        }
        .admin-table tbody tr:last-child td { border-bottom: none; }
        .admin-table tbody tr { transition: background 0.15s; }
        .admin-table tbody tr:hover { background: #fdf9fa; }

        .id-cell {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #d66d7f;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.3px;
        }
        .cliente-cell { line-height: 1.4; }
        .cliente-cell .nome { font-weight: 600; color: #2d3436; }
        .cliente-cell .email { color: #999; font-size: 12.5px; }
        .data-cell { color: #666; font-size: 13px; line-height: 1.4; }
        .data-cell .hora { color: #aaa; font-size: 12px; }
        .total-cell { font-weight: 700; color: #2d3436; font-size: 15px; white-space: nowrap; }

        /* Select de estado discreto que se "esconde" como badge */
        .estado-select {
            padding: 6px 28px 6px 12px;
            border-radius: 999px;
            font-size: 11.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.4px;
            font-family: inherit;
            border: 1px solid transparent;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23999' d='M5 6L0 0h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            transition: all 0.15s;
        }
        .estado-select:hover { filter: brightness(0.97); }
        .estado-select:focus { outline: 2px solid rgba(214,109,127,0.20); outline-offset: 1px; }

        .estado-aguarda_orcamento { background-color: #fce7f3; color: #9d174d; }
        .estado-em_analise        { background-color: #fef3c7; color: #92400e; }
        .estado-aguarda_pagamento { background-color: #fed7aa; color: #9a3412; }
        .estado-em_producao       { background-color: #dbeafe; color: #1e40af; }
        .estado-concluido         { background-color: #d1fae5; color: #065f46; }
        .estado-entregue          { background-color: #cffafe; color: #155e75; }
        .estado-cancelado         { background-color: #fee2e2; color: #991b1b; }

        /* Botão de ver detalhes - compacto, ícone + texto numa linha */
        .btn-ver {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #fff;
            color: #d66d7f;
            border: 1px solid #f0c8d2;
            border-radius: 8px;
            font-size: 13px; font-weight: 600;
            text-decoration: none;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .btn-ver:hover {
            background: #d66d7f;
            color: #fff;
            border-color: #d66d7f;
            box-shadow: 0 6px 16px rgba(214, 109, 127, 0.20);
        }

        .acoes-cell { text-align: right; white-space: nowrap; }
        .vazio-msg {
            text-align: center; padding: 60px 20px; color: #888; font-style: italic;
        }
    </style>
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main-content">
    <div class="pagina-header">
        <h1><i class="fas fa-box-open"></i> Gestão de Encomendas</h1>
        <div class="total"><?= $totalEncomendas ?> <?= $totalEncomendas === 1 ? 'encomenda' : 'encomendas' ?> no total</div>
    </div>

    <div class="filtros">
        <a href="?status=todos" class="<?= ($filtro === '' || $filtro === 'todos') ? 'active' : '' ?>">
            Todos <span class="badge-count"><?= $totalEncomendas ?></span>
        </a>
        <?php foreach ($estadosLabels as $key => $label): ?>
            <?php $cnt = $contagens[$key] ?? 0; if ($cnt === 0 && $filtro !== $key) continue; ?>
            <a href="?status=<?= $key ?>" class="<?= $filtro === $key ? 'active' : '' ?>">
                <?= $label ?> <span class="badge-count"><?= $cnt ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="panel-tabela">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Data</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th style="text-align:right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($encomendas): ?>
                    <?php foreach ($encomendas as $row):
                        $estado = $row['estado'] ?? '';
                        $primeiroNome = explode(' ', $row['nome_cliente'])[0] ?? '';
                    ?>
                        <tr>
                            <td class="id-cell">#<?= $row['id'] ?></td>
                            <td class="cliente-cell">
                                <div class="nome"><?= htmlspecialchars($row['nome_cliente']) ?></div>
                                <div class="email"><?= htmlspecialchars($row['email']) ?></div>
                            </td>
                            <td class="data-cell">
                                <?= date('d/m/Y', strtotime($row['data'])) ?>
                                <div class="hora"><?= date('H:i', strtotime($row['data'])) ?></div>
                            </td>
                            <td class="total-cell"><?= number_format($row['valor_total'], 2, ',', ' ') ?> €</td>
                            <td>
                                <!-- Select que parece badge: muda de cor conforme valor seleccionado e auto-submete -->
                                <form method="POST" action="" style="display:inline;">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="pedido_id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="atualizar_estado" value="1">
                                    <select name="estado"
                                            class="estado-select estado-<?= htmlspecialchars($estado) ?>"
                                            onchange="this.form.submit()">
                                        <?php foreach ($estadosLabels as $val => $label): ?>
                                            <option value="<?= $val ?>" <?= $estado === $val ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td class="acoes-cell">
                                <a href="view.php?id=<?= $row['id'] ?>" class="btn-ver">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="vazio-msg">
                            <i class="fas fa-inbox" style="font-size:36px; color:#d4a3ad; display:block; margin-bottom:8px;"></i>
                            Nenhuma encomenda encontrada.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>