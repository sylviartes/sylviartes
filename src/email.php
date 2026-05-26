<?php
/**
 * =============================================================================
 *  ENVIO DE EMAIL — Helper SylviArtes
 * =============================================================================
 *
 *  Sistema com 3 níveis de fallback automático:
 *
 *    1. RESEND API — RECOMENDADO. Setup em 2 min: criar conta em resend.com
 *       (login com GitHub), gerar API key, colar em RESEND_API_KEY abaixo.
 *       Funciona logo (até com o sender de teste onboarding@resend.dev).
 *
 *    2. PHPMailer + SMTP — alternativa para Gmail/Brevo/etc.
 *
 *    3. Outbox local — fallback final: emails ficam como ficheiros .eml
 *       em docs/outbox/ para teste local sem internet.
 *
 *  O sistema escolhe automaticamente o primeiro nível configurado.
 * =============================================================================
 */

// =============================================================================
// CONFIGURAÇÕES — valores lidos de config/.env (nunca hardcoded aqui)
// =============================================================================

// --- Load .env if present (idempotent — runs once per process) ---
(static function () {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $envFile = __DIR__ . '/../config/.env';
    if (file_exists($envFile)) {
        foreach (parse_ini_file($envFile) as $k => $v) {
            putenv("$k=$v");
        }
    }
})();

// === RESEND (Recomendado — mais simples) ===
// Chave lida de config/.env — nunca colocar a chave real diretamente aqui.
// Para configurar: copia config/.env.example para config/.env e preenche RESEND_API_KEY.
if (!defined('RESEND_API_KEY')) define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');
// Sender padrão de teste — funciona logo, sem precisar de verificar domínio.
// Quando comprares domínio próprio, adiciona-o em https://resend.com/domains
// e altera RESEND_FROM em .env para algo como 'SylviArtes <pedidos@sylviartes.pt>'.
if (!defined('RESEND_FROM'))    define('RESEND_FROM',    getenv('RESEND_FROM')    ?: 'SylviArtes <onboarding@resend.dev>');

// === SMTP (alternativa, se preferires Gmail/Brevo/etc.) ===
if (!defined('SMTP_HOST'))      define('SMTP_HOST',      getenv('SMTP_HOST')      ?: '');
if (!defined('SMTP_PORT'))      define('SMTP_PORT',      getenv('SMTP_PORT')      ?: 587);
if (!defined('SMTP_USER'))      define('SMTP_USER',      getenv('SMTP_USER')      ?: '');
if (!defined('SMTP_PASS'))      define('SMTP_PASS',      getenv('SMTP_PASS')      ?: '');
if (!defined('SMTP_FROM'))      define('SMTP_FROM',      getenv('SMTP_FROM')      ?: 'noreply@sylviartes.pt');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'SylviArtes');

/**
 * Envia um email. Usa Resend → SMTP → Outbox local automaticamente.
 *
 * @return bool true se enviou/guardou com sucesso
 */
function enviar_email(string $para, string $assunto, string $htmlCorpo): bool
{
    // ===========================================================================
    // Tentativa 1: RESEND API (recomendado)
    // ===========================================================================
    if (RESEND_API_KEY !== '' && function_exists('curl_init')) {
        $payload = json_encode([
            'from'    => RESEND_FROM,
            'to'      => [$para],
            'subject' => $assunto,
            'html'    => $htmlCorpo,
        ]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . RESEND_API_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro     = curl_error($ch);
        // curl_close() foi descontinuado no PHP >= 8.5 (não tem efeito desde PHP 8.0).
        // O handle é libertado automaticamente ao fim da execução/escopo.


        if ($status >= 200 && $status < 300) {
            return true;  // Email enviado pela Resend
        }
        // Falhou → loga e cai para tentativa seguinte
        error_log("Resend falhou (HTTP $status): " . ($erro ?: $response));
    }

    // ===========================================================================
    // Tentativa 2: PHPMailer + SMTP (se configurado)
    // ===========================================================================
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && SMTP_HOST !== '') {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($para);
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body    = $htmlCorpo;
            $mail->AltBody = strip_tags($htmlCorpo);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Falha no SMTP → cai para fallback
            error_log("Falha SMTP: " . $e->getMessage());
        }
    }

    // === Fallback: guardar como ficheiro .eml na outbox ===
    $outboxDir = __DIR__ . '/../docs/outbox';
    if (!is_dir($outboxDir)) {
        @mkdir($outboxDir, 0755, true);
    }

    $nomeFich = $outboxDir . '/' . date('Y-m-d_His') . '_' . preg_replace('/[^a-z0-9]+/i', '_', $para) . '.eml';
    $conteudo = "Para: $para\n";
    $conteudo .= "De: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\n";
    $conteudo .= "Assunto: $assunto\n";
    $conteudo .= "Data: " . date('r') . "\n";
    $conteudo .= "Content-Type: text/html; charset=UTF-8\n\n";
    $conteudo .= $htmlCorpo;

    return (bool) file_put_contents($nomeFich, $conteudo);
}

/**
 * Envia o email à cliente com o orçamento finalizado pela admin
 * e o link Stripe para pagamento.
 *
 * @param string $email          Email da cliente
 * @param string $nome           Nome (para personalizar saudação)
 * @param int    $pedidoId       ID do pedido
 * @param float  $valor          Valor final do orçamento (€)
 * @param string $linkPagamento  URL do Stripe Payment Link
 * @param string $descricao      Descrição do que foi pedido (opcional)
 *
 * @return bool true se enviou/guardou com sucesso
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
    $primeiroNome = htmlspecialchars(explode(' ', $nome)[0] ?? 'Cliente');

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

    return enviar_email($email, "Orçamento da sua encomenda — SylviArtes", $corpo);
}
