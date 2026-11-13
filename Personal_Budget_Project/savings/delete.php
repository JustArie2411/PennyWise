<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/connect.php';
require_once '../includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM savings_goals WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    log_activity($pdo, $user_id, 'DELETE_SAVINGS_GOAL', "Deleted savings goal ID #{$id}");
}

header("Location: index.php");
exit();
?>