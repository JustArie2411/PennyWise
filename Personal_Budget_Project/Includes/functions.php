<?php
require_once __DIR__ . '/connect.php';

function sanitize($string) {
    return htmlspecialchars(trim($string ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_currency($amount) {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

function log_activity($pdo, $user_id, $action, $details) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $details]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function get_budget_status($spent, $limit) {
    $spent = (float)$spent;
    $limit = (float)$limit;

    if ($limit <= 0) {
        return ['percentage' => 0, 'status' => 'No Limit', 'class' => 'badge-success', 'color' => '#10B981'];
    }
    
    $percentage = ($spent / $limit) * 100;
    $rounded_pct = round($percentage, 2);
    
    if ($percentage >= 100) {
        return ['percentage' => $rounded_pct, 'status' => 'Over Budget', 'class' => 'badge-danger', 'color' => '#EF4444'];
    } elseif ($percentage >= 90) {
        return ['percentage' => $rounded_pct, 'status' => 'Near Limit', 'class' => 'badge-warning', 'color' => '#F59E0B'];
    } elseif ($percentage >= 70) {
        return ['percentage' => $rounded_pct, 'status' => 'Approaching Limit', 'class' => 'badge-warning', 'color' => '#F59E0B'];
    } else {
        return ['percentage' => $rounded_pct, 'status' => 'On Track', 'class' => 'badge-success', 'color' => '#10B981'];
    }
}
?>