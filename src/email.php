<?php
/**
 * =============================================================================
 *  ENVIO DE EMAIL - Helper SylviArtes
 * =============================================================================
 *
 *  Sistema com 3 níveis de fallback automático:
 *
 *    1. RESEND API (método principal) - envia a partir do domínio verificado
 *       (ex: noreply@sylviartes.pt). Entrega para qualquer cliente. Requer
 *       RESEND_API_KEY e RESEND_FROM preenchidos em config/.env.
 *
 *    2. Gmail SMTP (fallback) - só se o Resend falhar e o SMTP estiver configurado.
 *       Requer SMTP_HOST, SMTP_USER e SMTP_PASS em config/.env.
 *
 *    3. Outbox local (último recurso) - guarda como ficheiro .eml em
 *       docs/outbox/ para poder ver os emails sem internet.
 *
 *  O sistema escolhe automaticamente o primeiro nível configurado.
 * =============================================================================
 */

require_once __DIR__ . '/../config/env.php';

// =============================================================================
// CONSTANTES - lidas do config/.env (nunca hardcoded aqui)
// =============================================================================

// Gmail SMTP
if (!defined('SMTP_HOST'))      define('SMTP_HOST',      getenv('SMTP_HOST')      ?: '');
if (!defined('SMTP_PORT'))      define('SMTP_PORT',      getenv('SMTP_PORT') ? (int)getenv('SMTP_PORT') : 587);
if (!defined('SMTP_USER'))      define('SMTP_USER',      getenv('SMTP_USER')      ?: '');
if (!defined('SMTP_PASS'))      define('SMTP_PASS',      getenv('SMTP_PASS')      ?: '');
if (!defined('SMTP_FROM'))      define('SMTP_FROM',      getenv('SMTP_FROM')      ?: '');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'SylviArtes');

// Resend (fallback)
if (!defined('RESEND_API_KEY')) define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');
if (!defined('RESEND_FROM'))    define('RESEND_FROM',    getenv('RESEND_FROM')    ?: 'SylviArtes <onboarding@resend.dev>');

// Email da administradora (para receber avisos de pedidos novos)
if (!defined('ADMIN_EMAIL'))    define('ADMIN_EMAIL',    getenv('ADMIN_EMAIL')    ?: '');


/**
 * Envia um email. Ordem: Gmail SMTP → Resend → Outbox local.
 *
 * @param string $para      Email do destinatário
 * @param string $assunto   Assunto do email
 * @param string $htmlCorpo Corpo do email em HTML
 * @param string $replyTo   (opcional) Email para onde as respostas devem ir.
 *                          Ex: quando enviamos ao cliente, Reply-To = Gmail da Sylvia.
 * @return bool true se enviou ou guardou com sucesso
 */
function enviar_email(string $para, string $assunto, string $htmlCorpo, string $replyTo = ''): bool
{
    // ===========================================================================
    // Tentativa 1: Resend API (método PRINCIPAL - envia do domínio verificado,
    // ex: noreply@sylviartes.pt). Entrega para qualquer cliente.
    // ===========================================================================
    if (RESEND_API_KEY !== '' && function_exists('curl_init')) {
        $payload = [
            'from'    => RESEND_FROM,
            'to'      => [$para],
            'subject' => $assunto,
            'html'    => $htmlCorpo,
        ];
        // Adiciona Reply-To se fornecido (ex: para o cliente poder responder à Sylvia)
        if ($replyTo !== '') {
            $payload['reply_to'] = [$replyTo];
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . RESEND_API_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro     = curl_error($ch);

        if ($status >= 200 && $status < 300) {
            return true; // Email enviado via Resend
        }
        error_log("Resend falhou (HTTP $status): " . ($erro ?: $response));
    }

    // ===========================================================================
    // Tentativa 2: Gmail SMTP (fallback - só se o Resend falhar e o SMTP estiver
    // configurado). Requer PHPMailer + SMTP_HOST/USER/PASS no .env.
    // ===========================================================================
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASS !== '') {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true); // true = lança exceções em caso de erro

            // Configuração do servidor SMTP
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;         // smtp.gmail.com
            $mail->SMTPAuth   = true;               // exige autenticação
            $mail->Username   = SMTP_USER;          // sylviartes.pt@gmail.com
            $mail->Password   = SMTP_PASS;          // password de aplicação do Google
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // TLS (Gmail usa porta 587)
            $mail->Port       = SMTP_PORT;          // 587
            $mail->CharSet    = 'UTF-8';            // suporte a acentos e carateres especiais

            // Remetente e destinatário
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($para);

            // Reply-To: quando o destinatário clica "Responder", a mensagem vai para
            // este email (e não para o endereço técnico do SMTP)
            if ($replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }

            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body    = $htmlCorpo;
            $mail->AltBody = strip_tags($htmlCorpo); // versão sem HTML para clientes de email antigos

            $mail->send();
            return true; // Email enviado via Gmail SMTP

        } catch (Exception $e) {
            // SMTP falhou → loga e cai para a tentativa seguinte
            error_log("Falha SMTP Gmail: " . $e->getMessage());
        }
    }

    // ===========================================================================
    // Tentativa 3: Outbox local (funciona sempre, mesmo sem internet)
    // O email fica guardado em docs/outbox/ como ficheiro .eml
    // ===========================================================================
    $outboxDir = __DIR__ . '/../docs/outbox';
    if (!is_dir($outboxDir)) {
        @mkdir($outboxDir, 0755, true);
    }

    $nomeFich = $outboxDir . '/' . date('Y-m-d_His') . '_' . preg_replace('/[^a-z0-9]+/i', '_', $para) . '.eml';
    $conteudo = "Para: $para\n";
    $conteudo .= "De: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\n";
    if ($replyTo !== '') {
        $conteudo .= "Reply-To: $replyTo\n";
    }
    $conteudo .= "Assunto: $assunto\n";
    $conteudo .= "Data: " . date('r') . "\n";
    $conteudo .= "Content-Type: text/html; charset=UTF-8\n\n";
    $conteudo .= $htmlCorpo;

    return (bool) file_put_contents($nomeFich, $conteudo);
}


