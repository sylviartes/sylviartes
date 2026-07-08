<?php
/**
 * =============================================================================
 *  HISTÓRICO DE ENCOMENDAS DO CLIENTE
 * =============================================================================
 *
 *  Lista todos os pedidos feitos pelo cliente autenticado, ordenados do mais
 *  recente para o mais antigo. Cada linha mostra: nº, data, total, estado do
 *  pagamento e estado do pedido. Para ver os detalhes (produtos, comprovativo,
 *  cancelar, etc.) o cliente clica em "Ver" e vai para encomenda.php?id=X.
 *
 *  IMPORTANTE: A query usa `WHERE p.utilizador_id = ?` para garantir que cada
 *  cliente só vê os SEUS próprios pedidos - nunca os de outras pessoas.
 * =============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/pedidos.php';   // numero_pedido_cliente()

$clienteId = $_SESSION['cliente_id'];

// JOIN com pagamento (LEFT porque pedidos antigos podem não ter linha em `pagamento`)
$stmt = $conn->prepare("
    SELECT p.id, p.data, p.estado, p.valor_total, p.tipo_entrega,
           pg.metodo, pg.estado_pagamento
    FROM pedido p
    LEFT JOIN pagamento pg ON pg.pedido_id = p.id
    WHERE p.utilizador_id = ?
    ORDER BY p.data DESC
");
$stmt->execute([$clienteId]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/** Converte o ENUM da BD em texto amigável para mostrar ao utilizador. */
function estadoLabel($estado) {
    $labels = [
        'aguarda_orcamento' => 'Aguarda Orçamento',
        'em_analise' => 'Em análise',
        'aguarda_pagamento' => 'Aguarda pagamento',
        'em_producao' => 'Em produção',
        'concluido' => 'Concluído',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ];
    return $labels[$estado] ?? $estado;
}

/** Converte o ENUM do estado do pagamento em texto amigável. */
function pagamentoLabel($estado) {
    $labels = [
        'analise_pagamento' => 'A validar',
        'validado' => 'Pago',
        'recusado' => 'Recusado',
    ];
    return $labels[$estado] ?? '-';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>As minhas encomendas - SylviArtes</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../imagens/logo_sylviartes.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cliente_style.css?v=<?= @filemtime(__DIR__ . '/cliente_style.css') ?: 1 ?>">
    <style>
        /* ===== TELEMOVEL: cada encomenda vira um cartao empilhado =====
           Ate 640px a tabela larga obrigava a scroll lateral. Escondemos o
           cabecalho e mostramos cada linha como um cartao, com o nome da coluna
           (data-label) a esquerda e o valor a direita. Assim aparece tudo no ecra. */
        @media (max-width: 640px) {
            .cli-orders-table thead { display: none; }
            .cli-orders-table,
            .cli-orders-table tbody,
            .cli-orders-table tr,
            .cli-orders-table td { display: block; width: 100%; box-sizing: border-box; }

            .cli-orders-table tr {
                border: 1px solid #f0e3e7; border-radius: 12px;
                padding: 10px 14px; margin-bottom: 14px; background: #fff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            }
            .cli-orders-table td {
                display: flex; justify-content: space-between; align-items: center;
                gap: 12px; padding: 8px 0; border: none;
                border-bottom: 1px solid #f5f0f2; text-align: right;
            }
            .cli-orders-table td:last-child { border-bottom: none; }
            .cli-orders-table td::before {
                content: attr(data-label);
                font-weight: 600; color: #d66d7f; font-size: 11.5px;
                text-transform: uppercase; letter-spacing: 0.4px;
                text-align: left; flex-shrink: 0;
            }
            /* Botao "Ver" em largura total, sem etiqueta */
            .cli-orders-table td.cli-cell-ver { padding-top: 10px; }
            .cli-orders-table td.cli-cell-ver::before { content: none; }
            .cli-orders-table td.cli-cell-ver .cli-btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="cli-wrapper">
        <a href="index.php" class="cli-back">← Minha Conta</a>

        <div class="cli-section">
            <h2>As minhas encomendas</h2>

            <?php if (empty($pedidos)): ?>
                <div class="cli-empty" style="padding:50px 20px;">
                    <div style="font-size:64px; opacity:0.3; margin-bottom:12px;">📦</div>
                    <h3 style="color:#2d3436; font-family:'Playfair Display',serif;">Ainda não tem encomendas</h3>
                    <p style="color:#636e72; max-width:380px; margin:8px auto 22px;">
                        Quando fizer a sua primeira compra, ela aparecerá aqui para acompanhar.
                    </p>
                    <a href="../catalogo.php" class="cli-btn">🛍️ Explorar catálogo</a>
                </div>
            <?php else: ?>
                <table class="cli-table cli-orders-table">
                    <thead>
                        <tr>
                            <th>Nº</th>
                            <th>Data</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $p): ?>
                            <tr>
                                <td data-label="Nº"><strong>#<?php echo numero_pedido_cliente($conn, (int)$p['id'], (int)$clienteId); ?></strong></td>
                                <td data-label="Data"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($p['data']))); ?></td>
                                <td data-label="Total"><?php echo number_format($p['valor_total'], 2, ',', '.'); ?> €</td>
                                <td data-label="Pagamento">
                                    <?php if ($p['estado_pagamento']): ?>
                                        <span class="cli-badge b-<?php echo htmlspecialchars($p['estado_pagamento']); ?>">
                                            <?php echo htmlspecialchars(pagamentoLabel($p['estado_pagamento'])); ?>
                                        </span>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td data-label="Estado">
                                    <span class="cli-badge b-<?php echo htmlspecialchars($p['estado']); ?>">
                                        <?php echo htmlspecialchars(estadoLabel($p['estado'])); ?>
                                    </span>
                                </td>
                                <td class="cli-cell-ver">
                                    <a href="encomenda.php?id=<?php echo (int)$p['id']; ?>" class="cli-btn cli-btn-ghost">Ver</a>
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
