<?php
declare(strict_types=1);

define('APP_NAME', 'Kutuphane Sistemi');
define('DB_HOST', 'sql113.infinityfree.com');
define('DB_NAME', 'if0_41646798_library_system');
define('DB_USER', 'if0_41646798');
define('DB_PASS', 'Huseynov06');
define('REMEMBER_COOKIE_NAME', 'library_remember');
define('REMEMBER_COOKIE_DAYS', 30);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = rtrim($scriptDir === '/' ? '' : $scriptDir, '/');
define('BASE_URL', $baseUrl);
