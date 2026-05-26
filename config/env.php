<?php
if (!defined('SYLVIARTES_ENV_LOADED')) {
    define('SYLVIARTES_ENV_LOADED', true);
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $parsed = parse_ini_string(file_get_contents($envFile), false, INI_SCANNER_RAW);
        if (is_array($parsed)) {
            foreach ($parsed as $k => $v) {
                putenv("$k=$v");
            }
        }
    }
}
