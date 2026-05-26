<?php
/**
 * =============================================================================
 *  ADMIN — Detalhe de Encomenda
 * =============================================================================
 *
 *  Painel onde o admin vê toda a informação de um pedido específico:
 *  cliente, produtos, total, estado, e gere o pagamento.
 *
 *  Acções disponíveis (só para o admin):
 *    1. VALIDAR pagamento manual (transferência) — após confirmar a entrada
 *       do dinheiro na conta bancária. Avança automaticamente o pedido para
 *       'em_producao'.
 *    2. RECUSAR pagamento — se o comprovativo for inválido.
 *    3. VER COMPROVATIVO — descarrega o BLOB diretamente como ficheiro.
 * =============================================================================
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';   // Exige login admin
require_once __DIR__ . '/../../../config/csrf.php';

// Sem ID → volta à lista
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$pedido_id = (int)$_GET['id'];

// =========================================================================
// AÇÃO: Admin atualiza o valor_total do pedido (orçamento finalizado)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accao_orcamento']) && $_POST['accao_orcamento'] === 'atualizar_preco') {
    csrf_validate();
    $novoValor = (float)str_replace(',', '.', $_POST['valor_total'] ?? 0);
    if ($novoValor > 0 && $novoValor < 100000) {
        $stmt = $conn->prepare("UPDATE pedido SET valor_total = ? WHERE id = ?");
        $stmt->execute([$novoValor, $pedido_id]);

        // Se houver linha em pagamento, atualiza também
        $stmt = $conn->prepare("UPDATE pagamento SET valor = ? WHERE pedido_id = ?");
        $stmt->execute([$novoValor, $pedido_id]);

        header("Location: view.php?id=$pedido_id&preco_atualizado=1");
    } else {
        header("Location: view.php?id=$pedido_id&erro=valor_invalido");
    }
    exit;
}

// =========================================================================
// AÇÃO: Admin valida/recusa o pagamento
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accao_pagamento'])) {
    csrf_validate();
    // Operador ternário: 'validar' → 'validado', qualquer outro → 'recusado'
    $novoEstado = $_POST['accao_pagamento'] === 'validar' ? 'validado' : 'recusado';

    // Atualiza o estado do pagamento
    $stmt = $conn->prepare("UPDATE pagamento SET estado_pagamento = ? WHERE pedido_id = ?");
    $stmt->execute([$novoEstado, $pedido_id]);

    if ($novoEstado === 'validado') {
        // Pagamento confirmado → avança o pedido para produção
        // (só se ainda estiver num estado inicial)
        $stmt = $conn->prepare("
            UPDATE pedido SET estado='em_producao'
            WHERE id=? AND estado IN ('em_analise', 'aguarda_pagamento')
        ");
        $stmt->execute([$pedido_id]);
    }

    // Redirect (Post-Redirect-Get) com mensagem na URL
    header("Location: view.php?id=" . $pedido_id . "&pag_msg=" . $novoEstado);
    exit;
}

// =========================================================================
// SERVIR IMAGEM DE INSPIRAÇÃO ENVIADA PELO CLIENTE
// =========================================================================
// Acedido por: view.php?id=X&inspiracao_id=N
if (isset($_GET['inspiracao_id'])) {
    $insp_id = (int)$_GET['inspiracao_id'];
    try {
        $stmt = $conn->prepare("SELECT imagem FROM pedido_inspiracao WHERE id = ? AND pedido_id = ?");
        $stmt->execute([$insp_id, $pedido_id]);
        $blob = $stmt->fetchColumn();
        if ($blob) {
            $blob = is_resource($blob) ? stream_get_contents($blob) : $blob;
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            header("Content-Type: " . $finfo->buffer($blob));
            echo $blob;
        }
    } catch (Exception $e) { /* tabela pode não existir ainda */ }
    exit;
}

// =========================================================================
// SERVIR O COMPROVATIVO COMO DOWNLOAD
// =========================================================================
if (isset($_GET['comprovativo']) && $_GET['comprovativo'] === '1') {
    $stmt = $conn->prepare("SELECT comprovativo FROM pagamento WHERE pedido_id = ?");
    $stmt->execute([$pedido_id]);
    $blob = $stmt->fetchColumn();

    if ($blob) {
        // O PDO pode devolver o BLOB como stream — converte para string se preciso
        $blob = is_resource($blob) ? stream_get_contents($blob) : $blob;

        // Deteta o tipo MIME real do ficheiro pelos magic bytes
        // (importante para o browser saber se é PDF, JPG, etc.)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        header("Content-Type: " . $finfo->buffer($blob));
        echo $blob;
    }
    exit;  // Não continua para o HTML
}

