<?php
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PHP 8.1+ required');
}

$root = dirname(__DIR__);
$configFile = $root . '/config.php';
if (!is_file($configFile)) {
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/install')) {
        return;
    }
    http_response_code(503);
    exit('Application is not installed.');
}

$config = require $configFile;
if (!is_array($config)) {
    http_response_code(500);
    exit('Invalid configuration.');
}

date_default_timezone_set('UTC');

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name($config['app']['session_name'] ?? 'vpn_admin_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db']['host'], $config['db']['name'], $config['db']['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    if (!empty($config['app']['debug'])) {
        throw $e;
    }
    http_response_code(500);
    exit('Database connection failed.');
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/subscription_service.php';

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}
