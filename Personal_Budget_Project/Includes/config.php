<?php
if (ob_get_level() == 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

define('APP_NAME', 'PennyWise');
define('CURRENCY_SYMBOL', '₱');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'Personal_Budget');

date_default_timezone_set('Asia/Manila');
?>