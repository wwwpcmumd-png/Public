<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';

if (($_SESSION['role'] ?? '') === 'student' && !empty($_SESSION['uid']) && student_profile((int)$_SESSION['uid'])) {
    redirect('index.php');
}

$error = '';
$name = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = is_string($_POST['student_name'] ?? null) ? trim($_POST['student_name']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'انتهت صلاحية الصفحة، يرجى المحاولة مرة أخرى.';
    } elseif ($name === '' || $password === '') {
        $error = 'يرجى إدخال الاسم وكلمة المرور.';
    } else {
        try {
            [$ok, $msg] = attempt_student_login($name, $password);
        } catch (AppException $ex) {
            [$ok, $msg] = [false, 'الاسم أو كلمة المرور غير صحيحة.'];
        }
        if ($ok) {
            redirect('index.php');
        }
        $error = $msg;
    }
}

render_login_page([
    'title'       => 'دخول الطالب - لوحة تقييم ومتابعة الطلاب',
    'sub'         => 'بوابة الطلاب',
    'heading'     => 'دخول الطالب',
    'icon'        => 'fa-user-graduate',
    'label'       => 'الاسم المسجل',
    'field'       => 'student_name',
    'placeholder' => 'اكتب اسمك كما هو مسجل',
    'value'       => $name,
    'error'       => $error,
    'other_text'  => 'هل أنت مشرف؟ الدخول من بوابة المشرفين',
    'other_href'  => '../admin/login.php',
    'base'        => '../',
]);
