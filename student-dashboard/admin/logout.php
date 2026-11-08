<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

// Logout only via POST + valid CSRF token (prevents forced logout via image tags / links).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valid($_POST['csrf_token'] ?? null)) {
    do_logout();
    redirect('login.php');
}
redirect('index.php');