try {
    // 1. Buscar dados da encomenda, utilizador e pagamento
    $stmt = $conn->prepare("SELECT p.*,
                                   u.nome, u.email, u.telefone, u.codigo_postal, u.localidade,
                                   pg.metodo as metodo_pagamento, pg.estado_pagamento,
                                   (pg.comprovativo IS NOT NULL) AS tem_comprovativo
                           FROM pedido p
                           JOIN utilizador u ON p.utilizador_id = u.id
                           LEFT JOIN pagamento pg ON p.id = pg.pedido_id
                           WHERE p.id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        die("Encomenda não encontrada.");
    }

    // 2. Buscar os itens e a PRIMEIRA imagem associada (Subquery mais segura que o GROUP BY)
    $stmt_itens = $conn->prepare("
        SELECT dp.*, p.nome as nome_produto, 
               (SELECT imagem FROM produto_imagem WHERE produto_id = p.id ORDER BY ordem ASC LIMIT 1) as imagem
        FROM detalhe_pedido dp 
        LEFT JOIN produto p ON dp.produto_id = p.id 
        WHERE dp.pedido_id = ?
    "); 
    $stmt_itens->execute([$pedido_id]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Admin: erro ao carregar encomenda: " . $e->getMessage());
    die('<p style="font-family:sans-serif;text-align:center;margin-top:80px;color:#555;">Ocorreu um erro interno. <a href="index.php">Voltar</a></p>');
}

$estadosLabels = [
    'aguarda_orcamento' => 'Aguarda Orçamento',
    'em_analise' => 'Em Análise',
    'aguarda_pagamento' => 'Aguarda Pagamento',
    'em_producao' => 'Em Produção',
    'concluido' => 'Concluído',
    'entregue' => 'Entregue',
    'cancelado' => 'Cancelado'
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes Encomenda #<?php echo $pedido_id; ?></title>
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-box { background: #f8f9fa; padding: 20px; border-radius: 12px; border: 2px solid #eee; }
        .info-label { font-size: 0.8em; color: #d66d7f; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .info-value { font-size: 1.1em; font-weight: bold; color: #333; margin-top: 5px; }
        .img-item { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 2px solid #eee; }
        .btn-voltar { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 25px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
        th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .badge { background: #d66d7f; color: white; padding: 4px 10px; border-radius: 15px; font-size: 0.9em; }
        
        /* AQUI ESTÁ A ALTERAÇÃO DA COR PARA O ROSA */
        .badge-pagamento { background: #d66d7f; color: white; padding: 3px 8px; border-radius: 10px; font-size: 0.8em; margin-left: 10px;}
    </style>
</head>
<body class="admin-body">

    <?php require_once __DIR__ . '/../sidebar.php'; ?>

    <div class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
            <h1><i class="fas fa-box-open"></i> Encomenda #<?php echo $pedido_id; ?></h1>
            <a href="index.php" class="btn-voltar"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>

        <div class="card">
            <h3><i class="fas fa-user"></i> Informações do Cliente</h3>
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Cliente</div>
                    <div class="info-value"><?php echo htmlspecialchars($pedido['nome'] ?? 'N/A'); ?></div>
                    <div style="color:#666;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($pedido['email'] ?? 'N/A'); ?></div>
                    <div style="color:#666;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($pedido['telefone'] ?? '---'); ?></div>
                </div>

                <div class="info-box">
                    <div class="info-label">Estado da Encomenda</div>
                    <div class="info-value">
                        <span class="badge"><?php echo $estadosLabels[$pedido['estado']] ?? $pedido['estado'] ?? 'N/A'; ?></span>
                    </div>
                    <div style="margin-top:10px;">
                        <i class="fas fa-calendar"></i> Data do Pedido: <?php echo isset($pedido['data']) ? date('d/m/Y H:i', strtotime($pedido['data'])) : 'N/A'; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3><i class="fas fa-truck"></i> Detalhes de Entrega e Pagamento</h3>
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Dados de Entrega</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($pedido['morada_entrega'] ?? 'Morada não especificada'); ?><br>
                        <span style="font-size: 0.9em; font-weight: normal; color: #555;">
                            <?php echo htmlspecialchars(($pedido['codigo_postal'] ?? '') . ' ' . ($pedido['localidade'] ?? '')); ?>
                        </span>
                    </div>
                    <div style="margin-top:15px; color:#666; font-size: 0.9em;">
                        <i class="fas fa-box"></i> <strong>Tipo de Entrega:</strong> <?php echo htmlspecialchars($pedido['tipo_entrega'] ?? 'N/A'); ?><br>
                        <i class="fas fa-calendar-alt"></i> <strong>Prazo Desejado:</strong> <?php echo !empty($pedido['prazo_entrega_desejado']) ? date('d/m/Y', strtotime($pedido['prazo_entrega_desejado'])) : 'N/A'; ?>
                    </div>
                </div>

                <div class="info-box">
                    <div class="info-label">Pagamento e Observações</div>
                    <div class="info-value" style="margin-bottom: 15px; color: #333;">
                        <i class="fas fa-credit-card"></i> 
                        <?php echo htmlspecialchars($pedido['metodo_pagamento'] ?? 'Não especificado'); ?>
                        <?php if(!empty($pedido['estado_pagamento'])): ?>
                            <span class="badge-pagamento"><?php echo htmlspecialchars($pedido['estado_pagamento']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-label">Observações da Encomenda</div>
                    <?php if (!empty($pedido['observacoes'])): ?>
                        <div style="background: #fff3f5; padding: 12px; border-left: 4px solid #d66d7f; border-radius: 4px; margin-top: 5px; font-size: 0.9em; color: #444; font-style: italic;">
                            <?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 5px; color:#999; font-size: 0.9em;">Sem observações adicionais.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // === Carrega imagens de inspiração enviadas pelo cliente ===
        $imagensInspiracao = [];
        $tabelaInspiracaoExiste = false;
        try {
            $check = $conn->query("SHOW TABLES LIKE 'pedido_inspiracao'");
            $tabelaInspiracaoExiste = (bool)$check->fetch();
            if ($tabelaInspiracaoExiste) {
                $stmt = $conn->prepare("SELECT id, ordem FROM pedido_inspiracao WHERE pedido_id = ? ORDER BY ordem ASC");
                $stmt->execute([$pedido_id]);
                $imagensInspiracao = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) { /* ignora */ }
        ?>

        <div class="card">
            <h3><i class="fas fa-images"></i> Imagens de Inspiração da Cliente</h3>

            <?php if (!$tabelaInspiracaoExiste): ?>
                <div style="background:#fff3cd; border-left:4px solid #ffc107; padding:12px 14px; border-radius:8px; color:#856404;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Configuração necessária:</strong>
                    aplique a SQL <code>docs/db/alter_portfolio.sql</code> em phpMyAdmin para ativar
                    a receção de fotos de inspiração nos pedidos.
                </div>
            <?php elseif (empty($imagensInspiracao)): ?>
                <p style="color:#888; font-style:italic; margin:0;">
                    A cliente não enviou fotos de inspiração com este pedido.
                </p>
            <?php else: ?>
                <p style="color:#666; font-size:14px; margin-bottom:14px;">
                    Fotos que a cliente enviou para mostrar o que tem em mente:
                </p>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
                    <?php foreach ($imagensInspiracao as $insp): ?>
                        <a href="view.php?id=<?= $pedido_id ?>&inspiracao_id=<?= (int)$insp['id'] ?>" target="_blank"
                           style="display:block; border-radius:12px; overflow:hidden; border:2px solid #f0e3e7;">
                            <img src="view.php?id=<?= $pedido_id ?>&inspiracao_id=<?= (int)$insp['id'] ?>"
                                 alt="Inspiração <?= (int)$insp['ordem'] ?>"
                                 style="width:100%; height:180px; object-fit:cover; display:block; cursor:zoom-in;">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================================================ -->
        <!-- CARD DE ORÇAMENTO (editar valor + enviar payment link)        -->
        <!-- Mostra-se sempre — independente do estado do pedido            -->
        <!-- ============================================================ -->
        <div class="card" style="border-left: 4px solid #d66d7f;">
            <h3><i class="fas fa-file-invoice-dollar"></i> Orçamento &amp; Link de Pagamento</h3>

            <?php
            // Mensagens flash via querystring
            $msgsOrcamento = [
                'preco_atualizado'        => ['ok',  '✓ Preço atualizado com sucesso.'],
                'link_enviado'            => ['ok',  '✓ Link de pagamento enviado por email à cliente!'],
                'link_gerado_sem_email'   => ['av',  '⚠ Link gerado mas falhou o envio do email — copie o URL abaixo manualmente.'],
                'erro_stripe'             => ['err', '✗ Erro Stripe: ' . htmlspecialchars($_GET['erro_stripe'] ?? '')],
                'erro'                    => ['err', '✗ Erro: ' . htmlspecialchars($_GET['erro'] ?? '')],
            ];
            foreach ($msgsOrcamento as $param => $info) {
                if (isset($_GET[$param])) {
                    [$tipo, $msg] = $info;
                    $cores = ['ok' => '#edf9f0;color:#1f6b35', 'av' => '#fff8d6;color:#8a6500', 'err' => '#fdeced;color:#8b1e2d'];
                    echo "<div style='background:" . $cores[$tipo] . "; padding:12px 14px; border-radius:8px; margin-bottom:14px;'>$msg</div>";
                }
            }
            ?>

            <!-- Form: editar valor_total -->
            <form method="POST" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:18px;">
                <?= csrf_input() ?>
                <input type="hidden" name="accao_orcamento" value="atualizar_preco">
                <label style="font-weight:600; color:#555;">Valor do orçamento (€):</label>
                <input type="number" step="0.01" min="0.01" max="99999" name="valor_total"
                       value="<?php echo number_format((float)$pedido['valor_total'], 2, '.', ''); ?>"
                       required style="padding:10px; border:1px solid #ddd; border-radius:8px; width:140px; font-size:16px;">
                <button type="submit" style="background:#6c757d; color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:600; cursor:pointer;">
                    <i class="fas fa-save"></i> Atualizar preço
                </button>
            </form>

            <p style="color:#666; font-size:14px; margin-bottom:14px;">
                Após confirmar o valor com a cliente por telefone, envie o link de pagamento.
                A cliente recebe email com o orçamento e link Stripe seguro (cartão ou MB Way).
            </p>

            <?php
            // Vai buscar URL do payment link se já foi gerado.
            // Resiliente: se a coluna ainda não existe (SQL alter_orcamento.sql não aplicada),
            // simplesmente trata como inexistente em vez de crashar.
            $linkExistente = null;
            $temColPaymentLink = false;
            try {
                $check = $conn->query("SHOW COLUMNS FROM pagamento LIKE 'stripe_payment_link_url'");
                $temColPaymentLink = (bool)$check->fetch();
                if ($temColPaymentLink) {
                    $stmtPL = $conn->prepare("SELECT stripe_payment_link_url FROM pagamento WHERE pedido_id = ?");
                    $stmtPL->execute([$pedido_id]);
                    $linkExistente = $stmtPL->fetchColumn();
                }
            } catch (Exception $e) { /* ignora */ }
            ?>

            <?php if (!$temColPaymentLink): ?>
                <div style="background:#fff3cd; border-left:4px solid #ffc107; padding:12px 14px; border-radius:8px; margin-bottom:14px; color:#856404;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Configuração necessária:</strong>
                    Aplique a SQL <code>docs/db/alter_orcamento.sql</code> para ativar o envio de links de pagamento.
                </div>
            <?php endif; ?>

            <?php if ($linkExistente): ?>
                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:14px; margin-bottom:14px;">
                    <strong style="color:#0369a1;"><i class="fas fa-link"></i> Link de pagamento ativo:</strong><br>
                    <a href="<?php echo htmlspecialchars($linkExistente); ?>" target="_blank" style="color:#0369a1; word-break:break-all;">
                        <?php echo htmlspecialchars($linkExistente); ?>
                    </a>
                </div>
            <?php endif; ?>

            <form method="POST" action="enviar_link.php" onsubmit="return confirm('Gerar/atualizar link de pagamento e enviar email à cliente?');">
                <?= csrf_input() ?>
                <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido_id; ?>">
                <button type="submit" style="background:#d66d7f; color:#fff; border:none; padding:14px 28px; border-radius:999px; font-weight:600; font-size:15px; cursor:pointer;">
                    <i class="fas fa-paper-plane"></i>
                    <?php echo $linkExistente ? 'Reenviar link (com novo valor)' : 'Enviar link de pagamento por email'; ?>
                </button>
            </form>
        </div>

        <?php if (!empty($pedido['metodo_pagamento'])): ?>
        <div class="card">
            <h3><i class="fas fa-money-check-alt"></i> Validação de Pagamento</h3>

            <?php if (isset($_GET['pag_msg'])): ?>
                <div style="background:#edf9f0; color:#1f6b35; padding:10px 14px; border-radius:8px; margin-bottom:14px;">
                    Estado do pagamento atualizado para <strong><?php echo htmlspecialchars($_GET['pag_msg']); ?></strong>.
                </div>
            <?php endif; ?>

            <p>
                <strong>Método:</strong> <?php echo htmlspecialchars($pedido['metodo_pagamento']); ?>
                — <strong>Estado atual:</strong> <span class="badge-pagamento"><?php echo htmlspecialchars($pedido['estado_pagamento'] ?? '—'); ?></span>
            </p>

            <?php if ($pedido['metodo_pagamento'] === 'transferencia'): ?>
                <?php if ($pedido['tem_comprovativo']): ?>
                    <p>
                        <strong>Comprovativo enviado pelo cliente:</strong><br>
                        <a href="view.php?id=<?php echo $pedido_id; ?>&comprovativo=1" target="_blank" class="btn-voltar" style="background:#d66d7f;">
                            <i class="fas fa-file-download"></i> Ver/Descarregar Comprovativo
                        </a>
                    </p>
                <?php else: ?>
                    <p style="color:#8b1e2d;">⚠ Cliente ainda não enviou comprovativo de transferência.</p>
                <?php endif; ?>
            <?php elseif (in_array($pedido['metodo_pagamento'], ['cartao', 'mbway'], true)): ?>
                <p style="color:#666;">Pagamento processado pela Stripe — atualizado automaticamente via webhook.</p>
            <?php else: ?>
                <p style="color:#666;">Pagamento em dinheiro — a ser efetuado no levantamento.</p>
            <?php endif; ?>

            <?php if ($pedido['estado_pagamento'] !== 'validado'): ?>
                <form method="POST" style="display:inline-block; margin-right:10px;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accao_pagamento" value="validar">
                    <button type="submit" style="padding:10px 18px; background:#28a745; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                        <i class="fas fa-check"></i> Validar pagamento
                    </button>
                </form>
                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Recusar este pagamento?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accao_pagamento" value="recusar">
                    <button type="submit" style="padding:10px 18px; background:#dc3545; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                        <i class="fas fa-times"></i> Recusar
                    </button>
                </form>
            <?php else: ?>
                <p style="color:#1f6b35;"><i class="fas fa-check-circle"></i> Pagamento já validado.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3><i class="fas fa-shopping-bag"></i> Itens Comprados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Preço Unit.</th>
                        <th>Qtd</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($itens as $item): 
                        // Tratamento do LONGBLOB caso venha como stream resource
                        $imagem_dados = is_resource($item['imagem']) ? stream_get_contents($item['imagem']) : $item['imagem'];
                        $imgSrc = !empty($imagem_dados) ? "data:image/jpeg;base64," . base64_encode($imagem_dados) : "";
                        
                        $preco = (float)($item['preco_unitario'] ?? 0); 
                        $subtotal = $preco * (int)$item['quantidade'];
                    ?>
                    <tr>
                        <td style="display:flex; gap:15px; align-items:center;">
                            <?php if($imgSrc): ?>
                                <img src="<?php echo $imgSrc; ?>" class="img-item">
                            <?php else: ?>
                                <div class="img-item" style="display:flex;align-items:center;justify-content:center;background:#f5f5f5;">
                                    <i class="fas fa-palette" style="color:#ccc;"></i>
                                </div>
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($item['nome_produto'] ?? 'Produto Personalizado'); ?></strong>
                        </td>
                        <td><?php echo number_format($preco, 2, ',', ' '); ?> €</td>
                        <td>x<?php echo $item['quantidade']; ?></td>
                        <td><strong><?php echo number_format($subtotal, 2, ',', ' '); ?> €</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#fdfdfd;">
                        <td colspan="3" align="right"><strong>TOTAL FINAL:</strong></td>
                        <td style="font-size:1.2em; color:#d66d7f;"><strong><?php echo number_format($pedido['valor_total'] ?? 0, 2, ',', ' '); ?> €</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>