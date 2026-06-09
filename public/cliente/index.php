<?php
/**
 * =============================================================================
 *  DASHBOARD DO CLIENTE - "Minha Conta"
 * =============================================================================
 *
 *  Página inicial da área de cliente após login. Mostra:
 *    - Saudação personalizada
 *    - Resumo de pedidos (total + pendentes de pagamento)
 *    - Cartões com atalhos para "Os meus dados", "As minhas encomendas", etc.
 *
 *  Acesso protegido por auth.php (redireciona para login se não autenticado).
 * =============================================================================
 */

require_once __DIR__ . '/auth.php';                    // exige login
require_once __DIR__ . '/../../config/db.php';

$clienteId = $_SESSION['cliente_id'];

// === Dados básicos do cliente para a saudação ===
$stmt = $conn->prepare("SELECT nome, email FROM utilizador WHERE id = ?");
$stmt->execute([$clienteId]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// === Estatísticas: nº total de pedidos ===
$stmtCount = $conn->prepare("SELECT COUNT(*) AS total FROM pedido WHERE utilizador_id = ?");
$stmtCount->execute([$clienteId]);
$totalPedidos = (int)($stmtCount->fetchColumn() ?: 0);

// === Estatísticas: pedidos com pagamento por validar ===
// (útil para alertar o cliente que tem algo pendente)
$stmtPend = $conn->prepare("
    SELECT COUNT(*) FROM pedido p
    LEFT JOIN pagamento pg ON pg.pedido_id = p.id
    WHERE p.utilizador_id = ?
      AND pg.estado_pagamento IN ('analise_pagamento', 'recusado')
      AND p.estado NOT IN ('cancelado', 'entregue')
");
$stmtPend->execute([$clienteId]);
$pendentes = (int)($stmtPend->fetchColumn() ?: 0);

// Mostra mensagem de boas-vindas se vier do registo (?bemvindo=1)
$bemvindo = isset($_GET['bemvindo']);

// Confirmação após reset de password (?pwd_resetada=1)
$pwdResetada = isset($_GET['pwd_resetada']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Conta - SylviArtes</title>
    <!-- Favicon: logotipo no separador do browser -->
    <link rel="icon" type="image/png" href="../imagens/logo_sylviartes.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="cliente_style.css">
</head>
<body>
    <div class="cli-wrapper">
        <a href="../index.php" class="cli-back">
            <i class="fas fa-arrow-left"></i> Voltar à loja
        </a>

        <!-- Cabeçalho com saudação -->
        <div class="cli-header">
            <h1>Olá, <?php echo htmlspecialchars($cliente['nome'] ?? ''); ?></h1>
            <p>
                <?php if ($bemvindo): ?>
                    Conta criada com sucesso. Bem-vindo(a) à SylviArtes!
                <?php elseif ($pwdResetada): ?>
                    Password atualizada com sucesso. Já pode usá-la nos próximos logins.
                <?php else: ?>
                    Tem <strong><?php echo $totalPedidos; ?></strong>
                    <?php echo $totalPedidos === 1 ? 'pedido registado' : 'pedidos registados'; ?>.
                    <?php if ($pendentes > 0): ?>
                        - <strong><?php echo $pendentes; ?></strong>
                        com pagamento pendente.
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Cartões de atalho -->
        <div class="cli-grid">
            <a class="cli-card" href="perfil.php">
                <span class="icone"><i class="fas fa-user"></i></span>
                <h3>Os meus dados</h3>
                <p>Atualize nome, morada, telefone e password.</p>
            </a>

            <a class="cli-card" href="encomendas.php">
                <span class="icone"><i class="fas fa-box-open"></i></span>
                <h3>As minhas encomendas</h3>
                <p>Veja o estado dos pedidos e acompanhe-os.</p>
            </a>

            <a class="cli-card" href="../catalogo.php">
                <span class="icone"><i class="fas fa-shopping-bag"></i></span>
                <h3>Continuar a comprar</h3>
                <p>Explore o catálogo e faça nova encomenda.</p>
            </a>

            <a class="cli-card" href="logout.php">
                <span class="icone"><i class="fas fa-sign-out-alt"></i></span>
                <h3>Terminar sessão</h3>
                <p>Saia em segurança deste dispositivo.</p>
            </a>
        </div>
    </div>
</body>
</html>
