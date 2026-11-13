<?php
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/csrf.php';

$user_id = get_logged_in_user_id();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $goal_name = trim($_POST['goal_name'] ?? '');
    $target_amount = (float)($_POST['target_amount'] ?? 0);
    $initial_amount = (float)($_POST['initial_amount'] ?? 0);
    $target_date = $_POST['target_date'] ?? '';

    if (empty($goal_name) || $target_amount <= 0 || empty($target_date)) {
        $error = "Please fill in all required fields with valid amounts.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO savings_goals (user_id, goal_name, target_amount, current_amount, target_date, status) VALUES (?, ?, ?, ?, ?, 'In Progress')");
        if ($stmt->execute([$user_id, $goal_name, $target_amount, $initial_amount, $target_date])) {
            log_activity($pdo, $user_id, 'ADD_SAVINGS_GOAL', "Created savings goal: {$goal_name}");
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to create savings goal.";
        }
    }
}
$csrf_token = generate_csrf_token();
?>

<main class="main-content">
    <div style="max-width: 500px; background: var(--surface); padding: 32px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
        <h2 style="margin-bottom: 24px;">Create Savings Goal</h2>
        
        <?php if(!empty($error)): ?>
            <div style="background: var(--danger-bg); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 0.875rem; font-weight: 500;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="add.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label>Goal Name</label>
                <input type="text" name="goal_name" class="form-control" placeholder="e.g., New Laptop, Emergency Fund" required>
            </div>

            <div class="form-group">
                <label>Target Amount (₱)</label>
                <input type="number" step="0.01" name="target_amount" class="form-control" placeholder="0.00" required>
            </div>

            <div class="form-group">
                <label>Initial Savings / Starting Amount (₱)</label>
                <input type="number" step="0.01" name="initial_amount" class="form-control" placeholder="0.00" value="0.00">
            </div>

            <div class="form-group">
                <label>Target Date</label>
                <input type="date" name="target_date" class="form-control" required>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn">Save Goal</button>
                <a href="index.php" class="btn" style="background: var(--border); color: var(--text-primary); box-shadow: none;">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>