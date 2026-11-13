<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/connect.php';
require_once 'includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $notif_id = (int)($_GET['id'] ?? 0);

    if ($action === 'mark_read' && $notif_id > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
    } elseif ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } elseif ($action === 'delete' && $notif_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
    }

    header("Location: notifications.php");
    exit();
}

$budgets_stmt = $pdo->prepare("
    SELECT b.*, c.name AS category_name,
    (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.user_id = b.user_id AND e.category_id = b.category_id) AS actual_spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ?
");
$budgets_stmt->execute([$user_id]);
$budgets = $budgets_stmt->fetchAll();

foreach ($budgets as $b) {
    $limit = $b['amount'];
    $spent = $b['actual_spent'];
    $percentage = $limit > 0 ? ($spent / $limit) * 100 : 0;

    if ($percentage >= 100) {
        $msg = "Your " . $b['category_name'] . " budget has been exceeded! Spent: ₱" . number_format($spent, 2) . " of ₱" . number_format($limit, 2);
        $chk = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND message = ? AND DATE(created_at) = CURDATE()");
        $chk->execute([$user_id, $msg]);
        if ($chk->rowCount() === 0) {
            $ins = $pdo->prepare("INSERT INTO notifications (user_id, type, message, is_read) VALUES (?, 'over_budget', ?, 0)");
            $ins->execute([$user_id, $msg]);
        }
    } elseif ($percentage >= 85) {
        $msg = "Your " . $b['category_name'] . " budget is " . round($percentage) . "% used.";
        $chk = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND message = ? AND DATE(created_at) = CURDATE()");
        $chk->execute([$user_id, $msg]);
        if ($chk->rowCount() === 0) {
            $ins = $pdo->prepare("INSERT INTO notifications (user_id, type, message, is_read) VALUES (?, 'budget_warning', ?, 0)");
            $ins->execute([$user_id, $msg]);
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <div style="max-width: 800px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div>
                <h1>Notifications</h1>
                <p style="color: var(--text-secondary);">Stay updated on budget limits, alerts, and financial milestones.</p>
            </div>
            
            <?php if (!empty($notifications)): ?>
                <a href="notifications.php?action=mark_all_read" class="btn" style="background: var(--surface); color: var(--text-primary); border: 1px solid var(--border); font-size: 0.85rem;">
                    <span class="material-symbols-rounded" style="font-size: 18px;">done_all</span> Mark All as Read
                </a>
            <?php endif; ?>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php if (empty($notifications)): ?>
                <div style="background: var(--surface); padding: 40px; text-align: center; border-radius: var(--radius); border: 1px solid var(--border); color: var(--text-secondary);">
                    <span class="material-symbols-rounded" style="font-size: 48px; color: var(--border); display: block; margin-bottom: 8px;">notifications_off</span>
                    You have no new notifications right now.
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): 
                    $is_read = (bool)$n['is_read'];
                    $type = $n['type'] ?? 'info';
                    $icon = 'notifications';
                    $icon_bg = '#EEF2FF';
                    $icon_color = 'var(--primary)';

                    if ($type === 'over_budget') {
                        $icon = 'error';
                        $icon_bg = '#FEE2E2';
                        $icon_color = 'var(--danger)';
                    } elseif ($type === 'budget_warning') {
                        $icon = 'warning';
                        $icon_bg = '#FFEDD5';
                        $icon_color = '#EA580C';
                    } elseif ($type === 'savings') {
                        $icon = 'savings';
                        $icon_bg = '#ECFDF5';
                        $icon_color = 'var(--success)';
                    }
                ?>
                    <div style="background: <?php echo $is_read ? 'var(--surface)' : '#F8FAFC'; ?>; padding: 16px 20px; border-radius: var(--radius); border: 1px solid <?php echo $is_read ? 'var(--border)' : 'var(--primary)'; ?>; display: flex; align-items: center; justify-content: space-between; gap: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.2s ease;">
                        <div style="display: flex; align-items: center; gap: 16px;">
                            <div style="width: 42px; height: 42px; border-radius: 50%; background: <?php echo $icon_bg; ?>; color: <?php echo $icon_color; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <span class="material-symbols-rounded" style="font-size: 22px;"><?php echo $icon; ?></span>
                            </div>
                            <div>
                                <p style="font-size: 0.9rem; font-weight: <?php echo $is_read ? '500' : '700'; ?>; color: var(--text-primary); margin-bottom: 2px;"><?php echo sanitize($n['message']); ?></p>
                                <span style="font-size: 0.775rem; color: var(--text-secondary);"><?php echo date('M d, Y • h:i A', strtotime($n['created_at'] ?? 'now')); ?></span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 8px; align-items: center;">
                            <?php if (!$is_read): ?>
                                <a href="notifications.php?action=mark_read&id=<?php echo $n['id']; ?>" title="Mark as Read" style="color: var(--primary); text-decoration: none; padding: 4px;"><span class="material-symbols-rounded" style="font-size: 20px;">check_circle</span></a>
                            <?php endif; ?>
                            <a href="notifications.php?action=delete&id=<?php echo $n['id']; ?>" title="Delete Notification" style="color: var(--text-secondary); text-decoration: none; padding: 4px;"><span class="material-symbols-rounded" style="font-size: 20px;">close</span></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>