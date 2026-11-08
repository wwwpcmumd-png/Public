<?php
/**
 * Application configuration.
 * Edit the database values below to match your XAMPP / hosting account.
 */
return [
    'db_host'    => 'localhost',
    'db_port'    => 3306,
    'db_name'    => 'student_dashboard',
    'db_user'    => 'root',   // XAMPP default. On shared hosting use the user from your control panel.
    'db_pass'    => '',       // XAMPP default is empty.

    // Set to true ONLY while debugging. Never leave it on in production.
    'debug'      => false,

    'session_name'      => 'SDASH_SESSID',
    'session_timeout'   => 7200,   // seconds of inactivity before automatic logout
    'max_failed_logins' => 8,      // failed attempts allowed per IP / portal ...
    'lockout_minutes'   => 15,     // ... within this many minutes
];
