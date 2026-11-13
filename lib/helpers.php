<?php
declare(strict_types=1);

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function random_token(int $bytes = 16): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function valid_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function normalize_email(string $email): string
{
    return strtolower(trim($email, " \t\n\r\0\x0B<>\"'"));
}

/* ---------- Session / auth ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('bcmail');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $u = null;
    if ($u === null) {
        $u = q_one('SELECT id, email, name FROM users WHERE id = ?', [$_SESSION['uid']]);
    }
    return $u;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login');
    }
    return $u;
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(24);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(): void
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Jeton de sécurité invalide. Rechargez la page et recommencez.');
    }
}

/* ---------- Navigation ---------- */

function url(string $page, array $params = []): string
{
    $params = array_merge(['p' => $page], $params);
    return 'index.php?' . http_build_query($params);
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

function flash(string $msg, string $type = 'ok'): void
{
    start_session();
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flashes(): array
{
    start_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function render(string $view, array $vars = [], bool $layout = true): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    if ($layout) {
        require APP_ROOT . '/views/layout.php';
    } else {
        echo $content;
    }
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function get(string $key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

/* ---------- Chiffrement (mot de passe SMTP) ---------- */

function secret_key(): string
{
    return hash('sha256', 'enc|' . config('app_secret'), true);
}

function encrypt_value(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_value(string $enc): string
{
    if ($enc === '') {
        return '';
    }
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

/* ---------- Divers ---------- */

function status_label(string $s): string
{
    return [
        'subscribed' => 'Abonné',
        'unsubscribed' => 'Désinscrit',
        'bounced' => 'En erreur',
        'draft' => 'Brouillon',
        'scheduled' => 'Programmée',
        'sending' => 'En cours d\'envoi',
        'paused' => 'En pause',
        'sent' => 'Envoyée',
        'cancelled' => 'Annulée',
        'pending' => 'En attente',
        'failed' => 'Échec',
        'skipped' => 'Ignoré',
    ][$s] ?? $s;
}

function fmt_date(?string $d): string
{
    if (!$d) {
        return '—';
    }
    $t = strtotime($d);
    return $t ? date('d/m/Y H:i', $t) : h($d);
}

function pct(int $a, int $b): string
{
    return $b > 0 ? round($a * 100 / $b) . ' %' : '—';
}
