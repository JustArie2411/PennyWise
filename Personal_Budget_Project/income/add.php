<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/connect.php';
require_once '../includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();
$error = '';
$cat_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE type = 'income' ORDER BY name ASC");
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $income_date = trim($_POST['income_date'] ?? date('Y-m-d'));
    $frequency = trim($_POST['frequency'] ?? 'One-time');

    if ($category_id <= 0 || $amount <= 0 || empty($income_date)) {
        $error = "Please fill in all required fields with valid values.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO income (user_id, category_id, amount, description, income_date, frequency) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $category_id, $amount, $description, $income_date, $frequency]);
        } catch (Exception $e) {
            $stmt = $pdo->prepare("INSERT INTO income (user_id, category_id, amount, description, income_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $category_id, $amount, $description, $income_date]);
        }

        log_activity($pdo, $user_id, 'ADD_INCOME', "Added income of ₱{$amount} ({$frequency})");
        header("Location: index.php?msg=added");
        exit();
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">
    <div style="max-width: 600px; margin: 0 auto;">
        <div style="background: var(--surface); padding: 32px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 700; margin-bottom: 24px; color: var(--text-primary);">Add New Income</h2>

            <?php if (!empty($error)): ?>
                <div style="background: #FEE2E2; color: #DC2626; padding: 12px; border-radius: var(--radius); margin-bottom: 20px; font-size: 0.875rem; font-weight: 500;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="add.php" method="POST">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Category</label>
                    <select name="category_id" class="form-control" required style="width: 100%;">
                        <option value="">Select Category</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Income Frequency</label>
                    <select name="frequency" class="form-control" required style="width: 100%;">
                        <option value="Weekly">Weekly Income (e.g. Allowance)</option>
                        <option value="Monthly">Monthly Income (e.g. Salary / Allowance)</option>
                        <option value="One-time" selected>One-time Income</option>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Amount (₱)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Optional notes (e.g. Weekly allowance from parents)" style="width: 100%;">
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Date</label>
                    <input type="date" name="income_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%;">
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn" style="flex: 1; padding: 12px; justify-content: center;">Save Income</button>
                    <a href="index.php" class="btn" style="flex: 1; padding: 12px; text-align: center; justify-content: center; background: var(--border); color: var(--text-primary); text-decoration: none;">Cancel</a>
                </div>

            </form>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>