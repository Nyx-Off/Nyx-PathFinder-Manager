<?php

declare(strict_types=1);
define('ROOT', dirname(__DIR__));
spl_autoload_register(function ($class) {
    $path = ROOT . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
function env(string $key, string $default = ''): string
{
    static $values;
    $values ??= parse_ini_file(ROOT . '/.env', false, INI_SCANNER_RAW) ?: [];
    return (string)($values[$key] ?? $default);
}
function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php.log');
if (PHP_SAPI !== 'cli') {
    $requestLock = fopen(ROOT . '/storage/operations.lock', 'c');
    if (!$requestLock || !flock($requestLock, LOCK_SH)) {
        http_response_code(503);
        exit('Service momentanément indisponible.');
    }
    if (is_file(ROOT . '/storage/maintenance')) {
        http_response_code(503);
        header('Retry-After: 30');
        exit('Maintenance en cours. Réessayez dans quelques instants.');
    }
    if (!is_dir(ROOT . '/storage/sessions')) {
        mkdir(ROOT . '/storage/sessions', 0700, true);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_save_path(ROOT . '/storage/sessions');
    session_name('nyx_pathfinder');
    session_set_cookie_params(['httponly' => true,'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite' => 'Strict','path' => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}
