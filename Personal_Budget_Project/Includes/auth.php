<?php
require_once __DIR__ . '/config.php';

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /Personal_Budget_Project/login.php");
        exit();
    }
}

function get_logged_in_user_id() {
    return $_SESSION['user_id'] ?? null;
}
?>