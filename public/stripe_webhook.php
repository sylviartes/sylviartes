<?php
/**
 * Webhook do Stripe - endpoint público.
 *
 * Configurar no Stripe Dashboard ou via CLI:
 *   stripe listen --forward-to localhost:8080/public/stripe_webhook.php
 *
 * Eventos tratados:
 *   FATURAS (método atual - Stripe Billing/Invoices):
 *   - invoice.paid / invoice.payment_succeeded → fatura paga (cartão ou MB Way)
 *   - invoice.payment_failed                   → pagamento da fatura recusado
 *
 *   CHECKOUT/PAYMENT LINKS (método legado, mantido por compatibilidade):
 *   - checkout.session.completed                → pagamento bem-sucedido (cartão)
 *   - checkout.session.async_payment_succeeded  → MB Way confirmado
 *   - checkout.session.async_payment_failed     → MB Way recusado
 *
 *  Nas faturas, o pedido é identificado por invoice.metadata.pedido_id (definido
 *  em criar_fatura_stripe(), config/stripe.php).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/stripe.php';

if (!stripe_disponivel()) {
    http_response_code(500);
    echo "Stripe SDK não instalado.";
    exit;
}

stripe_init();

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, STRIPE_WEBHOOK_SECRET);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Payload inválido');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Assinatura inválida');
}

function atualizar_pagamento(PDO $conn, string $sessionId, string $estado, ?string $paymentIntentId = null): ?int
{
    $stmt = $conn->prepare("SELECT id, pedido_id FROM pagamento WHERE stripe_session_id = ?");
    $stmt->execute([$sessionId]);
    $pag = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pag) return null;

    $stmt = $conn->prepare("
        UPDATE pagamento
        SET estado_pagamento = ?, stripe_payment_intent_id = COALESCE(?, stripe_payment_intent_id)
        WHERE id = ?
    ");
    $stmt->execute([$estado, $paymentIntentId, $pag['id']]);

    return (int)$pag['pedido_id'];
}

function avancar_pedido(PDO $conn, int $pedidoId, string $novoEstado): void
{
    $stmt = $conn->prepare("SELECT estado FROM pedido WHERE id=?");
    $stmt->execute([$pedidoId]);
    $estadoAnt = $stmt->fetchColumn();
    if (!$estadoAnt || $estadoAnt === $novoEstado) return;

    $stmt = $conn->prepare("UPDATE pedido SET estado=? WHERE id=?");
    $stmt->execute([$novoEstado, $pedidoId]);

    $stmt = $conn->prepare("
        INSERT INTO log_alteracoes_pedido (pedido_id, estado_anterior, estado_novo, alterado_por)
        VALUES (?, ?, ?, 'stripe_webhook')
    ");
    $stmt->execute([$pedidoId, $estadoAnt, $novoEstado]);
}

switch ($event->type) {
    // ===== FATURAS (Stripe Billing / Invoices) - método atual =====
    case 'invoice.paid':
    case 'invoice.payment_succeeded':
        // A fatura traz o pedido_id nos metadados (definido em criar_fatura_stripe)
        $invoice  = $event->data->object;
        $pedidoId = (int)($invoice->metadata->pedido_id ?? 0);
        if ($pedidoId > 0) {
            // Marca o pagamento desse pedido como validado
            $stmt = $conn->prepare("UPDATE pagamento SET estado_pagamento = 'validado' WHERE pedido_id = ?");
            $stmt->execute([$pedidoId]);
            // Avança o pedido para produção (com registo no log)
            avancar_pedido($conn, $pedidoId, 'em_producao');
        }
        break;

    case 'invoice.payment_failed':
        // Pagamento da fatura recusado -> marca o pagamento como recusado
        $invoice  = $event->data->object;
        $pedidoId = (int)($invoice->metadata->pedido_id ?? 0);
        if ($pedidoId > 0) {
            $stmt = $conn->prepare("UPDATE pagamento SET estado_pagamento = 'recusado' WHERE pedido_id = ?");
            $stmt->execute([$pedidoId]);
        }
        break;

    // ===== CHECKOUT / PAYMENT LINKS (método legado) =====
    case 'checkout.session.completed':
        $session = $event->data->object;
        if ($session->payment_status === 'paid') {
            $pedidoId = atualizar_pagamento($conn, $session->id, 'validado', $session->payment_intent ?? null);
            if ($pedidoId) avancar_pedido($conn, $pedidoId, 'em_producao');
        }
        break;

    case 'checkout.session.async_payment_succeeded':
        $session = $event->data->object;
        $pedidoId = atualizar_pagamento($conn, $session->id, 'validado', $session->payment_intent ?? null);
        if ($pedidoId) avancar_pedido($conn, $pedidoId, 'em_producao');
        break;

    case 'checkout.session.async_payment_failed':
        $session = $event->data->object;
        atualizar_pagamento($conn, $session->id, 'recusado', $session->payment_intent ?? null);
        break;
}

http_response_code(200);
echo "ok";
