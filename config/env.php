<?php
/**
 * Carrega as variáveis do ficheiro .env para o ambiente (getenv()).
 *
 * Lemos o ficheiro linha-a-linha (parser manual) em vez de parse_ini_string,
 * porque o INI rejeita valores com caracteres especiais como ( ) & ! etc.
 * (ex: passwords). Este parser aceita qualquer valor.
 */
if (!defined('SYLVIARTES_ENV_LOADED')) {
    define('SYLVIARTES_ENV_LOADED', true);

    $envFile = __DIR__ . '/.env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            $linha = trim($linha);

            // Ignora linhas vazias e comentários (# ou ;)
            if ($linha === '' || $linha[0] === '#' || $linha[0] === ';') {
                continue;
            }
            // Tem de ter o formato CHAVE=valor
            if (strpos($linha, '=') === false) {
                continue;
            }

            list($chave, $valor) = explode('=', $linha, 2);
            $chave = trim($chave);
            $valor = trim($valor);

            // Remove aspas envolventes, se existirem ("valor" ou 'valor')
            $len = strlen($valor);
            if ($len >= 2 &&
                (($valor[0] === '"' && $valor[$len - 1] === '"') ||
                 ($valor[0] === "'" && $valor[$len - 1] === "'"))) {
                $valor = substr($valor, 1, -1);
            }

            if ($chave !== '') {
                putenv("$chave=$valor");
            }
        }
    }
}
