<?php
/**
 * Configuração Stripe - SylviArtes
 *
 * SETUP:
 * 1. Correr na raiz do projeto:  composer require stripe/stripe-php
 * 2. Obter as chaves em https://dashboard.stripe.com/test/apikeys
 * 3. Substituir as constantes abaixo pelas chaves reais (modo TESTE para PAP).
 * 4. Para o webhook: instalar Stripe CLI e correr
 *      stripe listen --forward-to localhost:8080/public/stripe_webhook.php
 *    Copiar o whsec_... mostrado pela CLI para STRIPE_WEBHOOK_SECRET.
 */

if (!defined('STRIPE_PUBLISHABLE_KEY')) define('STRIPE_PUBLISHABLE_KEY', 'pk_test_SUBSTITUI_AQUI');
if (!defined('STRIPE_SECRET_KEY'))      define('STRIPE_SECRET_KEY',      'sk_test_SUBSTITUI_AQUI');
if (!defined('STRIPE_WEBHOOK_SECRET'))  define('STRIPE_WEBHOOK_SECRET',  'whsec_SUBSTITUI_AQUI');
if (!defined('STRIPE_CURRENCY'))        define('STRIPE_CURRENCY',        'eur');

// URL base do site (ajusta se mudares de porta/domínio)
if (!defined('SITE_BASE_URL')) define('SITE_BASE_URL', 'http://localhost:8080');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Verifica se o SDK do Stripe está instalado.
 */
function stripe_disponivel(): bool
{
    return class_exists('\\Stripe\\Stripe');
}

/**
 * Inicializa o SDK do Stripe (chama antes de qualquer chamada à API).
 */
function stripe_init(): void
{
    if (!stripe_disponivel()) {
        throw new RuntimeException(
            'SDK do Stripe não está instalado. Corre: composer require stripe/stripe-php'
        );
    }
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
}

/**
 * Cria uma Checkout Session do Stripe para um pedido.
 *
 * @param int    $pedidoId   ID do pedido
 * @param float  $valorTotal Valor total em euros
 * @param string $metodo     'cartao' ou 'mbway'
 * @param string $email      Email do cliente
 * @param string $descricao  Descrição mostrada no Stripe
 *
 * @return \Stripe\Checkout\Session
 */
function criar_checkout_session(int $pedidoId, float $valorTotal, string $metodo, string $email, string $descricao = '')
{
    stripe_init();

    // Métodos de pagamento aceites
    $paymentMethodTypes = ['card'];
    if ($metodo === 'mbway') {
        // Stripe expõe MB Way para Portugal
        $paymentMethodTypes = ['mb_way'];
    }

    $session = \Stripe\Checkout\Session::create([
        'mode' => 'payment',
        'payment_method_types' => $paymentMethodTypes,
        'customer_email' => $email ?: null,
        'line_items' => [[
            'price_data' => [
                'currency' => STRIPE_CURRENCY,
                'unit_amount' => (int) round($valorTotal * 100), // cêntimos
                'product_data' => [
                    'name' => 'SylviArtes - Pedido #' . $pedidoId,
                    'description' => $descricao ?: 'Pagamento da encomenda na SylviArtes',
                ],
            ],
            'quantity' => 1,
        ]],
        'metadata' => [
            'pedido_id' => (string) $pedidoId,
        ],
        'success_url' => SITE_BASE_URL . '/public/stripe_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => SITE_BASE_URL . '/public/stripe_cancel.php?pedido_id=' . $pedidoId,
    ]);

    return $session;
}

/**
 * Cria um Stripe Payment Link para um orçamento.
 *
 * Usado pelo admin (mãe) DEPOIS de telefonar à cliente e fechar o preço.
 * Devolve um URL público que pode ser enviado por email — a cliente paga
 * quando quiser, com cartão ou MB Way.
 *
 * Diferente de criar_checkout_session():
 *   - Não tem success/cancel URLs fixas (cliente fica em página Stripe ao pagar)
 *   - URL é permanente até ao admin desativar o link no dashboard
 *   - Stripe envia evento checkout.session.completed quando alguém paga
 *
 * @param int    $pedidoId   ID do pedido
 * @param float  $valorTotal Valor final em euros (já ajustado pela admin)
 * @param string $email      Email do cliente (para pré-preencher)
 * @param string $descricao  Texto a mostrar na página Stripe
 * @return \Stripe\PaymentLink
 */
function criar_payment_link(int $pedidoId, float $valorTotal, string $email, string $descricao = '')
{
    stripe_init();

    // 1. Criar um Product temporário no Stripe representando este pedido
    $product = \Stripe\Product::create([
        'name' => 'SylviArtes — Pedido #' . $pedidoId,
        'description' => $descricao ?: 'Bordado personalizado',
    ]);

    // 2. Criar Price para esse Product
    $price = \Stripe\Price::create([
        'unit_amount' => (int) round($valorTotal * 100),  // cêntimos
        'currency' => STRIPE_CURRENCY,
        'product' => $product->id,
    ]);

    // 3. Criar Payment Link com aceitação de Cartão e MB Way
    $paymentLink = \Stripe\PaymentLink::create([
        'line_items' => [[
            'price' => $price->id,
            'quantity' => 1,
        ]],
        'metadata' => [
            'pedido_id' => (string) $pedidoId,
            'email_cliente' => $email,
        ],
        // Após pagamento bem-sucedido, redireciona o cliente para a área dele
        'after_completion' => [
            'type' => 'redirect',
            'redirect' => [
                'url' => SITE_BASE_URL . '/public/stripe_success.php?session_id={CHECKOUT_SESSION_ID}',
            ],
        ],
    ]);

    return $paymentLink;
}
