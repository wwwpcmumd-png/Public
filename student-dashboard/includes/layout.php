<?php
declare(strict_types=1);

/**
 * Shared HTML chrome. Markup, Tailwind config, fonts and icons are identical
 * to the original dashboard so the design is preserved 1:1.
 *
 * @param string $base  relative path from the current page to the project root ('' or '../')
 */
function page_open(string $title, string $base, string $bodyClass = '', string $bodyAttrs = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    },
                    fontFamily: {
                        sans: ['Tajawal', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Arabic Font: Tajawal -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
        }
        /* Custom scrollbar for better aesthetics */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }
        .dark ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.5);
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(107, 114, 128, 0.8);
        }
    </style>
</head>
<body class="<?= e($bodyClass) ?>" <?= $bodyAttrs ?>>
<?php
}

function page_close(string $base, array $scripts = []): void
{
    echo '<script src="' . e($base) . 'assets/js/common.js"></script>' . "\n";
    foreach ($scripts as $s) {
        echo '<script src="' . e($base) . 'assets/js/' . e($s) . '"></script>' . "\n";
    }
    echo "</body>\n</html>";
}

function theme_button(): string
{
    $icon = 'fa-solid fa-moon text-lg';
    return '<button id="themeToggleBtn" type="button" onclick="toggleDarkMode()" class="p-2.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-all" title="تبديل الوضع">'
         . '<i id="themeIcon" class="' . $icon . '"></i></button>';
}

function logout_button(): string
{
    return '<form method="post" action="logout.php" class="inline">' . csrf_field()
         . '<button type="submit" class="p-2.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30 rounded-xl transition-all" title="تسجيل الخروج">'
         . '<i class="fa-solid fa-right-from-bracket text-lg"></i></button></form>';
}

function brand_block(string $subtitle): string
{
    return '<div class="flex items-center space-x-3 space-x-reverse">'
        . '<div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-white shadow-lg shadow-blue-500/30">'
        . '<i class="fa-solid fa-graduation-cap text-xl"></i></div>'
        . '<div><h1 class="font-bold text-lg leading-tight text-gray-900 dark:text-white">منصة النخبة التعليمية</h1>'
        . '<p class="text-xs text-gray-500 dark:text-gray-400">' . e($subtitle) . '</p></div></div>';
}

function footer_block(): string
{
    return '<footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-4 mt-auto">'
        . '<div class="max-w-7xl mx-auto px-4 text-center text-xs text-gray-500 dark:text-gray-400">'
        . 'لوحة تحكم تقييم وإدارة الطلاب &copy; 2026 - جميع الحقوق محفوظة</div></footer>';
}

/**
 * Login screen shared by both portals.
 * $o: title, heading, sub, label, field (input name), placeholder, icon, error,
 *     other_text, other_href, base
 */
function render_login_page(array $o): void
{
    $base = $o['base'] ?? '../';
    page_open($o['title'], $base, 'bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 min-h-screen transition-colors duration-200');
    ?>
    <div class="min-h-screen flex flex-col">
        <div class="absolute top-4 left-4"><?= theme_button() ?></div>
        <main class="flex-grow flex items-center justify-center p-4">
            <div class="w-full max-w-md">
                <div class="flex justify-center mb-6"><?= brand_block($o['sub']) ?></div>
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                        <h2 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
                            <i class="fa-solid <?= e($o['icon']) ?> text-blue-500"></i> <?= e($o['heading']) ?>
                        </h2>
                    </div>
                    <form method="post" autocomplete="off" class="p-6 space-y-4" id="loginForm">
                        <?= csrf_field() ?>
                        <?php if (!empty($o['error'])): ?>
                            <div id="loginError" class="px-3.5 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-300 text-xs font-semibold border border-rose-200 dark:border-rose-800/40">
                                <i class="fa-solid fa-circle-exclamation"></i> <?= e($o['error']) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label for="loginName" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5"><?= e($o['label']) ?></label>
                            <input type="text" id="loginName" name="<?= e($o['field']) ?>" required maxlength="120"
                                   value="<?= e($o['value'] ?? '') ?>" placeholder="<?= e($o['placeholder']) ?>"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="loginPassword" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">كلمة المرور</label>
                            <input type="password" id="loginPassword" name="password" required maxlength="128" autocomplete="current-password"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button type="submit" class="w-full px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-600/20 transition-all hover:scale-[1.02] active:scale-95">
                            <i class="fa-solid fa-right-to-bracket"></i> تسجيل الدخول
                        </button>
                    </form>
                </div>
                <p class="text-center mt-4">
                    <a href="<?= e($o['other_href']) ?>" class="text-xs text-blue-600 dark:text-blue-400 font-bold hover:underline"><?= e($o['other_text']) ?></a>
                </p>
            </div>
        </main>
        <?= footer_block() ?>
    </div>
    <?php
    page_close($base);
}
