<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if (current_admin()) {
    redirect('admin/index.php');
}
if (($_SESSION['role'] ?? '') === 'student' && !empty($_SESSION['uid'])) {
    redirect('student/index.php');
}

page_open('منصة النخبة التعليمية', '', 'bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 min-h-screen transition-colors duration-200');
?>
<div class="min-h-screen flex flex-col">
    <div class="absolute top-4 left-4"><?= theme_button() ?></div>
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="w-full max-w-md space-y-4">
            <div class="flex justify-center mb-2"><?= brand_block('لوحة تقييم ومتابعة الطلاب') ?></div>
            <a href="student/login.php" class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/60 shadow-sm hover:scale-[1.02] transition-all">
                <div>
                    <h3 class="font-extrabold text-gray-900 dark:text-white">بوابة الطلاب</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">عرض ملفك ومجموعتك وتقييمك وترتيبك</p>
                </div>
                <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-2xl flex items-center justify-center text-xl"><i class="fa-solid fa-user-graduate"></i></div>
            </a>
            <a href="admin/login.php" class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/60 shadow-sm hover:scale-[1.02] transition-all">
                <div>
                    <h3 class="font-extrabold text-gray-900 dark:text-white">بوابة المشرفين</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">إدارة الطلاب والمجموعات والتقييمات</p>
                </div>
                <div class="w-12 h-12 bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-2xl flex items-center justify-center text-xl"><i class="fa-solid fa-user-shield"></i></div>
            </a>
        </div>
    </main>
    <?= footer_block() ?>
</div>
<?php page_close('');