/**
 * Envia à cliente o email com o orçamento finalizado + link de pagamento Stripe.
 * Chamado pelo admin em admin/encomendas/enviar_link.php.
 *
 * @param string $email         Email da cliente
 * @param string $nome          Nome da cliente (para personalizar a saudação)
 * @param int    $pedidoId      ID do pedido
 * @param float  $valor         Valor final do orçamento (€)
 * @param string $linkPagamento URL do Stripe Payment Link
 * @param string $descricao     Descrição resumida do que foi pedido
 */
function enviar_email_orcamento(
    string $email,
    string $nome,
    int $pedidoId,
    float $valor,
    string $linkPagamento,
    string $descricao = ''
): bool {
    $valorFormatado = number_format($valor, 2, ',', '.') . ' €';
    $primeiroNome   = htmlspecialchars(explode(' ', $nome)[0] ?? 'Cliente');

    $corpo = '
    <div style="font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:30px; background:#fff;">
        <div style="text-align:center; margin-bottom:30px;">
            <h1 style="color:#d66d7f; font-family:Georgia,serif; margin:0;">SylviArtes</h1>
            <p style="color:#888; margin:4px 0 0; font-size:13px;">Costura Criativa &middot; Bordados Personalizados</p>
        </div>

        <h2 style="color:#2d3436;">Olá, ' . $primeiroNome . '!</h2>

        <p style="color:#555; line-height:1.6;">
            Obrigada pelo seu interesse no nosso trabalho. Já analisámos o seu pedido
            <strong>#' . $pedidoId . '</strong> e enviamos-lhe agora o orçamento final.
        </p>

        <div style="background:#fff8fa; border-left:4px solid #d66d7f; padding:20px; margin:25px 0; border-radius:6px;">
            <div style="color:#666; font-size:13px; text-transform:uppercase; letter-spacing:1px;">
                Valor do Orçamento
            </div>
            <div style="color:#d66d7f; font-size:32px; font-weight:bold; margin:6px 0;">
                ' . $valorFormatado . '
            </div>';

    if (!empty($descricao)) {
        $corpo .= '
            <div style="color:#666; font-size:13px; margin-top:10px;">
                <strong>Inclui:</strong> ' . htmlspecialchars($descricao) . '
            </div>';
    }

    $corpo .= '
        </div>

        <p style="color:#555; line-height:1.6;">
            Para confirmar a encomenda e iniciarmos a produção, pague através do link
            seguro abaixo. Aceitamos <strong>cartão</strong> e <strong>MB Way</strong>.
        </p>

        <p style="text-align:center; margin:35px 0;">
            <a href="' . htmlspecialchars($linkPagamento) . '"
               style="background:#d66d7f; color:#fff; padding:16px 40px; border-radius:999px;
                      text-decoration:none; font-weight:bold; font-size:16px; display:inline-block;">
                Pagar ' . $valorFormatado . '
            </a>
        </p>

        <p style="color:#888; font-size:13px; line-height:1.6;">
            Se preferir pagar de outra forma ou tiver dúvidas sobre este orçamento,
            responda a este email ou contacte-nos diretamente. Estamos cá para ajudar!
        </p>

        <hr style="border:none; border-top:1px solid #eee; margin:30px 0;">

        <p style="color:#999; font-size:12px; text-align:center;">
            SylviArtes &middot; Costura Criativa<br>
            Este email foi gerado automaticamente para o pedido #' . $pedidoId . '
        </p>
    </div>';

    // Reply-To = Gmail da Sylvia, para que o cliente possa responder directamente
    $replyTo = ADMIN_EMAIL ?: '';

    return enviar_email($email, "Orçamento da sua encomenda - SylviArtes", $corpo, $replyTo);
}


