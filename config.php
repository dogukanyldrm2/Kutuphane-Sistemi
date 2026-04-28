<?php
declare(strict_types=1);

define('APP_NAME', 'Kutuphane Sistemi');

define('DB_HOST', 'localhost'); 
define('DB_NAME', 'library_system'); 
define('DB_USER', 'root'); 
define('DB_PASS', '' );
define('REMEMBER_COOKIE_NAME', 'library_remember');
define('REMEMBER_COOKIE_DAYS', 30);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = rtrim($scriptDir === '/' ? '' : $scriptDir, '/');
define('BASE_URL', $baseUrl);
