<?php
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/csrf.php';

$user_id = get_logged_in_user_id();
$error = '';

$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'Expense' ORDER BY name ASC");
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $category_id = (int)($_POST['category_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $month = (int)date('m');
    $year = (int)date('Y');

    if ($category_id <= 0 || $amount <= 0) {
        $error = "Please select a valid category and an amount greater than 0.";
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM budgets WHERE user_id = ? AND category_id = ? AND month = ? AND year = ?");
            $check->execute([$user_id, $category_id, $month, $year]);
            
            if ($check->fetch()) {
                $error = "A budget for this category already exists for this month.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO budgets (user_id, category_id, amount, month, year) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$user_id, $category_id, $amount, $month, $year])) {
                    log_activity($pdo, $user_id, 'ADD_BUDGET', "Created budget of ₱{$amount}");
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Failed to save budget.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
$csrf_token = generate_csrf_token();
?>

<main class="main-content">
    <div style="max-width: 500px; background: var(--surface); padding: 32px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
        <h2 style="margin-bottom: 24px;">Set Monthly Budget</h2>
        
        <?php if(!empty($error)): ?>
            <div style="background: var(--danger-bg); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 0.875rem; font-weight: 500;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="add.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label>Expense Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Monthly Budget Limit (₱)</label>
                <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn">Save Budget</button>
                <a href="index.php" class="btn" style="background: var(--border); color: var(--text-primary); box-shadow: none;">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>