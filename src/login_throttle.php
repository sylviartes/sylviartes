<?php
/**
 * =============================================================================
 *  THROTTLE DE LOGIN - Proteção simples contra tentativas repetidas
 * =============================================================================
 *
 *  Limita o número de tentativas de login falhadas num curto espaço de tempo,
 *  para travar ataques de "força bruta" (tentar muitas passwords seguidas).
 *
 *  Como funciona:
 *    - Conta as falhas guardadas na sessão (por chave: 'admin' ou 'cliente').
 *    - Ao fim de N falhas, bloqueia novas tentativas durante alguns minutos.
 *    - Um login com sucesso limpa o contador.
 *
 *  Nota: é uma proteção ao nível da aplicação (boa para travar tentativas
 *  casuais). Requer que a sessão já esteja iniciada (config/session.php).
 * =============================================================================
 */

/**
 * Verifica se uma "chave" de login está neste momento bloqueada.
 *
 * @param string $chave  Identificador do formulário ('admin' ou 'cliente')
 * @return array ['bloqueado' => bool, 'restante' => int (segundos)]
 */
function login_throttle_estado(string $chave): array
{
    $dados = $_SESSION['login_throttle'][$chave] ?? null;
    if ($dados && ($dados['bloqueado_ate'] ?? 0) > time()) {
        return ['bloqueado' => true, 'restante' => $dados['bloqueado_ate'] - time()];
    }
    return ['bloqueado' => false, 'restante' => 0];
}

/**
 * Regista uma tentativa falhada. Ao atingir $maxTentativas, ativa o bloqueio.
 *
 * @param string $chave         'admin' ou 'cliente'
 * @param int    $maxTentativas Falhas permitidas antes de bloquear (def. 5)
 * @param int    $bloqueioSeg   Duração do bloqueio em segundos (def. 300 = 5 min)
 */
function login_registar_falha(string $chave, int $maxTentativas = 5, int $bloqueioSeg = 300): void
{
    $dados = $_SESSION['login_throttle'][$chave] ?? ['tentativas' => 0, 'bloqueado_ate' => 0];
    $dados['tentativas']++;

    if ($dados['tentativas'] >= $maxTentativas) {
        $dados['bloqueado_ate'] = time() + $bloqueioSeg;
        $dados['tentativas']    = 0; // reinicia o contador depois de bloquear
    }

    $_SESSION['login_throttle'][$chave] = $dados;
}

/**
 * Limpa o contador (chamado após um login bem-sucedido).
 */
function login_throttle_limpar(string $chave): void
{
    unset($_SESSION['login_throttle'][$chave]);
}
