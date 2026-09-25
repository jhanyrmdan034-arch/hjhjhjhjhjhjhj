<?php
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    $given = $_POST['csrf'] ?? '';
    if (!is_string($given) || !hash_equals(csrf_token(), $given)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function login_rate_limited(PDO $pdo, string $ip): bool {
    $hash = hash('sha256', $ip);
    $stmt = $pdo->prepare('SELECT attempts, lock_until FROM login_attempts WHERE ip_hash=?');
    $stmt->execute([$hash]);
    $row=$stmt->fetch();
    return $row && !empty($row['lock_until']) && strtotime($row['lock_until']) > time();
}
function record_login_failure(PDO $pdo, string $ip): void {
    $hash=hash('sha256',$ip);
    $pdo->prepare('INSERT INTO login_attempts (ip_hash,attempts,last_attempt,lock_until) VALUES (?,1,NOW(),NULL) ON DUPLICATE KEY UPDATE attempts=IF(lock_until IS NOT NULL AND lock_until < NOW(),1,attempts+1), last_attempt=NOW(), lock_until=IF(IF(lock_until IS NOT NULL AND lock_until < NOW(),1,attempts+1)>=5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), lock_until)')->execute([$hash]);
}
function clear_login_failures(PDO $pdo, string $ip): void {
    $pdo->prepare('DELETE FROM login_attempts WHERE ip_hash=?')->execute([hash('sha256',$ip)]);
}

function require_app_key(PDO $pdo): void {
    $key = $_SERVER['HTTP_X_APP_KEY'] ?? '';
    $hash = setting($pdo, 'app_api_key_hash', '');
    if (!$key || !$hash || !password_verify($key, $hash)) {
        json_response(['ok'=>false,'error'=>'unauthorized'],401);
    }
}
