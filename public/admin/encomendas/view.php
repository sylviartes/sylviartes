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

// Cores dos badges de estado (as mesmas do index.php)
$estadoCores = [
    'aguarda_orcamento' => '#fce7f3;color:#9d174d',
    'em_analise'        => '#fef3c7;color:#92400e',
    'aguarda_pagamento' => '#fed7aa;color:#9a3412',
    'em_producao'       => '#dbeafe;color:#1e40af',
    'concluido'         => '#d1fae5;color:#065f46',
    'entregue'          => '#cffafe;color:#155e75',
    'cancelado'         => '#fee2e2;color:#991b1b',
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encomenda #<?php echo $pedido_id; ?> — SylviArtes Admin</title>
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }

        /* Cabeçalho da página */
        .pagina-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; padding-bottom: 18px;
            border-bottom: 1px solid #f0e3e7;
        }
        .pagina-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 26px; color: #2d3436; margin: 0; font-weight: 600;
        }
        .pagina-header .subtitulo { color: #888; font-size: 13px; margin-top: 3px; }

        /* Botão voltar */
        .btn-voltar {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; background: #fff; color: #555;
            border: 1px solid #e8e8e8; border-radius: 8px;
            font-size: 13px; font-weight: 600; text-decoration: none;
            transition: all 0.15s;
        }
        .btn-voltar:hover { border-color: #d66d7f; color: #d66d7f; }

        /* Cards de secção */
        .secao {
            background: #fff; border-radius: 14px; padding: 24px;
            border: 1px solid #f0e3e7;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            margin-bottom: 20px;
        }
        .secao-titulo {
            font-family: 'Playfair Display', serif;
            font-size: 16px; font-weight: 600; color: #2d3436;
            margin: 0 0 18px; padding-bottom: 12px;
            border-bottom: 1px solid #f5e9ec;
            display: flex; align-items: center; gap: 8px;
        }
        .secao-titulo i { color: #d66d7f; }

        /* Grelha de informação 2 colunas */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 640px) { .info-grid { grid-template-columns: 1fr; } }

        /* Caixa de detalhe */
        .info-box { background: #fdf6f8; border-radius: 10px; padding: 16px; }
        .info-label {
            font-size: 10.5px; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; color: #d66d7f; margin-bottom: 6px;
        }
        .info-valor { font-size: 15px; font-weight: 600; color: #2d3436; line-height: 1.5; }
        .info-detalhe { font-size: 13px; color: #666; margin-top: 6px; line-height: 1.6; }

        /* Badge de estado */
        .estado-badge {
            display: inline-block; padding: 5px 14px; border-radius: 999px;
            font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
        }

        /* Observações da cliente */
        .obs-box {
            background: #fff8fa; border-left: 3px solid #d66d7f;
            border-radius: 0 8px 8px 0; padding: 12px 16px;
            font-size: 14px; color: #444; font-style: italic; line-height: 1.6;
        }

        /* Tabela de itens */
        .itens-tabela { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .itens-tabela thead th {
            background: #fdf6f8; color: #888; font-size: 11px;
            text-transform: uppercase; letter-spacing: 0.6px; font-weight: 600;
            padding: 11px 14px; text-align: left; border-bottom: 1px solid #f0e3e7;
        }
        .itens-tabela tbody td {
            padding: 14px; border-bottom: 1px solid #f5e9ec;
            vertical-align: middle; font-size: 14px;
        }
        .itens-tabela tbody tr:last-child td { border-bottom: none; }
        .itens-tabela tfoot td {
            padding: 14px; font-size: 14px; border-top: 2px solid #f0e3e7;
        }
        .img-item {
            width: 52px; height: 52px; object-fit: cover;
            border-radius: 8px; border: 1px solid #f0e3e7; flex-shrink: 0;
        }
        .img-placeholder {
            width: 52px; height: 52px; border-radius: 8px;
            border: 1px solid #f0e3e7; background: #fdf6f8;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .total-linha { font-size: 17px; font-weight: 700; color: #d66d7f; }

        /* Botões de ação */
        .btn-acao {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 20px; border: none; border-radius: 8px;
            font-family: inherit; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.15s;
        }
        .btn-validar  { background: #d1fae5; color: #065f46; }
        .btn-validar:hover  { background: #a7f3d0; }
        .btn-recusar  { background: #fee2e2; color: #991b1b; }
        .btn-recusar:hover  { background: #fecaca; }
        .btn-enviar   { background: #d66d7f; color: #fff; border-radius: 999px; padding: 13px 28px; font-size: 15px; }
        .btn-enviar:hover { background: #bf5b6d; box-shadow: 0 6px 16px rgba(214,109,127,0.25); }

        /* Mensagens flash */
        .flash-ok  { background:#edf9f0; color:#1f6b35; padding:11px 14px; border-radius:8px; margin-bottom:14px; font-size:14px; }
        .flash-av  { background:#fff8d6; color:#8a6500; padding:11px 14px; border-radius:8px; margin-bottom:14px; font-size:14px; }
        .flash-err { background:#fdeced; color:#8b1e2d; padding:11px 14px; border-radius:8px; margin-bottom:14px; font-size:14px; }

        /* Aviso amarelo (migração pendente) */
        .aviso-migracao {
            background: #fff3cd; border-left: 4px solid #ffc107;
            border-radius: 0 8px 8px 0; padding: 12px 14px;
            color: #856404; font-size: 13px; margin-bottom: 14px;
        }
        .aviso-migracao code {
            background: #fff; padding: 2px 6px; border-radius: 4px; font-size: 12px;
        }

        /* Link de pagamento activo */
        .link-ativo {
            background: #f0f9ff; border: 1px solid #bae6fd;
            border-radius: 8px; padding: 14px; margin-bottom: 14px;
        }
        .link-ativo a { color: #0369a1; word-break: break-all; font-size: 13px; }

        /* Input do valor */
        .input-valor {
            padding: 10px 14px; border: 1px solid #e8e8e8; border-radius: 8px;
            width: 140px; font-size: 15px; font-family: inherit;
            transition: border-color 0.15s;
        }
        .input-valor:focus { border-color: #d66d7f; outline: 3px solid rgba(214,109,127,0.12); }

        /* Btn guardar preço */
        .btn-guardar {
            padding: 10px 18px; background: #fff; color: #555;
            border: 1px solid #e8e8e8; border-radius: 8px;
            font-family: inherit; font-size: 13px; font-weight: 600;
            cursor: pointer; transition: all 0.15s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-guardar:hover { border-color: #d66d7f; color: #d66d7f; }
    </style>
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main-content">

    <!-- Cabeçalho -->
    <div class="pagina-header">
        <div>
            <h1><i class="fas fa-box-open" style="color:#d66d7f; margin-right:8px;"></i> Encomenda #<?php echo $pedido_id; ?></h1>
            <div class="subtitulo">
                <?php echo isset($pedido['data']) ? date('d/m/Y \à\s H:i', strtotime($pedido['data'])) : ''; ?>
            </div>
        </div>
        <a href="index.php" class="btn-voltar"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <!-- =====================================================================
         SECÇÃO 1 — Cliente + Estado
    ====================================================================== -->
    <div class="secao">
        <div class="secao-titulo"><i class="fas fa-user"></i> Cliente &amp; Estado</div>
        <div class="info-grid">

            <!-- Dados do cliente -->
            <div class="info-box">
                <div class="info-label">Cliente</div>
                <div class="info-valor"><?php echo htmlspecialchars($pedido['nome'] ?? 'N/A'); ?></div>
                <div class="info-detalhe">
                    <i class="fas fa-envelope" style="color:#d66d7f; width:14px;"></i>
                    <?php echo htmlspecialchars($pedido['email'] ?? 'N/A'); ?><br>
                    <i class="fas fa-phone" style="color:#d66d7f; width:14px;"></i>
                    <?php echo htmlspecialchars($pedido['telefone'] ?? '---'); ?>
                </div>
            </div>

            <!-- Estado -->
            <div class="info-box">
                <div class="info-label">Estado da Encomenda</div>
                <?php
                    $est = $pedido['estado'] ?? 'aguarda_orcamento';
                    $corEst = $estadoCores[$est] ?? '#f0e3e7;color:#555';
                ?>
                <div class="info-valor" style="margin-bottom:10px;">
                    <span class="estado-badge" style="background:<?php echo $corEst; ?>">
                        <?php echo $estadosLabels[$est] ?? $est; ?>
                    </span>
                </div>
                <div class="info-detalhe">
                    <i class="fas fa-calendar" style="color:#d66d7f; width:14px;"></i>
                    Pedido em <?php echo isset($pedido['data']) ? date('d/m/Y H:i', strtotime($pedido['data'])) : 'N/A'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         SECÇÃO 2 — Entrega + Observações
    ====================================================================== -->
    <div class="secao">
        <div class="secao-titulo"><i class="fas fa-truck"></i> Entrega &amp; Pedido</div>
        <div class="info-grid">

            <!-- Entrega -->
            <div class="info-box">
                <div class="info-label">Morada de Entrega</div>
                <div class="info-valor"><?php echo htmlspecialchars($pedido['morada_entrega'] ?? 'Não especificada'); ?></div>
                <div class="info-detalhe">
                    <?php $cp = trim(($pedido['codigo_postal'] ?? '') . ' ' . ($pedido['localidade'] ?? '')); ?>
                    <?php if ($cp): ?><?php echo htmlspecialchars($cp); ?><br><?php endif; ?>
                    <i class="fas fa-box" style="color:#d66d7f; width:14px;"></i>
                    <?php echo htmlspecialchars($pedido['tipo_entrega'] ?? 'N/A'); ?><br>
                    <i class="fas fa-calendar-alt" style="color:#d66d7f; width:14px;"></i>
                    Prazo: <?php echo !empty($pedido['prazo_entrega_desejado']) ? date('d/m/Y', strtotime($pedido['prazo_entrega_desejado'])) : 'N/A'; ?>
                </div>
            </div>

            <!-- Observações (descrição do pedido) -->
            <div class="info-box">
                <div class="info-label">Descrição do Pedido</div>
                <?php if (!empty($pedido['observacoes'])): ?>
                    <div class="obs-box" style="margin-top:6px;">
                        <?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?>
                    </div>
                <?php else: ?>
                    <div style="color:#aaa; font-size:13px; margin-top:6px; font-style:italic;">Sem observações adicionais.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         SECÇÃO 3 — Imagens de Inspiração
    ====================================================================== -->
    <?php
    // Carrega imagens de inspiração enviadas pelo cliente
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

    <div class="secao">
        <div class="secao-titulo"><i class="fas fa-images"></i> Imagens de Inspiração da Cliente</div>

        <?php if (!$tabelaInspiracaoExiste): ?>
            <div class="aviso-migracao">
                <strong><i class="fas fa-exclamation-triangle"></i> Configuração necessária:</strong>
                aplique a SQL <code>docs/db/alter_portfolio.sql</code> em phpMyAdmin para ativar
                    a receção de fotos de inspiração nos pedidos.
            </div>
        <?php elseif (empty($imagensInspiracao)): ?>
            <p style="color:#aaa; font-style:italic; margin:0; font-size:14px;">A cliente não enviou fotos de inspiração com este pedido.</p>
        <?php else: ?>
            <p style="color:#666; font-size:13px; margin-bottom:14px;">Fotos que a cliente enviou para mostrar o que tem em mente:</p>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
                <?php foreach ($imagensInspiracao as $insp): ?>
                    <a href="view.php?id=<?= $pedido_id ?>&inspiracao_id=<?= (int)$insp['id'] ?>" target="_blank"
                       style="display:block; border-radius:12px; overflow:hidden; border:1px solid #f0e3e7;">
                        <img src="view.php?id=<?= $pedido_id ?>&inspiracao_id=<?= (int)$insp['id'] ?>"
                             alt="Inspiração <?= (int)$insp['ordem'] ?>"
                             loading="lazy"
                             style="width:100%; height:180px; object-fit:cover; display:block; cursor:zoom-in;">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- =====================================================================
         SECÇÃO 4 — Orçamento & Link de Pagamento
    ====================================================================== -->
    <div class="secao">
        <div class="secao-titulo"><i class="fas fa-file-invoice-dollar"></i> Orçamento &amp; Link de Pagamento</div>

        <?php
        // Mensagens flash via querystring
        $msgsOrcamento = [
            'preco_atualizado'      => ['ok',  '<i class="fas fa-check-circle"></i> Preço atualizado com sucesso.'],
            'link_enviado'          => ['ok',  '<i class="fas fa-check-circle"></i> Link de pagamento enviado por email à cliente!'],
            'link_gerado_sem_email' => ['av',  '<i class="fas fa-exclamation-circle"></i> Link gerado mas falhou o email — copie o URL abaixo manualmente.'],
            'erro_stripe'           => ['err', '<i class="fas fa-times-circle"></i> Erro Stripe: ' . htmlspecialchars($_GET['erro_stripe'] ?? '')],
            'erro'                  => ['err', '<i class="fas fa-times-circle"></i> Erro: ' . htmlspecialchars($_GET['erro'] ?? '')],
        ];
        foreach ($msgsOrcamento as $param => $info) {
            if (isset($_GET[$param])) {
                [$tipo, $msg] = $info;
                echo "<div class='flash-{$tipo}'>{$msg}</div>";
            }
        }
        ?>

        <!-- Editar valor do orçamento -->
        <form method="POST" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:18px;">
            <?= csrf_input() ?>
            <input type="hidden" name="accao_orcamento" value="atualizar_preco">
            <label style="font-weight:600; color:#555; font-size:14px;">Valor do orçamento (€):</label>
            <input type="number" step="0.01" min="0.01" max="99999" name="valor_total"
                   value="<?php echo number_format((float)$pedido['valor_total'], 2, '.', ''); ?>"
                   required class="input-valor">
            <button type="submit" class="btn-guardar">
                <i class="fas fa-save"></i> Guardar preço
            </button>
        </form>

        <p style="color:#666; font-size:14px; margin-bottom:16px; line-height:1.6;">
            Após confirmar o valor com a cliente por telefone, envie o link de pagamento.
            A cliente recebe um email com o orçamento e um link Stripe seguro (cartão ou MB Way).
        </p>

        <?php
        // Vai buscar URL do payment link se já foi gerado.
        // Resiliente: se a coluna ainda não existe, trata como inexistente.
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
            <div class="aviso-migracao">
                <strong><i class="fas fa-exclamation-triangle"></i> Configuração necessária:</strong>
                Aplique a SQL <code>docs/db/alter_orcamento.sql</code> para ativar o envio de links de pagamento.
            </div>
        <?php endif; ?>

        <?php if ($linkExistente): ?>
            <div class="link-ativo">
                <strong style="color:#0369a1; font-size:13px;"><i class="fas fa-link"></i> Link de pagamento ativo:</strong><br>
                <a href="<?php echo htmlspecialchars($linkExistente); ?>" target="_blank" rel="noopener">
                    <?php echo htmlspecialchars($linkExistente); ?>
                </a>
            </div>
        <?php endif; ?>

        <form method="POST" action="enviar_link.php" onsubmit="return confirm('Gerar/atualizar link de pagamento e enviar email à cliente?');">
            <?= csrf_input() ?>
            <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido_id; ?>">
            <button type="submit" class="btn-acao btn-enviar">
                <i class="fas fa-paper-plane"></i>
                <?php echo $linkExistente ? 'Reenviar link (com novo valor)' : 'Enviar link de pagamento por email'; ?>
            </button>
        </form>
    </div>

    <!-- =====================================================================
         SECÇÃO 5 — Validação de Pagamento (só se existir pagamento)
    ====================================================================== -->
    <?php if (!empty($pedido['metodo_pagamento'])): ?>
    <div class="secao">
        <div class="secao-titulo"><i class="fas fa-money-check-alt"></i> Validação de Pagamento</div>

        <?php if (isset($_GET['pag_msg'])): ?>
            <div class="flash-ok">
                Estado do pagamento atualizado para <strong><?php echo htmlspecialchars($_GET['pag_msg']); ?></strong>.
            </div>
        <?php endif; ?>

        <p style="font-size:14px; color:#555; margin-bottom:16px;">
            <strong>Método:</strong> <?php echo htmlspecialchars($pedido['metodo_pagamento']); ?>
            &nbsp;·&nbsp;
            <strong>Estado:</strong>
            <span class="estado-badge" style="background:<?php echo $estadoCores[$pedido['estado_pagamento'] ?? ''] ?? '#f0e3e7;color:#555'; ?>; margin-left:4px;">
                <?php echo htmlspecialchars($pedido['estado_pagamento'] ?? '—'); ?>
            </span>
        </p>

        <?php if ($pedido['metodo_pagamento'] === 'transferencia'): ?>
            <?php if ($pedido['tem_comprovativo']): ?>
                <p style="margin-bottom:16px;">
                    <a href="view.php?id=<?php echo $pedido_id; ?>&comprovativo=1" target="_blank" class="btn-acao btn-validar" style="text-decoration:none;">
                        <i class="fas fa-file-download"></i> Ver comprovativo de transferência
                    </a>
                </p>
            <?php else: ?>
                <p style="color:#856404; font-size:14px; margin-bottom:16px;">
                    <i class="fas fa-exclamation-circle"></i> Cliente ainda não enviou comprovativo de transferência.
                </p>
            <?php endif; ?>
        <?php elseif (in_array($pedido['metodo_pagamento'], ['cartao', 'mbway'], true)): ?>
            <p style="color:#666; font-size:14px; margin-bottom:16px;">Pagamento processado pela Stripe — atualizado automaticamente via webhook.</p>
        <?php else: ?>
            <p style="color:#666; font-size:14px; margin-bottom:16px;">Pagamento em dinheiro — a ser efetuado no levantamento.</p>
        <?php endif; ?>

        <?php if (($pedido['estado_pagamento'] ?? '') !== 'validado'): ?>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accao_pagamento" value="validar">
                    <button type="submit" class="btn-acao btn-validar">
                        <i class="fas fa-check"></i> Validar pagamento
                    </button>
                </form>
                <form method="POST" onsubmit="return confirm('Recusar este pagamento?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accao_pagamento" value="recusar">
                    <button type="submit" class="btn-acao btn-recusar">
                        <i class="fas fa-times"></i> Recusar
                    </button>
                </form>
            </div>
        <?php else: ?>
            <p style="color:#065f46; font-size:14px;"><i class="fas fa-check-circle"></i> Pagamento já validado.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- =====================================================================
         SECÇÃO 6 — Itens do Pedido
    ====================================================================== -->
    <div class="secao">
        <div class="secao-titulo"><i class="fas fa-shopping-bag"></i> Itens do Pedido</div>
        <table class="itens-tabela">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Preço Unit.</th>
                    <th>Qtd</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item):
                    // BLOB pode vir como stream resource — converte para string
                    $imagem_dados = is_resource($item['imagem']) ? stream_get_contents($item['imagem']) : $item['imagem'];
                    // Detecta o tipo MIME real pela assinatura do ficheiro
                    $imgMime = !empty($imagem_dados) ? (new finfo(FILEINFO_MIME_TYPE))->buffer($imagem_dados) : '';
                    $imgSrc  = !empty($imagem_dados) ? "data:{$imgMime};base64," . base64_encode($imagem_dados) : '';
                    $preco    = (float)($item['preco_unitario'] ?? 0);
                    $subtotal = $preco * (int)$item['quantidade'];
                ?>
                <tr>
                    <td>
                        <div style="display:flex; gap:14px; align-items:center;">
                            <?php if ($imgSrc): ?>
                                <img src="<?php echo $imgSrc; ?>" class="img-item" alt="">
                            <?php else: ?>
                                <div class="img-placeholder">
                                    <i class="fas fa-palette" style="color:#d66d7f; font-size:18px;"></i>
                                </div>
                            <?php endif; ?>
                            <strong style="font-size:14px; color:#2d3436;">
                                <?php echo htmlspecialchars($item['nome_produto'] ?? 'Produto Personalizado'); ?>
                            </strong>
                        </div>
                    </td>
                    <td style="color:#666; font-size:14px;"><?php echo number_format($preco, 2, ',', ' '); ?> €</td>
                    <td style="color:#666; font-size:14px;">× <?php echo (int)$item['quantidade']; ?></td>
                    <td style="font-weight:600; color:#2d3436;"><?php echo number_format($subtotal, 2, ',', ' '); ?> €</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right; font-weight:600; color:#555; font-size:13px; text-transform:uppercase; letter-spacing:0.5px;">Total Final</td>
                    <td class="total-linha"><?php echo number_format((float)($pedido['valor_total'] ?? 0), 2, ',', ' '); ?> €</td>
                </tr>
            </tfoot>
        </table>
    </div>

</div><!-- fim .main-content -->
</body>
</html>