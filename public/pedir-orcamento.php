<?php
/**
 * =============================================================================
 *  PEDIR ORÇAMENTO — Form único de pedido personalizado
 * =============================================================================
 *
 *  Ponto único de contacto. Substitui o checkout do antigo carrinho.
 *  Cliente preenche dados pessoais + descrição da peça que quer + opcionalmente
 *  3 fotos de inspiração + (opcional) ID de item do portfólio que serviu de
 *  inspiração.
 *
 *  Cria pedido com estado 'aguarda_orcamento'. Mãe vê em admin, telefona,
 *  ajusta preço, envia link Stripe por email.
 *
 *  Suporta:
 *    - ?inspiracao=ID  → pré-seleciona item do portfólio
 *    - Cliente logado  → auto-preenche dados pessoais
 * =============================================================================
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../src/email.php'; // para enviar aviso à Sylvia quando chega pedido novo

// =============================================================================
// LOGIN OBRIGATÓRIO — só clientes com sessão iniciada podem pedir orçamento
// =============================================================================
// Se não estiver autenticado, envia para o login e volta a esta página depois
// (preserva o ?inspiracao=ID, se vier de um item do portfólio).
// Tem de ser ANTES de qualquer output (header.php) para o redirect funcionar.
if (!isset($_SESSION['cliente_id'])) {
    $voltar = 'pedir-orcamento.php';
    if (isset($_GET['inspiracao'])) {
        $voltar .= '?inspiracao=' . (int)$_GET['inspiracao'];
    }
    // O redirect é relativo a cliente/login.php — "../" volta à pasta public/
    header('Location: cliente/login.php?redirect=' . urlencode('../' . $voltar));
    exit;
}

// === REGEX (mesmas do projeto, fornecidas pelo professor) ===
$regexPostal   = "/^[1-9]\d{3}(-\d{3})?$/";
$regexTelefone = "/^(\+351)?(2\d{8}|9[1236]\d{7})$/";
$regexEmail    = "/^[a-zA-Z0-9\-]+(\.[a-zA-Z0-9]+)*@[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)*$/";
$regexNome     = "/(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)+$)|(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)*( ((de)|(dos)|(da)|(do Ó)))?( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÔÕUÚÙÛ][a-zaáàãâeéèêiíìîoóòôõuúùû]+)+$)/u";
$regexMorada   = "/^\S+( \S+)*$/";

$mensagem = "";
$tipo_mensagem = "";
$erros = [];
$pedidoConcluido = false;

// === Item do portfólio que serviu de inspiração (opcional via ?inspiracao=ID) ===
$inspiracaoId = isset($_GET['inspiracao']) ? (int)$_GET['inspiracao'] : 0;
$itemInspiracao = null;
if ($inspiracaoId > 0) {
    $stmt = $conn->prepare("
        SELECT p.id, p.nome, p.descricao, c.nome AS categoria
        FROM produto p
        LEFT JOIN categoria c ON c.id = p.categoria_id
        WHERE p.id = ? AND p.visivel_catalogo = 1
    ");
    $stmt->execute([$inspiracaoId]);
    $itemInspiracao = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// === Auto-preenchimento se cliente logado ===
$clienteLogado = null;
if (isset($_SESSION['cliente_id'])) {
    $stmt = $conn->prepare("SELECT * FROM utilizador WHERE id = ?");
    $stmt->execute([$_SESSION['cliente_id']]);
    $clienteLogado = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// === Categorias para o select ===
$todasCategorias = $conn->query("SELECT id, nome FROM categoria ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// =============================================================================
// PROCESSAR FORM
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $nome          = trim($_POST['nome'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $telefone      = preg_replace('/\s+/', '', trim($_POST['telefone'] ?? ''));
    $morada        = trim($_POST['morada'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    $localidade    = trim($_POST['localidade'] ?? '');
    $tipoEntrega   = $_POST['tipo_entrega'] ?? 'domicilio';
    $prazoEntrega  = $_POST['prazo_entrega_desejado'] ?? '';
    $categoriaId   = (int)($_POST['categoria_id'] ?? 0);
    $descricao     = trim($_POST['descricao'] ?? '');
    $portfolioRef  = (int)($_POST['portfolio_inspiracao_id'] ?? 0);

    // Validações
    if (!preg_match($regexNome, $nome))         $erros[] = "Nome inválido (Ex: Maria Silva).";
    if (!preg_match($regexEmail, $email))       $erros[] = "Email inválido.";
    if (!preg_match($regexTelefone, $telefone)) $erros[] = "Telefone inválido (Ex: 912345678).";
    if ($descricao === '' || strlen($descricao) < 15) {
        $erros[] = "Descreva o que pretende com algum detalhe (mínimo 15 caracteres).";
    }
    if ($tipoEntrega === 'domicilio') {
        if (!preg_match($regexMorada, $morada))    $erros[] = "Morada obrigatória para entrega ao domicílio.";
        if (!preg_match($regexPostal, $codigo_postal)) $erros[] = "Código Postal inválido (Ex: 4000-123).";
        if ($localidade === '')                  $erros[] = "Localidade obrigatória.";
    }
    if (empty($prazoEntrega)) {
        $prazoEntrega = date('Y-m-d', strtotime('+21 days'));
    }

    if (empty($erros)) {
        // === 1. Identificar/criar utilizador ===
        if ($clienteLogado) {
            $utilizadorId = (int)$clienteLogado['id'];
            $stmt = $conn->prepare("UPDATE utilizador SET nome=?, telefone=?, morada=?, codigo_postal=?, localidade=? WHERE id=?");
            $stmt->execute([$nome, $telefone, $morada, $codigo_postal, $localidade, $utilizadorId]);
        } else {
            $stmt = $conn->prepare("SELECT id FROM utilizador WHERE email = ?");
            $stmt->execute([$email]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existente) {
                $utilizadorId = (int)$existente['id'];
                $stmt = $conn->prepare("UPDATE utilizador SET nome=?, telefone=?, morada=?, codigo_postal=?, localidade=? WHERE id=?");
                $stmt->execute([$nome, $telefone, $morada, $codigo_postal, $localidade, $utilizadorId]);
            } else {
                $hash = password_hash(uniqid(), PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO utilizador (nome, email, password, telefone, morada, codigo_postal, localidade, nivel_acesso)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'cliente')
                ");
                $stmt->execute([$nome, $email, $hash, $telefone, $morada, $codigo_postal, $localidade]);
                $utilizadorId = (int)$conn->lastInsertId();
            }
        }

        $conn->beginTransaction();
        try {
            $moradaEntrega = ($tipoEntrega === 'levantamento_atelier') ? 'Levantamento no atelier' : $morada;

            // === 2. Verificar se a coluna portfolio_inspiracao_id já existe ===
            $temColPortfolio = false;
            try {
                $check = $conn->query("SHOW COLUMNS FROM pedido LIKE 'portfolio_inspiracao_id'");
                $temColPortfolio = (bool)$check->fetch();
            } catch (Exception $e) { /* ignora */ }

            // === 3. Inserir pedido (estado: em_analise — aparece imediatamente no painel admin) ===
            if ($temColPortfolio) {
                $stmt = $conn->prepare("
                    INSERT INTO pedido (utilizador_id, prazo_entrega_desejado, estado, valor_total, observacoes,
                                        tipo_entrega, morada_entrega, custo_envio, portfolio_inspiracao_id)
                    VALUES (?, ?, 'em_analise', 0, ?, ?, ?, 0, ?)
                ");
                $stmt->execute([$utilizadorId, $prazoEntrega, $descricao, $tipoEntrega, $moradaEntrega, $portfolioRef ?: null]);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO pedido (utilizador_id, prazo_entrega_desejado, estado, valor_total, observacoes,
                                        tipo_entrega, morada_entrega, custo_envio)
                    VALUES (?, ?, 'em_analise', 0, ?, ?, ?, 0)
                ");
                $stmt->execute([$utilizadorId, $prazoEntrega, $descricao, $tipoEntrega, $moradaEntrega]);
            }
            $pedidoId = (int)$conn->lastInsertId();

            // === 4. Inserir uma linha em detalhe_pedido se houver categoria escolhida ===
            // (genérica — sem produto_id real, descrição contém o pedido completo)
            if ($categoriaId > 0) {
                // Vai buscar o "produto-tipo" da categoria — primeiro item visível
                $stmt = $conn->prepare("SELECT id FROM produto WHERE categoria_id = ? AND visivel_catalogo = 1 LIMIT 1");
                $stmt->execute([$categoriaId]);
                $produtoTipo = $stmt->fetchColumn();
                if ($produtoTipo) {
                    $stmt = $conn->prepare("
                        INSERT INTO detalhe_pedido (pedido_id, produto_id, quantidade, preco_unitario, descricao)
                        VALUES (?, ?, 1, 0, ?)
                    ");
                    $stmt->execute([$pedidoId, $produtoTipo, $descricao]);
                }
            }

            // === 5. Linha de pagamento NÃO é criada agora ===
            // A BD tem trigger que rejeita valor <= 0. Como ainda não temos o
            // valor final do orçamento, deixamos para o admin/encomendas/enviar_link.php
            // criar a linha de pagamento quando enviar o Stripe Payment Link.

            // === 6. Upload de até 3 imagens de inspiração ===
            $temTabelaInspiracao = false;
            try {
                $check = $conn->query("SHOW TABLES LIKE 'pedido_inspiracao'");
                $temTabelaInspiracao = (bool)$check->fetch();
            } catch (Exception $e) { /* ignora */ }

            if ($temTabelaInspiracao && isset($_FILES['inspiracao']) && is_array($_FILES['inspiracao']['name'])) {
                $tiposOk = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                // Descobre o limite do MySQL para BLOBs e respeita-o (com margem de 100KB para overhead)
                try {
                    $maxBytes = (int)$conn->query("SHOW VARIABLES LIKE 'max_allowed_packet'")
                                          ->fetch(PDO::FETCH_ASSOC)['Value'] - 102400;
                } catch (Exception $e) {
                    $maxBytes = 900000; // fallback ~1MB
                }

                $stmtImg = $conn->prepare("INSERT INTO pedido_inspiracao (pedido_id, imagem, ordem) VALUES (?, ?, ?)");
                $ordem = 1;
                $maxFotos = 3;

                for ($i = 0; $i < count($_FILES['inspiracao']['name']) && $ordem <= $maxFotos; $i++) {
                    if ($_FILES['inspiracao']['error'][$i] !== UPLOAD_ERR_OK) continue;

                    $tamanho = $_FILES['inspiracao']['size'][$i];
                    if ($tamanho > $maxBytes) {
                        // Foto demasiado grande para o BLOB — ignora silenciosamente
                        // (no admin há outro aviso geral; aqui o pedido continua sem essa foto)
                        continue;
                    }

                    $tipo = mime_content_type($_FILES['inspiracao']['tmp_name'][$i]);
                    if (!in_array($tipo, $tiposOk, true)) continue;

                    $blob = file_get_contents($_FILES['inspiracao']['tmp_name'][$i]);
                    $stmtImg->bindValue(1, $pedidoId, PDO::PARAM_INT);
                    $stmtImg->bindValue(2, $blob, PDO::PARAM_LOB);
                    $stmtImg->bindValue(3, $ordem, PDO::PARAM_INT);
                    $stmtImg->execute();
                    $ordem++;
                }
            }

            $conn->commit();

            // === Aviso automático à Sylvia quando chega um pedido novo ===
            // Best-effort: se o email falhar, o pedido já foi gravado na BD (o importante).
            // A Sylvia vê o pedido no painel admin mesmo que o email falhe.
            if (defined('ADMIN_EMAIL') && ADMIN_EMAIL !== '') {
                try {
                    enviar_email_nova_encomenda(ADMIN_EMAIL, $pedidoId, $nome, $email, $telefone, $descricao);
                } catch (Exception $eEmail) {
                    // Regista no log do servidor mas não interrompe o fluxo do cliente
                    error_log("Aviso admin: falha ao enviar notificação de pedido #$pedidoId: " . $eEmail->getMessage());
                }
            }

            $mensagem = "Pedido #{$pedidoId} recebido com sucesso! Vamos contactá-la(o) em breve para confirmar os detalhes e enviar o orçamento final.";
            $tipo_mensagem = "success";
            $pedidoConcluido = true;

        } catch (Exception $e) {
            // Tenta rollBack — se a conexão já foi perdida (server has gone away),
            // o próprio rollBack pode lançar exceção. Apanhamos para mostrar erro
            // amigável em vez de página em branco.
            try {
                if ($conn->inTransaction()) $conn->rollBack();
            } catch (Exception $eRoll) { /* ignora */ }

            // Regista o erro completo no log do servidor (não visível ao utilizador)
            error_log("Orçamento: erro ao registar pedido para email={$email}: "
                . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());

            if (str_contains($e->getMessage(), 'gone away') || str_contains($e->getMessage(), 'too large')) {
                $mensagem = "Erro ao registar pedido. As fotos enviadas são demasiado grandes — tente com fotos mais pequenas (máx 1MB cada).";
            } elseif (str_contains($e->getMessage(), 'Data truncated') || str_contains($e->getMessage(), 'ENUM')) {
                // Este erro acontece se a migração SQL setup_completo.sql não foi aplicada
                $mensagem = "Erro de configuração na base de dados. Contacte a administração.";
            } else {
                $mensagem = "Ocorreu um erro ao registar o pedido. Por favor tente novamente ou contacte-nos directamente.";
            }
            $tipo_mensagem = "danger";
        }
    } else {
        $mensagem = "Verifique os campos do formulário.";
        $tipo_mensagem = "danger";
    }
}