/**
 * Envia à Sylvia (ADMIN_EMAIL) um aviso quando um novo pedido chega.
 * Inclui dados do cliente e link directo para o admin.
 *
 * @param string $adminEmail  Email da administradora
 * @param int    $pedidoId    ID do pedido recém-criado
 * @param string $nomeCliente Nome completo do cliente
 * @param string $emailCliente Email do cliente (para Reply-To)
 * @param string $telefone    Telefone do cliente
 * @param string $descricao   Descrição do pedido
 */
function enviar_email_nova_encomenda(
    string $adminEmail,
    int $pedidoId,
    string $nomeCliente,
    string $emailCliente,
    string $telefone,
    string $descricao
): bool {
    // Lê a URL base do site para o link do admin (funciona em localhost e em produção)
    $baseUrl = getenv('SITE_BASE_URL') ?: 'http://localhost:8080';
    $linkAdmin = $baseUrl . '/public/admin/encomendas/view.php?id=' . $pedidoId;

    $corpo = '
    <div style="font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:30px; background:#fff;">
        <div style="text-align:center; margin-bottom:24px;">
            <h1 style="color:#d66d7f; font-family:Georgia,serif; margin:0;">SylviArtes</h1>
            <p style="color:#888; font-size:13px; margin:4px 0 0;">Painel de Gestão</p>
        </div>

        <!-- Título do aviso -->
        <div style="background:#fff8fa; border-left:4px solid #d66d7f; padding:16px 20px;
                    border-radius:6px; margin-bottom:24px;">
            <h2 style="margin:0; color:#d66d7f; font-size:20px;">
                🔔 Novo pedido #' . $pedidoId . '
            </h2>
            <p style="margin:6px 0 0; color:#555; font-size:14px;">
                Um novo pedido de orçamento foi submetido.
            </p>
        </div>

        <!-- Dados do cliente -->
        <table style="width:100%; border-collapse:collapse; font-size:14px; color:#444;">
            <tr>
                <td style="padding:10px 0; border-bottom:1px solid #f0e3e7; width:35%;
                           color:#888; font-weight:600;">Cliente</td>
                <td style="padding:10px 0; border-bottom:1px solid #f0e3e7;">
                    ' . htmlspecialchars($nomeCliente) . '
                </td>
            </tr>
            <tr>
                <td style="padding:10px 0; border-bottom:1px solid #f0e3e7;
                           color:#888; font-weight:600;">Email</td>
                <td style="padding:10px 0; border-bottom:1px solid #f0e3e7;">
                    <a href="mailto:' . htmlspecialchars($emailCliente) . '"
                       style="color:#d66d7f;">' . htmlspecialchars($emailCliente) . '</a>
                </td>
            </tr>
            <tr>
                <td style="padding:10px 0; border-bottom:1px solid #f0e3e7;
                           color:#888; font-weight:600;">Telefone</td>
                <td style="padding:10px 0; border-bottom:1px solid #f0e3e7;">
                    <a href="tel:' . htmlspecialchars($telefone) . '"
                       style="color:#d66d7f;">' . htmlspecialchars($telefone) . '</a>
                </td>
            </tr>
        </table>

        <!-- Descrição do pedido -->
        <div style="margin:24px 0;">
            <div style="color:#888; font-size:12px; font-weight:600; text-transform:uppercase;
                        letter-spacing:1px; margin-bottom:8px;">O que pediu</div>
            <div style="background:#f8f9fa; padding:16px; border-radius:8px;
                        font-size:14px; color:#444; line-height:1.6; font-style:italic;">
                "' . htmlspecialchars(mb_strimwidth($descricao, 0, 400, '…')) . '"
            </div>
        </div>

        <!-- Botão para o admin -->
        <p style="text-align:center; margin:30px 0;">
            <a href="' . htmlspecialchars($linkAdmin) . '"
               style="background:#d66d7f; color:#fff; padding:14px 36px; border-radius:999px;
                      text-decoration:none; font-weight:bold; font-size:15px; display:inline-block;">
                Ver pedido no painel →
            </a>
        </p>

        <p style="color:#999; font-size:12px; text-align:center;">
            SylviArtes &middot; Este aviso foi gerado automaticamente
        </p>
    </div>';

    // Reply-To = email do cliente, para poder responder directamente a ele
    return enviar_email(
        $adminEmail,
        "🔔 Novo pedido #$pedidoId - " . $nomeCliente,
        $corpo,
        $emailCliente
    );
}


