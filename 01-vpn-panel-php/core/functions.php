<?php
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function text_substr(string $value, int $start, int $length): string { return function_exists('mb_substr') ? mb_substr($value,$start,$length,'UTF-8') : substr($value,$start,$length); }

function setting(PDO $pdo, string $key, ?string $default = null): ?string {
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['value'] : $default;
}

function set_setting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute([$key, $value]);
}

/**
 * Return the installation sub-path from configured base URL.
 * Examples:
 *   https://example.com       => ''
 *   https://example.com/test  => '/test'
 */
function app_base_path(): string {
    global $config;
    $base = (string)($config['app']['base_url'] ?? '');
    $path = (string)(parse_url($base, PHP_URL_PATH) ?? '');
    $path = '/' . trim($path, '/');
    return $path === '/' ? '' : $path;
}

/** Build a path that always stays inside the installation directory. */
function app_path(string $path = ''): string {
    $base = app_base_path();
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '/' : $path);
}

function redirect(string $url): never {
    // Absolute external URLs are kept as-is. Root-relative app paths are scoped
    // to the installation folder so /test installs do not jump to site root.
    if (!preg_match('~^https?://~i', $url)) {
        $url = app_path($url);
    }
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void { $_SESSION['_flash'][] = ['type'=>$type,'message'=>$message]; }
function flashes(): array { $f=$_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }

function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void {
    if (!is_admin()) redirect('/admin/login.php');
}

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function json_response(array $payload, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function random_token(int $bytes=32): string { return bin2hex(random_bytes($bytes)); }

function app_version(): string {
    $f = dirname(__DIR__) . '/VERSION';
    return is_file($f) ? trim((string)file_get_contents($f)) : 'unknown';
}

function validate_http_url(string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    return isset($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http','https'], true);
}
