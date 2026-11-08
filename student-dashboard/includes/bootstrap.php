<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('PHP 8.0 or newer is required.');
}
foreach (['pdo_mysql', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) {
        http_response_code(500);
        exit('Missing required PHP extension: ' . $ext);
    }
}

define('APP_ROOT', dirname(__DIR__));

require_once __DIR__ . '/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', cfg('debug') ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof AppException && defined('IS_API')) {
        json_response(['ok' => false, 'message' => $e->getMessage()], $e->status);
    }
    error_log('[student-dashboard] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (defined('IS_API')) {
        json_response(['ok' => false, 'message' => cfg('debug') ? $e->getMessage() : 'حدث خطأ في الخادم، يرجى المحاولة لاحقاً.'], 500);
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>خطأ</title>'
       . '<body style="font-family:Tahoma,sans-serif;text-align:center;padding:60px;background:#111827;color:#f3f4f6">'
       . '<h2>تعذر إكمال الطلب</h2><p>تحقق من إعدادات قاعدة البيانات في config/config.php ومن استيراد database.sql.</p>'
       . (cfg('debug') ? '<pre dir="ltr" style="text-align:left">' . e($e->getMessage()) . '</pre>' : '')
       . '</body></html>';
    exit;
});

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/auth.php';

start_secure_session();
send_security_headers();