/**
 * Notifica a cliente por email quando o estado da sua encomenda muda.
 * Chamado pelo admin ao mudar o estado (admin/encomendas) e na validação de pagamento.
 *
 * Vai buscar o nome e email da cliente a partir do próprio pedido, por isso basta
 * passar o id do pedido e o novo estado.
 *
 * Só envia para estados relevantes para a cliente (definidos em $mapa). Estados
 * internos como 'em_analise' não geram email (o aviso de pedido novo já cobre isso).
 *
 * @param PDO    $conn       Ligação à base de dados
 * @param int    $pedidoId   ID do pedido
 * @param string $novoEstado Novo estado do pedido
 * @return bool  true se enviou/guardou; false se não havia email a enviar
 */
function enviar_email_estado_pedido(PDO $conn, int $pedidoId, string $novoEstado): bool
{
    // Texto amigável por estado: [título, mensagem]. Estados fora deste mapa não enviam.
    $mapa = [
        'aguarda_pagamento' => ['O seu orçamento está pronto 💌', 'Já preparámos o orçamento da sua encomenda. Veja o email com o link de pagamento (ou a sua área de cliente) para confirmar e iniciarmos a produção.'],
        'em_producao'       => ['Mãos à obra! 🧵',                'Boas notícias: já começámos a produzir a sua encomenda, feita à medida e com todo o carinho.'],
        'concluido'         => ['A sua encomenda está pronta! ✨', 'Terminámos a sua encomenda. Entraremos em contacto para combinar a entrega ou o levantamento.'],
        'entregue'          => ['Encomenda entregue 💝',          'A sua encomenda foi entregue. Esperamos que adore o resultado! Se puder, deixe-nos uma avaliação na sua área de cliente.'],
        'cancelado'         => ['Encomenda cancelada',            'A sua encomenda foi cancelada. Se tiver alguma dúvida, basta responder a este email.'],
    ];

    // Estado sem mensagem definida → não há nada a enviar
    if (!isset($mapa[$novoEstado])) {
        return false;
    }

    // Vai buscar a cliente dona do pedido
    $stmt = $conn->prepare("
        SELECT u.nome, u.email
        FROM pedido p
        JOIN utilizador u ON u.id = p.utilizador_id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedidoId]);
    $cli = $stmt->fetch(PDO::FETCH_ASSOC);

    // Sem destinatário válido → não envia
    if (!$cli || empty($cli['email'])) {
        return false;
    }

    [$titulo, $mensagem] = $mapa[$novoEstado];
    $primeiroNome = htmlspecialchars(explode(' ', $cli['nome'])[0] ?? 'Cliente');

    // Link para a cliente ver a encomenda na sua área pessoal.
    // Usa o host do pedido HTTP atual (em produção é sylviartes.pt), por ser sempre
    // o domínio real onde o email está a ser gerado. Só cai para SITE_BASE_URL /
    // localhost se, por algum motivo, não estivermos dentro de um pedido web.
    if (!empty($_SERVER['HTTP_HOST'])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $baseUrl = ($isHttps ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    } else {
        $baseUrl = getenv('SITE_BASE_URL') ?: 'http://localhost:8080';
    }
    $linkConta = rtrim($baseUrl, '/') . '/cliente/encomenda.php?id=' . $pedidoId;

    $corpo = '
    <div style="font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:30px; background:#fff;">
        <div style="text-align:center; margin-bottom:24px;">
            <h1 style="color:#d66d7f; font-family:Georgia,serif; margin:0;">SylviArtes</h1>
            <p style="color:#888; margin:4px 0 0; font-size:13px;">Costura Criativa &middot; Bordados Personalizados</p>
        </div>

        <h2 style="color:#2d3436;">Olá, ' . $primeiroNome . '!</h2>

        <div style="background:#fff8fa; border-left:4px solid #d66d7f; padding:18px 20px; margin:20px 0; border-radius:6px;">
            <h3 style="margin:0 0 6px; color:#d66d7f;">' . $titulo . '</h3>
            <p style="margin:0; color:#555; line-height:1.6;">' . $mensagem . '</p>
        </div>

        <p style="text-align:center; margin:30px 0;">
            <a href="' . htmlspecialchars($linkConta) . '"
               style="background:#d66d7f; color:#fff; padding:14px 36px; border-radius:999px;
                      text-decoration:none; font-weight:bold; font-size:15px; display:inline-block;">
                Ver a minha encomenda
            </a>
        </p>

        <hr style="border:none; border-top:1px solid #eee; margin:30px 0;">
        <p style="color:#999; font-size:12px; text-align:center;">
            SylviArtes &middot; Este email foi gerado automaticamente.
        </p>
    </div>';

    // Reply-To = Gmail da Sylvia, para a cliente poder responder diretamente
    $replyTo = ADMIN_EMAIL ?: '';

    return enviar_email(
        $cli['email'],
        'Atualização da sua encomenda - SylviArtes',
        $corpo,
        $replyTo
    );
}