// Título e descrição para esta página
$pageTitle       = 'Pedir Orçamento';
$pageDescription = 'Peça um orçamento gratuito para o seu bordado personalizado — resposta em 24h.';
require_once __DIR__ . '/header.php';

// Helper para auto-fill
$val = function ($campo) use ($clienteLogado) {
    if (isset($_POST[$campo])) return $_POST[$campo];
    if ($clienteLogado && isset($clienteLogado[$campo])) return $clienteLogado[$campo];
    return '';
};
?>

<style>
/* Anula o padding do .pagina-main — o orc-wrapper gere o seu próprio espaçamento */
main { padding: 0 !important; max-width: 100% !important; }

.orc-wrapper { max-width: 920px; margin: 30px auto 60px; background: #fff; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); padding: 40px; }
.orc-title { text-align: center; font-size: 32px; font-weight: 700; color: #2d3436; margin-bottom: 8px; font-family: 'Playfair Display', serif; }
.orc-subtitle { text-align: center; color: #7d7d7d; font-size: 16px; margin-bottom: 30px; }
.orc-section { margin: 28px 0 14px; font-size: 13px; font-weight: 700; letter-spacing: 2px; color: #d66d7f; text-transform: uppercase; }
.orc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.orc-grid .full { grid-column: 1 / -1; }
.orc-group { margin-bottom: 14px; }
.orc-group input, .orc-group select, .orc-group textarea {
    width: 100%; border: 1px solid #e8e8e8; border-radius: 12px; padding: 14px 16px;
    font-size: 15px; background: #fff; color: #333; outline: none; transition: 0.2s;
    font-family: inherit;
}
.orc-group input:focus, .orc-group select:focus, .orc-group textarea:focus {
    border-color: #d66d7f; box-shadow: 0 0 0 3px rgba(214,120,139,0.10);
}
.orc-group textarea { min-height: 140px; resize: vertical; }
.orc-uploads {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 8px;
}
.orc-upload-box {
    border: 2px dashed #f0c8d2; border-radius: 14px; padding: 20px 10px;
    text-align: center; cursor: pointer; transition: 0.2s; background: #fff8fa;
    color: #d66d7f; font-size: 13px; min-height: 90px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.orc-upload-box:hover { border-color: #d66d7f; background: #fff0f3; }
.orc-upload-box i { font-size: 22px; margin-bottom: 6px; }
.orc-upload-box input[type="file"] { display: none; }
.orc-upload-box.has-file { background: #edf9f0; border-color: #cfe9d7; color: #1f6b35; }
.orc-info-box {
    background: #fff8fa; border: 1px solid #f4cdd5; border-radius: 14px;
    padding: 18px 22px; margin-bottom: 22px; color: #555; font-size: 14px;
}
.orc-info-box strong { color: #d66d7f; }
.orc-btn {
    width: 100%; border: none; border-radius: 999px; padding: 18px 20px; font-size: 17px;
    font-weight: 700; color: #fff;
    background: linear-gradient(135deg, #d66d7f, #bf5b6d);
    cursor: pointer; transition: 0.2s;
}
.orc-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(201,95,122,0.25); }
.orc-success {
    text-align: center; padding: 30px;
}
.orc-success h2 { color: #1f6b35; font-family: 'Playfair Display', serif; }
.orc-alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 18px; }
.orc-alert.danger { background: #fdeced; color: #8b1e2d; }
.orc-alert.success { background: #edf9f0; color: #1f6b35; }
.orc-inspiracao {
    background: #fff8fa; border-left: 4px solid #d66d7f; padding: 14px 18px;
    border-radius: 8px; margin-bottom: 22px; display: flex; gap: 14px; align-items: center;
}
/* Tablet — grelha de 2 colunas passa a 1 coluna (campos ficavam muito estreitos) */
@media (max-width: 991px) {
    .orc-grid { grid-template-columns: 1fr; }
}
/* Mobile — margens laterais pequenas + sem cantos exagerados */
@media (max-width: 768px) {
    .orc-wrapper { padding: 24px 20px; margin: 16px; border-radius: 16px; }
    .orc-uploads { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .orc-wrapper { margin: 10px; padding: 20px 16px; }
}
</style>

<div class="orc-wrapper">
    <?php if ($pedidoConcluido): ?>
        <!-- Cartão de confirmação exibido após submissão bem-sucedida -->
        <div class="orc-success">
            <!-- Ícone de confirmação -->
            <div style="width:72px; height:72px; background:#d1fae5; border-radius:50%;
                        display:flex; align-items:center; justify-content:center;
                        margin: 0 auto 20px;">
                <i class="fas fa-check" style="color:#065f46; font-size:28px;"></i>
            </div>

            <h2 style="font-family:'Playfair Display',serif; font-size:26px; color:#2d3436; margin-bottom:8px;">
                Pedido recebido!
            </h2>
            <p style="color:#d66d7f; font-weight:600; font-size:17px; margin-bottom:20px;">
                #<?= $pedidoId ?>
            </p>

            <!-- O que acontece a seguir -->
            <div style="background:#fdf6f8; border-radius:14px; padding:20px; margin-bottom:24px; text-align:left;">
                <p style="font-weight:700; color:#2d3436; font-size:13px; letter-spacing:1px;
                           text-transform:uppercase; margin-bottom:14px;">O que acontece a seguir</p>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="width:26px; height:26px; background:#d66d7f; color:#fff; border-radius:50%;
                                    display:flex; align-items:center; justify-content:center;
                                    font-size:12px; font-weight:700; flex-shrink:0;">1</div>
                        <div style="font-size:14px; color:#444; line-height:1.5;">
                            <strong>A Sylvia analisa o seu pedido</strong><br>
                            <span style="color:#888;">Normalmente em menos de 24 horas.</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="width:26px; height:26px; background:#d66d7f; color:#fff; border-radius:50%;
                                    display:flex; align-items:center; justify-content:center;
                                    font-size:12px; font-weight:700; flex-shrink:0;">2</div>
                        <div style="font-size:14px; color:#444; line-height:1.5;">
                            <strong>Recebe um contacto por telefone</strong><br>
                            <span style="color:#888;">Para confirmar os detalhes e o valor final.</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="width:26px; height:26px; background:#d66d7f; color:#fff; border-radius:50%;
                                    display:flex; align-items:center; justify-content:center;
                                    font-size:12px; font-weight:700; flex-shrink:0;">3</div>
                        <div style="font-size:14px; color:#444; line-height:1.5;">
                            <strong>Recebe o link de pagamento por email</strong><br>
                            <span style="color:#888;">Pagamento seguro por cartão ou MB Way.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTAs -->
            <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                <a href="catalogo.php" class="orc-btn"
                   style="display:inline-flex; align-items:center; gap:8px;
                          width:auto; padding:13px 26px; text-decoration:none;">
                    <i class="fas fa-images"></i> Ver o catálogo
                </a>
                <?php if (isset($_SESSION['cliente_id'])): ?>
                <a href="cliente/encomendas.php"
                   style="display:inline-flex; align-items:center; gap:8px;
                          padding:13px 26px; text-decoration:none; font-weight:600;
                          color:#d66d7f; background:#fff; border:2px solid #f0c8d2;
                          border-radius:999px; font-size:15px; transition:0.2s;">
                    <i class="fas fa-box"></i> As minhas encomendas
                </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>

        <h1 class="orc-title">Pedir Orçamento</h1>
        <p class="orc-subtitle">Conte-nos o que pretende — fazemos o orçamento sob medida.</p>

        <?php if ($itemInspiracao): ?>
            <div class="orc-inspiracao">
                <i class="fas fa-image" style="font-size:22px; color:#d66d7f;"></i>
                <div>
                    <strong>Inspiração:</strong> "<?= htmlspecialchars($itemInspiracao['nome']) ?>"
                    <?php if ($itemInspiracao['categoria']): ?>
                        <span style="color:#888;"> &middot; <?= htmlspecialchars($itemInspiracao['categoria']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$clienteLogado): ?>
            <div style="text-align:center; margin-bottom:18px; color:#666; font-size:14px;">
                Já é cliente?
                <a href="cliente/login.php?redirect=../pedir-orcamento.php<?= $inspiracaoId ? '?inspiracao=' . $inspiracaoId : '' ?>" style="color:#d66d7f; font-weight:600;">
                    Faça login
                </a>
                para preencher os dados automaticamente.
            </div>
        <?php else: ?>
            <div style="text-align:center; margin-bottom:18px; color:#1f6b35; font-size:14px; background:#edf9f0; padding:10px; border-radius:10px;">
                ✓ Olá <strong><?= htmlspecialchars($clienteLogado['nome']) ?></strong> — dados pré-preenchidos.
            </div>
        <?php endif; ?>

        <?php if ($mensagem): ?>
            <div class="orc-alert <?= $tipo_mensagem === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($mensagem) ?>
                <?php if (!empty($erros)): ?>
                    <ul style="margin: 8px 0 0 18px;">
                        <?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" novalidate>
            <?= csrf_input() ?>
            <input type="hidden" name="portfolio_inspiracao_id" value="<?= $inspiracaoId ?>">

            <!-- DADOS PESSOAIS -->
            <div class="orc-section">Dados Pessoais</div>
            <div class="orc-grid">
                <!-- Cada campo tem um <label> com for= ligado ao id= do input.
                     Isto é obrigatório para acessibilidade (WCAG 1.3.1):
                     leitores de ecrã anunciam o label ao utilizador. -->
                <div class="orc-group full">
                    <label for="orc_nome" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Nome Completo <span style="color:#d66d7f;">*</span>
                    </label>
                    <input type="text" id="orc_nome" name="nome" placeholder="Ex: Maria Silva"
                           value="<?= htmlspecialchars($val('nome')) ?>" required>
                </div>
                <div class="orc-group">
                    <label for="orc_email" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Email <span style="color:#d66d7f;">*</span>
                    </label>
                    <input type="email" id="orc_email" name="email" placeholder="Ex: maria@email.com"
                           value="<?= htmlspecialchars($val('email')) ?>"
                           <?= $clienteLogado ? 'readonly' : '' ?> required>
                </div>
                <div class="orc-group">
                    <label for="orc_telefone" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Telemóvel <span style="color:#d66d7f;">*</span>
                    </label>
                    <input type="text" id="orc_telefone" name="telefone" placeholder="Ex: 912 345 678"
                           value="<?= htmlspecialchars($val('telefone')) ?>" required>
                </div>
            </div>

            <!-- O QUE PRETENDE -->
            <div class="orc-section">O Que Pretende</div>
            <div class="orc-grid">
                <div class="orc-group full">
                    <label for="orc_categoria" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Tipo de Peça
                    </label>
                    <select id="orc_categoria" name="categoria_id">
                        <option value="0">Selecionar (opcional)</option>
                        <?php foreach ($todasCategorias as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= ($_POST['categoria_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="orc-group full">
                    <label for="orc_descricao" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Descrição do Pedido <span style="color:#d66d7f;">*</span>
                    </label>
                    <textarea id="orc_descricao" name="descricao" required
                              placeholder="Descreva com detalhe: nomes a bordar, cores, tamanho, ocasião, prazo..."><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- FOTOS DE INSPIRAÇÃO -->
            <div class="orc-section">Fotos de Inspiração (opcional)</div>
            <p style="color:#666; font-size:13px; margin: -8px 0 12px;">
                Pode juntar até 3 fotos do que tem em mente — modelos que viu, cores, estilo, etc.
                (JPG, PNG, GIF, WEBP, máx 5 MB cada).
            </p>
            <div class="orc-uploads">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <label class="orc-upload-box" id="upBox<?= $i ?>">
                        <i class="fas fa-image"></i>
                        <span id="upTxt<?= $i ?>">Foto <?= $i + 1 ?></span>
                        <input type="file" name="inspiracao[]" accept="image/*"
                               onchange="onPickFoto(this, <?= $i ?>)">
                    </label>
                <?php endfor; ?>
            </div>

            <!-- ENTREGA -->
            <div class="orc-section">Entrega</div>
            <div class="orc-grid">
                <div class="orc-group">
                    <label for="orc_tipo_entrega" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Forma de Entrega
                    </label>
                    <select id="orc_tipo_entrega" name="tipo_entrega">
                        <option value="domicilio" <?= ($_POST['tipo_entrega'] ?? 'domicilio') === 'domicilio' ? 'selected' : '' ?>>
                            Entrega ao Domicílio
                        </option>
                        <option value="levantamento_atelier" <?= ($_POST['tipo_entrega'] ?? '') === 'levantamento_atelier' ? 'selected' : '' ?>>
                            Levantamento no Atelier
                        </option>
                    </select>
                </div>
                <div class="orc-group">
                    <label for="orc_prazo" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Prazo Desejado
                    </label>
                    <input type="date" id="orc_prazo" name="prazo_entrega_desejado"
                           value="<?= htmlspecialchars($_POST['prazo_entrega_desejado'] ?? date('Y-m-d', strtotime('+21 days'))) ?>"
                           min="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
                <div class="orc-group full">
                    <label for="orc_morada" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Morada
                    </label>
                    <input type="text" id="orc_morada" name="morada" placeholder="Rua, número, andar (se entrega ao domicílio)"
                           value="<?= htmlspecialchars($val('morada')) ?>">
                </div>
                <div class="orc-group">
                    <label for="orc_cp" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Código Postal
                    </label>
                    <input type="text" id="orc_cp" name="codigo_postal" placeholder="Ex: 8700-123"
                           value="<?= htmlspecialchars($val('codigo_postal')) ?>">
                </div>
                <div class="orc-group">
                    <label for="orc_localidade" style="display:block; margin-bottom:4px; font-weight:600; color:#555; font-size:14px;">
                        Localidade
                    </label>
                    <input type="text" id="orc_localidade" name="localidade" placeholder="Ex: Olhão"
                           value="<?= htmlspecialchars($val('localidade')) ?>">
                </div>
            </div>

            <!-- INFO -->
            <div class="orc-info-box">
                <strong>Como funciona:</strong> recebemos o seu pedido, analisamos a personalização
                e contactamos por telefone ou email com o orçamento final.
                Recebe um link seguro para pagamento (cartão ou MB Way).
                Após pagamento, iniciamos a produção e enviamos para a morada indicada.
            </div>

            <button type="submit" class="orc-btn">
                <i class="fas fa-paper-plane"></i> Enviar Pedido de Orçamento
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
function onPickFoto(input, idx) {
    const box = document.getElementById('upBox' + idx);
    const txt = document.getElementById('upTxt' + idx);
    if (input.files && input.files[0]) {
        box.classList.add('has-file');
        txt.textContent = '✓ ' + input.files[0].name.substring(0, 16) + (input.files[0].name.length > 16 ? '...' : '');
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
