<?php
declare(strict_types=1);
/**
 * One-time installer.
 *  1. Creates the tables from database/database.sql (safe to re-run: IF NOT EXISTS).
 *  2. Creates the FIRST admin account - and ONLY if no admin exists yet.
 * After setup it locks itself. Delete this file from the server afterwards.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$lockFile = APP_ROOT . '/config/installed.lock';

function installer_page(string $title, string $bodyHtml): void
{
    page_open($title, '', 'bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 min-h-screen');
    echo '<main class="min-h-screen flex items-center justify-center p-4"><div class="w-full max-w-md">'
       . '<div class="flex justify-center mb-6">' . brand_block('الإعداد الأولي') . '</div>'
       . '<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4">'
       . $bodyHtml . '</div></div></main>';
    page_close('');
    exit;
}

function run_schema(PDO $pdo): void
{
    $sql = file_get_contents(APP_ROOT . '/database/database.sql');
    if ($sql === false) {
        throw new RuntimeException('database/database.sql not found');
    }
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (explode(';', (string)$sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $stmt)) {
            continue; // the database is already selected through config.php
        }
        $pdo->exec($stmt);
    }
}

// Already installed? Refuse to run again (and never touch the database in that case).
$adminCount = null;
try {
    $pdo = db();
    try {
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    } catch (PDOException $ex) {
        $adminCount = null; // tables do not exist yet
    }
    if ($adminCount === null && !is_file($lockFile)) {
        run_schema($pdo);   // creates tables + sample data, only on a brand-new database
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }
} catch (Throwable $ex) {
    error_log('[installer] ' . $ex->getMessage());
    installer_page('خطأ في الإعداد',
        '<h2 class="font-bold text-rose-600">تعذر الاتصال بقاعدة البيانات</h2>'
        . '<p class="text-sm text-gray-600 dark:text-gray-300">تأكد من إنشاء قاعدة البيانات وتعديل بيانات الاتصال في <code dir="ltr">config/config.php</code> ثم أعد تحميل الصفحة.</p>'
        . (cfg('debug') ? '<pre dir="ltr" class="text-xs text-left overflow-auto">' . e($ex->getMessage()) . '</pre>' : ''));
}

if ($adminCount > 0 || is_file($lockFile)) {
    if ($adminCount > 0 && !is_file($lockFile)) {
        @file_put_contents($lockFile, 'installed ' . date('c'));
    }
    installer_page('تم التثبيت',
        '<h2 class="font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-circle-check text-emerald-500"></i> التثبيت مكتمل مسبقاً</h2>'
        . '<p class="text-sm text-gray-600 dark:text-gray-300">لأسباب أمنية لا يمكن إنشاء مشرف جديد من هذه الصفحة. يرجى <b>حذف ملف install.php</b> من الخادم.</p>'
        . '<a href="admin/login.php" class="block text-center px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white">الذهاب إلى دخول المشرف</a>');
}

$errors = [];
$username = '';
$fullName = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $fullName = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
    $pass     = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $pass2    = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';

    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الصفحة، أعد المحاولة.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $errors[] = 'اسم المستخدم: 3-50 حرفاً (حروف إنجليزية وأرقام و . _ - فقط).';
    }
    if (str_chars($fullName) > 100) {
        $errors[] = 'الاسم الكامل طويل جداً.';
    }
    if (strlen($pass) < 8 || strlen($pass) > 72) {
        $errors[] = 'كلمة المرور يجب أن تتراوح بين 8 و 72 حرفاً.';
    }
    if (!hash_equals($pass, $pass2)) {
        $errors[] = 'كلمتا المرور غير متطابقتين.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            // Re-check inside the transaction so two simultaneous requests cannot both create an admin.
            $cnt = (int)$pdo->query('SELECT COUNT(*) FROM admins FOR UPDATE')->fetchColumn();
            if ($cnt > 0) {
                $pdo->rollBack();
                redirect('install.php');
            }
            $pdo->prepare('INSERT INTO admins (username, full_name, password_hash) VALUES (:u, :f, :p)')
                ->execute([':u' => $username, ':f' => $fullName, ':p' => password_hash($pass, PASSWORD_DEFAULT)]);
            $pdo->commit();
            @file_put_contents($lockFile, 'installed ' . date('c'));
            installer_page('تم التثبيت',
                '<h2 class="font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-circle-check text-emerald-500"></i> تم إنشاء حساب المشرف بنجاح</h2>'
                . '<p class="text-sm text-gray-600 dark:text-gray-300">احذف الآن ملف <b>install.php</b> من الخادم، ثم سجّل الدخول.</p>'
                . '<a href="admin/login.php" class="block text-center px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white">دخول المشرف</a>');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[installer] ' . $ex->getMessage());
            $errors[] = 'تعذر إنشاء الحساب، راجع سجل أخطاء الخادم.';
        }
    }
}

$input = 'w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500';
ob_start();
?>
<h2 class="font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="fa-solid fa-user-shield text-blue-500"></i> إنشاء حساب المشرف الأول</h2>
<p class="text-xs text-gray-500 dark:text-gray-400">تم إنشاء جداول قاعدة البيانات. أنشئ الآن حساب المشرف. تعمل هذه الصفحة مرة واحدة فقط.</p>
<?php foreach ($errors as $err): ?>
    <div class="px-3.5 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-300 text-xs font-semibold border border-rose-200 dark:border-rose-800/40"><?= e($err) ?></div>
<?php endforeach; ?>
<form method="post" autocomplete="off" class="space-y-4">
    <?= csrf_field() ?>
    <div><label class="block text-xs font-semibold mb-1.5">اسم المستخدم</label>
        <input type="text" name="username" required maxlength="50" dir="ltr" value="<?= e($username) ?>" class="<?= $input ?>"></div>
    <div><label class="block text-xs font-semibold mb-1.5">الاسم الكامل (اختياري)</label>
        <input type="text" name="full_name" maxlength="100" value="<?= e($fullName) ?>" class="<?= $input ?>"></div>
    <div><label class="block text-xs font-semibold mb-1.5">كلمة المرور (8 أحرف على الأقل)</label>
        <input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" class="<?= $input ?>"></div>
    <div><label class="block text-xs font-semibold mb-1.5">تأكيد كلمة المرور</label>
        <input type="password" name="password2" required minlength="8" maxlength="72" autocomplete="new-password" class="<?= $input ?>"></div>
    <button type="submit" class="w-full px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-600/20">إنشاء الحساب</button>
</form>
<?php
installer_page('إعداد المشرف', (string)ob_get_clean());
