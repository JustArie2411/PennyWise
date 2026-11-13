<?php
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/csrf.php';

$user_id = get_logged_in_user_id();
$goal_id = (int)($_GET['id'] ?? 0);
$error = '';

$stmt = $pdo->prepare("SELECT * FROM savings_goals WHERE id = ? AND user_id = ?");
$stmt->execute([$goal_id, $user_id]);
$goal = $stmt->fetch();

if (!$goal) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $contribution = (float)($_POST['contribution'] ?? 0);

    if ($contribution <= 0) {
        $error = "Please enter an amount greater than 0.";
    } else {
        $new_current = $goal['current_amount'] + $contribution;
        $status = ($new_current >= $goal['target_amount']) ? 'Completed' : 'In Progress';

        $update = $pdo->prepare("UPDATE savings_goals SET current_amount = ?, status = ? WHERE id = ? AND user_id = ?");
        if ($update->execute([$new_current, $status, $goal_id, $user_id])) {
            log_activity($pdo, $user_id, 'CONTRIBUTE_SAVINGS', "Added ₱{$contribution} to goal: {$goal['goal_name']}");
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to add contribution.";
        }
    }
}
$csrf_token = generate_csrf_token();
?>

<main class="main-content">
    <div style="max-width: 500px; background: var(--surface); padding: 32px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
        <h2 style="margin-bottom: 8px;">Add Funds to Goal</h2>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 24px;"><?php echo sanitize($goal['goal_name']); ?></p>
        
        <?php if(!empty($error)): ?>
            <div style="background: var(--danger-bg); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 0.875rem; font-weight: 500;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="contribute.php?id=<?php echo $goal_id; ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label>Contribution Amount (₱)</label>
                <input type="number" step="0.01" name="contribution" class="form-control" placeholder="0.00" required>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn">Add Funds</button>
                <a href="index.php" class="btn" style="background: var(--border); color: var(--text-primary); box-shadow: none;">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>