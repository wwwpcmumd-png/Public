<?php
declare(strict_types=1);

/** Shared PDO connection (prepared statements only, real server-side prepares). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        cfg('db_host', 'localhost'),
        (int)cfg('db_port', 3306),
        cfg('db_name', '')
    );
    $pdo = new PDO($dsn, (string)cfg('db_user', ''), (string)cfg('db_pass', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
