<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';

if (current_admin()) {
    redirect('index.php');
}

$error = '';
$username = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'انتهت صلاحية الصفحة، يرجى المحاولة مرة أخرى.';
    } elseif ($username === '' || $password === '') {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.';
    } else {
        [$ok, $msg] = attempt_admin_login($username, $password);
        if ($ok) {
            redirect('index.php');
        }
        $error = $msg;
    }
}

render_login_page([
    'title'       => 'دخول المشرف - لوحة تحكم إدارة وتقييم الطلاب',
    'sub'         => 'تسجيل دخول المشرف',
    'heading'     => 'دخول المشرف',
    'icon'        => 'fa-user-shield',
    'label'       => 'اسم المستخدم',
    'field'       => 'username',
    'placeholder' => 'اسم المستخدم',
    'value'       => $username,
    'error'       => $error,
    'other_text'  => 'هل أنت طالب؟ الدخول من بوابة الطلاب',
    'other_href'  => '../student/login.php',
    'base'        => '../',
]);
