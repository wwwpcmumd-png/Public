<?php
declare(strict_types=1);

/* ---------- login logging & brute-force throttling ---------- */

function log_login(string $role, ?int $userId, string $username, bool $success): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO login_logs (role, user_id, username, ip_address, user_agent, success)
             VALUES (:role, :uid, :uname, :ip, :ua, :ok)'
        );
        $st->execute([
            ':role'  => $role,
            ':uid'   => $userId,
            ':uname' => mb_substr($username, 0, 120),
            ':ip'    => client_ip(),
            ':ua'    => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ':ok'    => $success ? 1 : 0,
        ]);
        if ($success) { // housekeeping: keep 90 days of history
            db()->exec('DELETE FROM login_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
        }
    } catch (Throwable $e) {
        error_log('login log failed: ' . $e->getMessage());
    }
}

function login_is_blocked(string $role): bool
{
    $max     = (int)cfg('max_failed_logins', 8);
    $minutes = (int)cfg('lockout_minutes', 15);
    $st = db()->prepare(
        'SELECT COUNT(*) FROM login_logs
         WHERE role = :role AND ip_address = :ip AND success = 0
           AND created_at > DATE_SUB(NOW(), INTERVAL ' . $minutes . ' MINUTE)'
    );
    $st->execute([':role' => $role, ':ip' => client_ip()]);
    return ((int)$st->fetchColumn()) >= $max;
}

/* ---------- session helpers ---------- */

function session_begin(string $role, int $userId): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'role'          => $role,
        'uid'           => $userId,
        'last_activity' => time(),
    ];
    csrf_rotate();
}

function do_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/* ---------- admin ---------- */

/**
 * @return array{0:bool,1:string}
 */
function attempt_admin_login(string $username, string $password): array
{
    $username = trim($username);
    if (login_is_blocked('admin')) {
        return [false, 'تم تجاوز عدد محاولات الدخول المسموحة. حاول مرة أخرى بعد قليل.'];
    }
    $st = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1');
    $st->execute([':u' => $username]);
    $row = $st->fetch();

    // Constant-ish time: always run a password_verify.
    $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringforsaltOuY8m2o0u1V3o2P8bq3g0m7sJ6u1e';
    $ok   = password_verify($password, $hash) && $row;

    if (!$ok) {
        log_login('admin', $row ? (int)$row['id'] : null, $username, false);
        return [false, 'اسم المستخدم أو كلمة المرور غير صحيحة.'];
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $up = db()->prepare('UPDATE admins SET password_hash = :h WHERE id = :id');
        $up->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $row['id']]);
    }
    db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id')->execute([':id' => $row['id']]);
    log_login('admin', (int)$row['id'], $username, true);
    session_begin('admin', (int)$row['id']);
    return [true, ''];
}

/** Returns the logged-in admin row or null. Always re-checked against the database. */
function current_admin(): ?array
{
    static $cache = [];
    if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['uid'])) {
        return null;
    }
    $id = (int)$_SESSION['uid'];
    if (!array_key_exists($id, $cache)) {
        $st = db()->prepare('SELECT id, username, full_name FROM admins WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $cache[$id] = $st->fetch() ?: null;
    }
    return $cache[$id];
}

function require_admin_page(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('login.php');
    }
    return $admin;
}

function api_require_admin(): array
{
    if (empty($_SESSION['role']) || empty($_SESSION['uid'])) {
        json_response(['ok' => false, 'message' => 'انتهت الجلسة، يرجى تسجيل الدخول.'], 401);
    }
    if ($_SESSION['role'] !== 'admin') {
        json_response(['ok' => false, 'message' => 'غير مصرح لك بتنفيذ هذه العملية.'], 403);
    }
    $admin = current_admin();
    if (!$admin) {
        json_response(['ok' => false, 'message' => 'انتهت الجلسة، يرجى تسجيل الدخول.'], 401);
    }
    return $admin;
}

/* ---------- student ---------- */

/**
 * @return array{0:bool,1:string}
 */
function attempt_student_login(string $name, string $password): array
{
    $name = clean_text($name);
    if (login_is_blocked('student')) {
        return [false, 'تم تجاوز عدد محاولات الدخول المسموحة. حاول مرة أخرى بعد قليل.'];
    }
    $st = db()->prepare('SELECT id, name, password_hash FROM students WHERE name = :n LIMIT 1');
    $st->execute([':n' => $name]);
    $row = $st->fetch();

    $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringforsaltOuY8m2o0u1V3o2P8bq3g0m7sJ6u1e';
    $ok   = password_verify($password, $hash) && $row;

    if (!$ok) {
        log_login('student', $row ? (int)$row['id'] : null, $name, false);
        return [false, 'الاسم أو كلمة المرور غير صحيحة.'];
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $up = db()->prepare('UPDATE students SET password_hash = :h WHERE id = :id');
        $up->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $row['id']]);
    }
    log_login('student', (int)$row['id'], $name, true);
    session_begin('student', (int)$row['id']);
    return [true, ''];
}

function require_student_page(): array
{
    if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['uid'])) {
        redirect('login.php');
    }
    $profile = student_profile((int)$_SESSION['uid']);
    if (!$profile) {
        do_logout();
        redirect('login.php');
    }
    return $profile;
}
