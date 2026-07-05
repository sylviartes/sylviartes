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

require_once __DIR__ . '/env.php';

if (!defined('STRIPE_PUBLISHABLE_KEY')) define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_SUBSTITUI_AQUI');
if (!defined('STRIPE_SECRET_KEY'))      define('STRIPE_SECRET_KEY',      getenv('STRIPE_SECRET_KEY')      ?: 'sk_test_SUBSTITUI_AQUI');
if (!defined('STRIPE_WEBHOOK_SECRET'))  define('STRIPE_WEBHOOK_SECRET',  getenv('STRIPE_WEBHOOK_SECRET')  ?: 'whsec_SUBSTITUI_AQUI');
if (!defined('STRIPE_CURRENCY'))        define('STRIPE_CURRENCY',        getenv('STRIPE_CURRENCY')        ?: 'eur');

// URL base do site (ajusta se mudares de porta/domínio)
if (!defined('SITE_BASE_URL')) define('SITE_BASE_URL', getenv('SITE_BASE_URL') ?: 'http://localhost:8080');

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

    // Métodos de pagamento aceites em Portugal: Cartão, MB Way e Multibanco.
    // (O parâmetro $metodo é mantido por compatibilidade, mas oferecemos os três.)
    $paymentMethodTypes = ['card', 'mb_way', 'multibanco'];

    // URL base de CONFIANÇA (SITE_BASE_URL, sem o prefixo /public, que não existe no
    // URL de produção). Em produção é https://sylviartes.pt.
    $baseUrl = rtrim(SITE_BASE_URL, '/');

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
        // Repete o pedido_id no PaymentIntent, útil para reconciliação/webhooks.
        'payment_intent_data' => [
            'metadata' => ['pedido_id' => (string) $pedidoId],
        ],
        'success_url' => $baseUrl . '/stripe_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $baseUrl . '/stripe_cancel.php?pedido_id=' . $pedidoId,
    ]);

    return $session;
}

/**
 * [LEGADO / ABORDAGEM INICIAL] Cria um Stripe Payment Link para um orçamento.
 *
 * Esta foi a PRIMEIRA abordagem ao pagamento de valor variável. Foi substituída
 * por criar_fatura_stripe() (ver abaixo), porque os Payment Links não permitem,
 * de forma segura, recolher morada de faturação/envio nem emitir um documento
 * de fatura formal. Mantida apenas como referência da evolução do projeto.
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

/**
 * [SOLUÇÃO ADOTADA] Emite uma FATURA dinâmica (Stripe Billing / Invoices).
 *
 * Esta é a forma usada pela SylviArtes para cobrar orçamentos de valor variável.
 * Ao contrário dos Payment Links, a fatura:
 *   - regista um Cliente Stripe com morada de FATURAÇÃO e de ENVIO;
 *   - gera um documento de fatura formal (com número e PDF);
 *   - é enviada por email pela Stripe e tem prazo de vencimento;
 *   - aceita cartão e MB Way na página de pagamento alojada (hosted invoice).
 *
 * Fluxo (depois de a proprietária fechar o preço com o cliente):
 *   1. Cria/atualiza o Cliente Stripe (com address = faturação e shipping = envio);
 *   2. Cria um InvoiceItem com o valor exato do orçamento (em cêntimos);
 *   3. Cria a Invoice (collection_method = send_invoice, vencimento em N dias);
 *   4. Finaliza a fatura -> gera número, PDF e hosted_invoice_url.
 *
 * @param int    $pedidoId        ID do pedido
 * @param float  $valorTotal      Valor final do orçamento em euros
 * @param array  $cliente         ['nome','email','morada','codigo_postal','localidade','telefone']
 * @param string $descricao       Descrição da peça (linha da fatura)
 * @param int    $diasVencimento  Prazo de pagamento (dias)
 * @return \Stripe\Invoice         Fatura finalizada (->hosted_invoice_url, ->invoice_pdf, ->number, ->id)
 */
function criar_fatura_stripe(
    int $pedidoId,
    float $valorTotal,
    array $cliente,
    string $descricao = '',
    int $diasVencimento = 7
) {
    stripe_init();

    // --- 1. Cliente Stripe com morada de faturação e de envio ---
    $dadosCliente = [
        'name'     => $cliente['nome']  ?? '',
        'email'    => $cliente['email'] ?? '',
        'metadata' => ['pedido_id' => (string) $pedidoId],
    ];

    if (!empty($cliente['morada'])) {
        // Morada usada tanto para faturação como para envio (PT)
        $morada = [
            'line1'       => $cliente['morada'],
            'postal_code' => $cliente['codigo_postal'] ?? '',
            'city'        => $cliente['localidade']    ?? '',
            'country'     => 'PT',
        ];
        $dadosCliente['address']  = $morada;                 // endereço de FATURAÇÃO
        $dadosCliente['shipping'] = [                        // endereço de ENVIO
            'name'    => $cliente['nome'] ?? '',
            'phone'   => $cliente['telefone'] ?? null,
            'address' => $morada,
        ];
    }

    $customer = \Stripe\Customer::create($dadosCliente);

    // --- 2. Criar a fatura PRIMEIRO (vazia), com envio por email e prazo de vencimento ---
    // É criada antes da linha para podermos ligar o item a ESTA fatura de forma explícita.
    // (Se criássemos o item antes da fatura, o item ficava "pendente" e podia não ser
    //  anexado, resultando numa fatura de 0 EUR marcada como paga.)
    //
    // Métodos de pagamento mostrados: Cartão + MB Way (Portugal). Se o MB Way não
    // estiver ativado na conta Stripe, o pedido é rejeitado; nesse caso repetimos só
    // com cartão, para o link de pagamento nunca falhar.
    $dadosFatura = [
        'customer'          => $customer->id,
        'collection_method' => 'send_invoice',
        'days_until_due'    => $diasVencimento,
        'description'       => 'SylviArtes - Pedido #' . $pedidoId,
        'metadata'          => ['pedido_id' => (string) $pedidoId],
        'auto_advance'      => false,
        'payment_settings'  => ['payment_method_types' => ['card', 'mb_way']],
    ];
    try {
        $invoice = \Stripe\Invoice::create($dadosFatura);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        // Provavelmente o MB Way não está ativado na conta -> usa apenas cartão
        $dadosFatura['payment_settings']['payment_method_types'] = ['card'];
        $invoice = \Stripe\Invoice::create($dadosFatura);
    }

    // --- 3. Linha da fatura: valor do orçamento (em cêntimos), ligada a ESTA fatura ---
    \Stripe\InvoiceItem::create([
        'customer'    => $customer->id,
        'invoice'     => $invoice->id,
        'amount'      => (int) round($valorTotal * 100),
        'currency'    => STRIPE_CURRENCY,
        'description' => $descricao ?: ('Bordado personalizado - Pedido #' . $pedidoId),
    ]);

    // --- 4. Finalizar -> gera número de fatura, PDF e URL de pagamento alojada ---
    $invoice = $invoice->finalizeInvoice();

    return $invoice;
}
