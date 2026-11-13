<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/connect.php';
require_once '../includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();
$id = (int)($_GET['id'] ?? 0);
$error = '';
$stmt = $pdo->prepare("SELECT * FROM income WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$income = $stmt->fetch();

if (!$income) {
    header("Location: index.php");
    exit();
}

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
            $update = $pdo->prepare("UPDATE income SET category_id = ?, amount = ?, description = ?, income_date = ?, frequency = ? WHERE id = ? AND user_id = ?");
            $update->execute([$category_id, $amount, $description, $income_date, $frequency, $id, $user_id]);
        } catch (Exception $e) {
            $update = $pdo->prepare("UPDATE income SET category_id = ?, amount = ?, description = ?, income_date = ? WHERE id = ? AND user_id = ?");
            $update->execute([$category_id, $amount, $description, $income_date, $id, $user_id]);
        }

        log_activity($pdo, $user_id, 'EDIT_INCOME', "Updated income record ID #{$id}");
        header("Location: index.php?msg=updated");
        exit();
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">
    <div style="max-width: 600px; margin: 0 auto;">
        <div style="background: var(--surface); padding: 32px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 700; margin-bottom: 24px; color: var(--text-primary);">Edit Income Record</h2>

            <?php if (!empty($error)): ?>
                <div style="background: #FEE2E2; color: #DC2626; padding: 12px; border-radius: var(--radius); margin-bottom: 20px; font-size: 0.875rem; font-weight: 500;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="edit.php?id=<?php echo $id; ?>" method="POST">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Category</label>
                    <select name="category_id" class="form-control" required style="width: 100%;">
                        <option value="">Select Category</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $income['category_id'] == $cat['id'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Income Frequency</label>
                    <select name="frequency" class="form-control" required style="width: 100%;">
                        <option value="Weekly" <?php echo ($income['frequency'] ?? '') === 'Weekly' ? 'selected' : ''; ?>>Weekly Income</option>
                        <option value="Monthly" <?php echo ($income['frequency'] ?? '') === 'Monthly' ? 'selected' : ''; ?>>Monthly Income</option>
                        <option value="One-time" <?php echo ($income['frequency'] ?? 'One-time') === 'One-time' ? 'selected' : ''; ?>>One-time Income</option>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Amount (₱)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?php echo $income['amount']; ?>" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Description</label>
                    <input type="text" name="description" class="form-control" value="<?php echo sanitize($income['description']); ?>" placeholder="Optional notes" style="width: 100%;">
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Date</label>
                    <input type="date" name="income_date" class="form-control" value="<?php echo sanitize($income['income_date']); ?>" required style="width: 100%;">
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn" style="flex: 1; padding: 12px; justify-content: center;">Update Income</button>
                    <a href="index.php" class="btn" style="flex: 1; padding: 12px; text-align: center; justify-content: center; background: var(--border); color: var(--text-primary); text-decoration: none;">Cancel</a>
                </div>

            </form>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>