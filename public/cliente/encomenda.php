<?php
/**
 * =============================================================================
 *  DETALHE DE ENCOMENDA - Vista única do cliente
 * =============================================================================
 *
 *  Página com toda a informação sobre uma encomenda específica e onde o
 *  cliente pode tomar acções:
 *
 *    1. CANCELAR pedido
 *       Permitido enquanto o estado for "em_analise" ou "aguarda_pagamento".
 *       Regista a alteração em log_alteracoes_pedido.
 *
 *    2. PAGAR AGORA (cartão / MB Way)
 *       Reabre a sessão Stripe se o cliente fechou a janela sem pagar.
 *       Ver stripe_retomar.php.
 *
 *  SEGURANÇA: Verifica SEMPRE `utilizador_id = $clienteId` em todas as
 *  queries para garantir que ninguém vê/manipula encomendas de outras pessoas.
 * =============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../src/avaliacoes.php';

$clienteId = $_SESSION['cliente_id'];
$pedidoId = (int)($_GET['id'] ?? 0);

// ID inválido → volta à lista
if ($pedidoId <= 0) {
    header("Location: encomendas.php");
    exit;
}

// Carrega o pedido E confirma que pertence ao cliente logado
// (esta é a verificação crítica de segurança contra IDs adivinhados)
$stmt = $conn->prepare("
    SELECT * FROM pedido WHERE id = ? AND utilizador_id = ?
");
$stmt->execute([$pedidoId, $clienteId]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    // Pedido não existe OU não é deste cliente → fora daqui!
    header("Location: encomendas.php");
    exit;
}

$mensagem = "";  // texto de feedback
$tipoMsg = "";   // 'ok' ou 'erro' (para colorir a caixa)

// =========================================================================
// PROCESSAMENTO DE ACÇÕES (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accao = $_POST['accao'] ?? '';

    // ----- AÇÃO: Cancelar pedido -----
    if ($accao === 'cancelar') {
        csrf_validate();   // protege contra pedidos forjados (CSRF)
        // Só permite cancelar enquanto o estado ainda for inicial
        if (in_array($pedido['estado'], ['em_analise', 'aguarda_pagamento'], true)) {
            try {
                // Transação para garantir atomicidade: ou ambos UPDATE+INSERT ocorrem, ou nenhum
                $conn->beginTransaction();
                $estadoAnt = $pedido['estado'];

                // 1) Marca o pedido como cancelado (com dupla verificação de segurança)
                $stmt = $conn->prepare("UPDATE pedido SET estado='cancelado' WHERE id=? AND utilizador_id=?");
                $stmt->execute([$pedidoId, $clienteId]);

                // 2) Regista a alteração no log para auditoria
                $stmtLog = $conn->prepare("
                    INSERT INTO log_alteracoes_pedido (pedido_id, estado_anterior, estado_novo, alterado_por)
                    VALUES (?, ?, 'cancelado', ?)
                ");
                $stmtLog->execute([$pedidoId, $estadoAnt, 'cliente#' . $clienteId]);

                $conn->commit();
                header("Location: encomenda.php?id=" . $pedidoId . "&msg=cancelado");
                exit;
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Cliente: erro ao cancelar encomenda: " . $e->getMessage());
                $mensagem = "Ocorreu um erro ao cancelar a encomenda. Por favor tente mais tarde.";
                $tipoMsg = "erro";
            }
        } else {
            $mensagem = "Este pedido já não pode ser cancelado.";
            $tipoMsg = "erro";
        }
    }
    // ----- AÇÃO: Avaliar a encomenda (estrelas + comentário) -----
    elseif ($accao === 'avaliar') {
        csrf_validate();
        $estrelas = (int)($_POST['estrelas'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        // Só se pode avaliar uma encomenda já concluída/entregue
        if (!in_array($pedido['estado'], ['concluido', 'entregue'], true)) {
            $mensagem = "Só pode avaliar encomendas concluídas.";
            $tipoMsg = "erro";
        } elseif ($estrelas < 1 || $estrelas > 5) {
            $mensagem = "Selecione entre 1 e 5 estrelas.";
            $tipoMsg = "erro";
        } elseif (pedido_ja_avaliado($conn, $pedidoId)) {
            $mensagem = "Já avaliou esta encomenda. Obrigado!";
            $tipoMsg = "erro";
        } else {
            // aprovado = 0 → fica à espera de moderação no admin.
            // produto_id NULL → testemunho geral (aparece na homepage após aprovação).
            $stmt = $conn->prepare("
                INSERT INTO avaliacao (utilizador_id, produto_id, pedido_id, estrelas, comentario, aprovado)
                VALUES (?, NULL, ?, ?, ?, 0)
            ");
            $stmt->execute([$clienteId, $pedidoId, $estrelas, $comentario]);
            header("Location: encomenda.php?id=" . $pedidoId . "&msg=avaliacao");
            exit;
        }
    }
}

// Mensagens "flash" passadas via querystring depois de um redirect
// (Pattern Post-Redirect-Get: evita re-submissão se o utilizador atualizar página)
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'cancelado') { $mensagem = "Pedido cancelado."; $tipoMsg = "ok"; }
    if ($_GET['msg'] === 'avaliacao') { $mensagem = "Avaliação enviada! Obrigado. Vai aparecer no site assim que for aprovada."; $tipoMsg = "ok"; }
}

// =========================================================================
// CARREGAMENTO DE DADOS PARA A VIEW
// =========================================================================

// Recarrega o pedido (depois de eventuais UPDATEs feitos acima)
$stmt = $conn->prepare("SELECT * FROM pedido WHERE id=?");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

// Linha de pagamento associada ao pedido
$stmt = $conn->prepare("SELECT * FROM pagamento WHERE pedido_id = ?");
$stmt->execute([$pedidoId]);
$pagamento = $stmt->fetch(PDO::FETCH_ASSOC);

// Itens (detalhe do pedido) - JOIN com produto para ir buscar o nome
// (LEFT JOIN porque o produto pode ter sido eliminado entretanto)
$stmt = $conn->prepare("
    SELECT dp.*, pr.nome AS produto_nome
    FROM detalhe_pedido dp
    LEFT JOIN produto pr ON pr.id = dp.produto_id
    WHERE dp.pedido_id = ?
");
$stmt->execute([$pedidoId]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Flags de UI: que botões/secções mostrar? ===
$podeCancelar = in_array($pedido['estado'], ['em_analise', 'aguarda_pagamento'], true);
// Mostrar botão "Pagar agora" (só faz sentido para Stripe não validado)
$pagInline = $pagamento && in_array($pagamento['metodo'], ['cartao', 'mbway'], true)
             && $pagamento['estado_pagamento'] !== 'validado'
             && $pedido['estado'] !== 'cancelado';

function estadoLabel2($e) {
    return [
        'aguarda_orcamento' => 'Aguarda Orçamento',
        'em_analise' => 'Em análise',
        'aguarda_pagamento' => 'Aguarda pagamento',
        'em_producao' => 'Em produção',
        'concluido' => 'Concluído',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ][$e] ?? $e;
}
function pagLabel2($e) {
    return [
        'analise_pagamento' => 'A validar',
        'validado' => 'Pago',
        'recusado' => 'Recusado',
    ][$e] ?? '-';
}
function metodoLabel($m) {
    return [
        'cartao' => 'Cartão',
        'mbway' => 'MB Way',
        'transferencia' => 'Transferência Bancária',
        'dinheiro' => 'Dinheiro no levantamento',
    ][$m] ?? $m;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encomenda #<?php echo (int)$pedido['id']; ?> - SylviArtes</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../imagens/logo_sylviartes.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cliente_style.css?v=<?= @filemtime(__DIR__ . '/cliente_style.css') ?: 1 ?>">
    <style>
        /* Formulário de avaliação da encomenda */
        .aval-encomenda {
            background: linear-gradient(135deg, #fff8fa, #fdf0f4);
            border: 1px solid #f0c8d2; border-radius: 12px;
            padding: 18px 22px; margin: 16px 0;
        }
        .aval-encomenda h3 { margin: 0 0 4px; color: #d66d7f; font-size: 18px; }
        .aval-encomenda p { color: #636e72; font-size: 14px; margin: 0 0 12px; }
        /* Estrelas clicáveis */
        .aval-estrelas { display: inline-flex; gap: 6px; font-size: 30px; color: #ddd; margin-bottom: 14px; }
        .aval-estrelas .aval-estrela {
            background: none; border: none; padding: 2px; margin: 0;
            cursor: pointer; color: inherit; font-size: inherit; line-height: 1;
        }
        .aval-estrelas i { transition: color 0.15s; }
        .aval-estrelas i.ativa { color: #f5b301; }
        .aval-encomenda textarea {
            width: 100%; box-sizing: border-box; padding: 12px;
            border-radius: 10px; border: 1px solid #e8cdd4;
            font-family: inherit; font-size: 14px; resize: vertical; margin-bottom: 12px;
        }
        .aval-encomenda textarea:focus { outline: none; border-color: #d66d7f; }
    </style>
</head>
<body>
    <div class="cli-wrapper">
        <a href="encomendas.php" class="cli-back">← As minhas encomendas</a>

        <?php if ($mensagem): ?>
            <div class="<?php echo $tipoMsg === 'ok' ? 'auth-sucesso' : 'auth-erro'; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <div class="cli-section">
            <h2>Encomenda #<?php echo (int)$pedido['id']; ?></h2>
            <p style="color:#636e72; margin-bottom:18px;">
                Feita em <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['data']))); ?>
            </p>

            <p>
                <strong>Estado:</strong>
                <span class="cli-badge b-<?php echo htmlspecialchars($pedido['estado']); ?>">
                    <?php echo htmlspecialchars(estadoLabel2($pedido['estado'])); ?>
                </span>
            </p>

            <?php if ($pedido['estado'] === 'aguarda_orcamento'): ?>
                <div style="background:#fff8fa; border-left:4px solid #d66d7f; padding:14px 18px; border-radius:8px; margin: 14px 0; color:#555;">
                    <i class="fas fa-info-circle" style="color:#d66d7f;"></i>
                    <strong>Estamos a analisar o seu pedido.</strong>
                    Vamos contactá-la(o) em breve por telefone ou email para confirmar
                    detalhes e enviar o orçamento final com link de pagamento.
                </div>
            <?php elseif ($pedido['estado'] === 'aguarda_pagamento'): ?>
                <div style="background:#f0f9ff; border-left:4px solid #3b82f6; padding:14px 18px; border-radius:8px; margin: 14px 0; color:#555;">
                    <i class="fas fa-envelope" style="color:#3b82f6;"></i>
                    <strong>Orçamento enviado!</strong>
                    Verifique o email com o link de pagamento.
                </div>
            <?php elseif (in_array($pedido['estado'], ['concluido', 'entregue'], true)): ?>
                <?php if (pedido_ja_avaliado($conn, $pedidoId)): ?>
                    <!-- Já avaliada: agradecimento -->
                    <div style="background:#f0fdf4; border-left:4px solid #22c55e; padding:14px 18px; border-radius:8px; margin:14px 0; color:#555;">
                        <i class="fas fa-check-circle" style="color:#22c55e;"></i>
                        <strong>Obrigado pela sua avaliação!</strong> Vai aparecer no site assim que for aprovada.
                    </div>
                <?php else: ?>
                    <!-- Formulário para avaliar a experiência desta encomenda -->
                    <div class="aval-encomenda">
                        <h3><i class="fas fa-star"></i> Como correu a sua encomenda?</h3>
                        <p>A sua opinião ajuda outras pessoas a conhecer o nosso trabalho. (1 = mau, 5 = excelente)</p>
                        <form method="POST" id="form-avaliar" onsubmit="return validarAvaliacao();">
                            <?= csrf_input() ?>
                            <input type="hidden" name="accao" value="avaliar">
                            <input type="hidden" name="estrelas" id="aval-estrelas-input" value="0">

                            <!-- Estrelas clicáveis (rato ou teclado) -->
                            <div class="aval-estrelas" id="aval-estrelas" role="radiogroup" aria-label="Classificação em estrelas">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <button type="button" class="aval-estrela" data-valor="<?= $i ?>"
                                            role="radio" aria-checked="false" aria-label="<?= $i ?> estrela<?= $i > 1 ? 's' : '' ?>">
                                        <i class="far fa-star"></i>
                                    </button>
                                <?php endfor; ?>
                            </div>

                            <textarea name="comentario" rows="3" maxlength="500"
                                      placeholder="Conte como foi a sua experiência (opcional)..."></textarea>
                            <button type="submit" class="cli-btn">Enviar avaliação</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($pagamento): ?>
                <p>
                    <strong>Pagamento:</strong>
                    <?php echo htmlspecialchars(metodoLabel($pagamento['metodo'])); ?> -
                    <span class="cli-badge b-<?php echo htmlspecialchars($pagamento['estado_pagamento']); ?>">
                        <?php echo htmlspecialchars(pagLabel2($pagamento['estado_pagamento'])); ?>
                    </span>
                </p>
            <?php endif; ?>

            <p><strong>Entrega:</strong>
                <?php echo $pedido['tipo_entrega'] === 'domicilio' ? 'Ao domicílio' : 'Levantamento no atelier'; ?>
                <?php if ($pedido['tipo_entrega'] === 'domicilio'): ?>
                    - <?php echo htmlspecialchars($pedido['morada_entrega']); ?>
                <?php endif; ?>
            </p>
            <p><strong>Prazo desejado:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($pedido['prazo_entrega_desejado']))); ?></p>

            <?php if (!empty($pedido['observacoes'])): ?>
                <p><strong>Observações:</strong> <?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></p>
            <?php endif; ?>
        </div>

        <div class="cli-section">
            <h2>Produtos</h2>
            <table class="cli-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Preço unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $it): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($it['produto_nome'] ?? 'Produto removido'); ?></strong>
                                <?php if (!empty($it['descricao'])): ?>
                                    <br><small style="color:#636e72;"><?php echo htmlspecialchars($it['descricao']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int)$it['quantidade']; ?></td>
                            <td><?php echo number_format($it['preco_unitario'] ?? 0, 2, ',', '.'); ?> €</td>
                            <td><?php echo number_format(($it['preco_unitario'] ?? 0) * $it['quantidade'], 2, ',', '.'); ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3" style="text-align:right;"><strong>Custo de envio</strong></td>
                        <td><?php echo number_format($pedido['custo_envio'], 2, ',', '.'); ?> €</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align:right;"><strong>Total</strong></td>
                        <td><strong style="color:#d66d7f;"><?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?> €</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if ($pagInline): ?>
            <div class="cli-section">
                <h2>Pagar agora</h2>
                <p>Foi escolhido <strong><?php echo htmlspecialchars(metodoLabel($pagamento['metodo'])); ?></strong>. Clique abaixo para concluir o pagamento na plataforma segura do Stripe.</p>
                <a class="cli-btn" href="../stripe_retomar.php?pedido_id=<?php echo (int)$pedido['id']; ?>">
                    Pagar <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?> €
                </a>
            </div>
        <?php endif; ?>


        <?php if ($podeCancelar): ?>
            <div class="cli-section">
                <h2>Cancelar encomenda</h2>
                <p>Pode cancelar enquanto o estado for <em>Em análise</em> ou <em>Aguarda pagamento</em>.</p>
                <form method="POST" onsubmit="return confirm('Tem a certeza que quer cancelar este pedido?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accao" value="cancelar">
                    <button type="submit" class="cli-btn cli-btn-danger">Cancelar pedido</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // === Seleção de estrelas no formulário de avaliação ===
    // Funciona com rato e teclado (os botões são focáveis).
    (function () {
        const grupo = document.getElementById('aval-estrelas');
        if (!grupo) return; // a encomenda pode não ter formulário (não concluída / já avaliada)

        const input = document.getElementById('aval-estrelas-input');
        const botoes = grupo.querySelectorAll('.aval-estrela');
        const icones = grupo.querySelectorAll('i');

        function pintar(valor) {
            input.value = valor;
            icones.forEach((el, idx) => {
                const ativa = idx < valor;
                el.classList.toggle('fas', ativa);   // estrela cheia
                el.classList.toggle('far', !ativa);  // estrela vazia
                el.classList.toggle('ativa', ativa);
            });
            botoes.forEach((b) => {
                b.setAttribute('aria-checked', (parseInt(b.dataset.valor, 10) === valor) ? 'true' : 'false');
            });
        }

        botoes.forEach((b) => {
            b.addEventListener('click', () => pintar(parseInt(b.dataset.valor, 10)));
        });
    })();

    // Impede submeter sem ter escolhido estrelas
    function validarAvaliacao() {
        const v = parseInt(document.getElementById('aval-estrelas-input').value, 10);
        if (!v || v < 1) {
            alert('Por favor escolha uma classificação de 1 a 5 estrelas.');
            return false;
        }
        return true;
    }
    </script>
</body>
</html>
