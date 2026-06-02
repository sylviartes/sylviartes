<?php
/**
 * =============================================================================
 *  ADMIN — Aplicar Migrações de Base de Dados
 * =============================================================================
 *
 *  Verifica se as 3 atualizações de base de dados foram aplicadas
 *  e permite aplicá-las com um clique, sem precisar do phpMyAdmin.
 * =============================================================================
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../auth.php';

// ── Verificar estado de cada migração ────────────────────────────────────────

// Migração 1 — coluna produto_id na tabela avaliacao
$migr1 = false;
try {
    $migr1 = (bool)$conn->query("SHOW COLUMNS FROM avaliacao LIKE 'produto_id'")->fetch();
} catch (Exception $e) {}

// Migração 2 — tabela pedido_inspiracao
$migr2 = false;
try {
    $migr2 = (bool)$conn->query("SHOW TABLES LIKE 'pedido_inspiracao'")->fetch();
} catch (Exception $e) {}

// Migração 3 — coluna stripe_payment_link_url na tabela pagamento
$migr3 = false;
try {
    $migr3 = (bool)$conn->query("SHOW COLUMNS FROM pagamento LIKE 'stripe_payment_link_url'")->fetch();
} catch (Exception $e) {}

// ── Aplicar migrações pendentes (POST) ───────────────────────────────────────

$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aplicar'])) {
    $erros = [];
    $aplicadas = 0;

    if (!$migr1) {
        try {
            $conn->exec("ALTER TABLE avaliacao
                ADD COLUMN produto_id INT NULL AFTER utilizador_id,
                ADD INDEX idx_aval_produto (produto_id)");
            $conn->exec("ALTER TABLE avaliacao
                ADD UNIQUE KEY uniq_aval_user_produto (utilizador_id, produto_id)");
            $migr1 = true;
            $aplicadas++;
        } catch (Exception $e) {
            // Ignora se já existir (código 1060 = coluna duplicada, 1061 = índice duplicado)
            if (strpos($e->getMessage(), 'Duplicate') !== false || $e->getCode() == 1060 || $e->getCode() == 1061) {
                $migr1 = true;
                $aplicadas++;
            } else {
                $erros[] = "Avaliações: " . $e->getMessage();
            }
        }
    }

    if (!$migr2) {
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS pedido_inspiracao (
                id        INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                imagem    MEDIUMBLOB NOT NULL,
                ordem     TINYINT NOT NULL DEFAULT 1,
                CONSTRAINT fk_pi_pedido FOREIGN KEY (pedido_id)
                    REFERENCES pedido(id) ON DELETE CASCADE
            )");
            $conn->exec("ALTER TABLE pedido
                ADD COLUMN IF NOT EXISTS portfolio_inspiracao_id INT NULL");
            $migr2 = true;
            $aplicadas++;
        } catch (Exception $e) {
            $erros[] = "Fotos de Inspiração: " . $e->getMessage();
        }
    }

    if (!$migr3) {
        try {
            $conn->exec("ALTER TABLE pagamento
                ADD COLUMN IF NOT EXISTS stripe_payment_link_url VARCHAR(500) NULL");
            $migr3 = true;
            $aplicadas++;
        } catch (Exception $e) {
            $erros[] = "Link de Pagamento: " . $e->getMessage();
        }
    }

    if (empty($erros)) {
        if ($aplicadas > 0) {
            $mensagem = "Todas as migrações foram aplicadas com sucesso!";
        } else {
            $mensagem = "Nada a fazer — todas as migrações já estavam aplicadas.";
        }
        $tipoMensagem = 'sucesso';
    } else {
        $mensagem = "Ocorreram erros: " . implode(' | ', $erros);
        $tipoMensagem = 'erro';
    }
}

$todasFeitas = $migr1 && $migr2 && $migr3;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrações — SylviArtes Admin</title>
    <link rel="stylesheet" href="../admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }

        .migr-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
            padding: 28px 32px;
            max-width: 700px;
            margin-bottom: 24px;
        }

        .migr-linha {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .migr-linha:last-child { border-bottom: none; }

        .migr-icon {
            font-size: 22px;
            width: 32px;
            text-align: center;
            flex-shrink: 0;
        }

        .migr-info { flex: 1; }
        .migr-info strong { display: block; font-size: 15px; color: #2d3436; }
        .migr-info small { color: #888; font-size: 13px; }

        .badge-ok {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-pendente {
            background: #fff3e0;
            color: #e65100;
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-aplicar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #d66d7f, #e8a4b0);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 28px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .btn-aplicar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(214,109,127,0.30);
        }
        .btn-aplicar:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .aviso-sucesso {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            border-radius: 10px;
            padding: 14px 20px;
            color: #2e7d32;
            margin-bottom: 20px;
            font-weight: 500;
            max-width: 700px;
        }
        .aviso-erro {
            background: #fdecea;
            border-left: 4px solid #e53935;
            border-radius: 10px;
            padding: 14px 20px;
            color: #b71c1c;
            margin-bottom: 20px;
            font-weight: 500;
            max-width: 700px;
        }
        .aviso-completo {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            border-left: 4px solid #4caf50;
            border-radius: 12px;
            padding: 20px 24px;
            color: #2e7d32;
            max-width: 700px;
            margin-bottom: 24px;
        }
    </style>
</head>
<body class="admin-body">
    <?php require_once __DIR__ . '/../sidebar.php'; ?>

    <div class="main-content">
        <h1 style="font-family:'Playfair Display',serif; color:#2d3436; margin-bottom:6px;">
            <i class="fas fa-database"></i> Migrações de Base de Dados
        </h1>
        <p style="color:#888; margin-bottom:28px; font-size:14px;">
            Aplica as atualizações necessárias para ativar todas as funcionalidades do site.
        </p>

        <?php if ($mensagem): ?>
            <div class="<?= $tipoMensagem === 'sucesso' ? 'aviso-sucesso' : 'aviso-erro' ?>">
                <i class="fas fa-<?= $tipoMensagem === 'sucesso' ? 'check-circle' : 'times-circle' ?>"></i>
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <?php if ($todasFeitas): ?>
            <div class="aviso-completo">
                <i class="fas fa-check-circle"></i>
                <strong> Tudo em ordem!</strong> Todas as funcionalidades estão ativas.
            </div>
        <?php endif; ?>

        <div class="migr-card">
            <div class="migr-linha">
                <div class="migr-icon" style="color:#d66d7f;">
                    <i class="fas fa-star"></i>
                </div>
                <div class="migr-info">
                    <strong>Avaliações por Produto</strong>
                    <small>Associa cada avaliação a um produto específico do portfólio</small>
                </div>
                <?php if ($migr1): ?>
                    <span class="badge-ok"><i class="fas fa-check"></i> Concluída</span>
                <?php else: ?>
                    <span class="badge-pendente"><i class="fas fa-clock"></i> Pendente</span>
                <?php endif; ?>
            </div>

            <div class="migr-linha">
                <div class="migr-icon" style="color:#d66d7f;">
                    <i class="fas fa-images"></i>
                </div>
                <div class="migr-info">
                    <strong>Fotos de Inspiração</strong>
                    <small>Permite que clientes enviem fotos de inspiração nas encomendas</small>
                </div>
                <?php if ($migr2): ?>
                    <span class="badge-ok"><i class="fas fa-check"></i> Concluída</span>
                <?php else: ?>
                    <span class="badge-pendente"><i class="fas fa-clock"></i> Pendente</span>
                <?php endif; ?>
            </div>

            <div class="migr-linha">
                <div class="migr-icon" style="color:#d66d7f;">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="migr-info">
                    <strong>Link de Pagamento Stripe</strong>
                    <small>Guarda e reenvia links de pagamento por email às clientes</small>
                </div>
                <?php if ($migr3): ?>
                    <span class="badge-ok"><i class="fas fa-check"></i> Concluída</span>
                <?php else: ?>
                    <span class="badge-pendente"><i class="fas fa-clock"></i> Pendente</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$todasFeitas): ?>
            <form method="POST">
                <button type="submit" name="aplicar" class="btn-aplicar">
                    <i class="fas fa-play"></i>
                    Aplicar Migrações Pendentes
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
