<?php
declare(strict_types=1);

/** Thrown for validation / business-rule errors that are safe to show to the user. */
class AppException extends RuntimeException
{
    public int $status;
    public function __construct(string $message, int $status = 422)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function cfg(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/config.php';
    }
    return $config[$key] ?? $default;
}

/** Escape for HTML output. */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return ((int)($_SERVER['SERVER_PORT'] ?? 0)) === 443;
}

function client_ip(): string
{
    // REMOTE_ADDR only: forwarded headers can be spoofed by the client.
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return substr($ip, 0, 45);
}

function redirect(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    if (strlen($raw) > 65536) {
        throw new AppException('حجم الطلب كبير جداً.', 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new AppException('صيغة الطلب غير صحيحة.', 400);
    }
    return $data;
}

/** Number of characters (not bytes) in a UTF-8 string. */
function str_chars(string $s): int
{
    return (int)preg_match_all('/./us', $s);
}

/** Trim and collapse whitespace; rejects control characters and invalid UTF-8. */
function clean_text($value): string
{
    if ($value !== null && !is_scalar($value)) {
        throw new AppException('قيمة غير صالحة.');
    }
    $s = trim((string)$value);
    if ($s === '') {
        return '';
    }
    if (!preg_match('//u', $s) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $s)) {
        throw new AppException('يحتوي النص على رموز غير مسموحة.');
    }
    return (string)preg_replace('/\s+/u', ' ', $s);
}

/** Single source of truth for the avatar list (code points avoid file-encoding issues). */
function avatar_options(): array
{
    return [
        "\u{1F468}\u{200D}\u{1F393}", // 👨‍🎓
        "\u{1F469}\u{200D}\u{1F393}", // 👩‍🎓
        "\u{1F468}\u{200D}\u{1F4BB}", // 👨‍💻
        "\u{1F469}\u{200D}\u{1F52C}", // 👩‍🔬
        "\u{1F466}",                  // 👦
        "\u{1F467}",                  // 👧
        "\u{1F9D1}\u{200D}\u{1F3EB}", // 🧑‍🏫
        "\u{1F680}",                  // 🚀
        "\u{2B50}",                   // ⭐
        "\u{1F3C6}",                  // 🏆
    ];
}

function default_avatar(): string
{
    return avatar_options()[0];
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name((string)cfg('session_name', 'SDASH_SESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle timeout
    $timeout = (int)cfg('session_timeout', 7200);
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $timeout) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

function send_security_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}
