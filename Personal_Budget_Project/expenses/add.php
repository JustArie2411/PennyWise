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
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');
    $description = trim($_POST['description'] ?? '');
    $expense_date = $_POST['expense_date'] ?? '';

    if ($category_id <= 0 || $amount <= 0 || empty($expense_date)) {
        $error = "Please provide a valid category, amount greater than 0, and date.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, category_id, amount, payment_method, description, expense_date) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $category_id, $amount, $payment_method, $description, $expense_date])) {
            log_activity($pdo, $user_id, 'ADD_EXPENSE', "Added expense of ₱{$amount}");
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to add expense record.";
        }
    }
}
$csrf_token = generate_csrf_token();
?>

<main class="main-content">
    <div style="max-width: 500px; background: var(--surface); padding: 32px; border-radius: var(--radius); border: 1px solid var(--border);">
        <h2 style="margin-bottom: 24px;">Add New Expense</h2>
        <?php if(!empty($error)): ?>
            <div style="background: #FEE2E2; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem;"><?php echo $error; ?></div>
        <?php endif; ?>
        <form action="add.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" class="form-control" required>
                    <option value="Cash">Cash</option>
                    <option value="Bank">Bank Transfer</option>
                    <option value="Debit Card">Debit Card</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="E-Wallet">E-Wallet (GCash/Maya)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (₱)</label>
                <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" class="form-control" placeholder="Optional notes">
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn" style="background: var(--danger);">Save Expense</button>
                <a href="index.php" class="btn" style="background: var(--border); color: var(--text-primary);">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>