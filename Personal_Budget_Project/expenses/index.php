<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/connect.php';
require_once '../includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();
$csrf_token = generate_csrf_token();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed.";
    } else {
        $action = $_POST['action'] ?? '';
        $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $description = trim($_POST['description'] ?? '');
        $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
        $expense_id = (int)($_POST['expense_id'] ?? 0);

        if ($amount === false || $amount <= 0) $errors[] = "Amount must be greater than 0.";
        if ($category_id <= 0) $errors[] = "Select a valid category.";

        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO expenses (user_id, category_id, description, payment_method, amount, expense_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $category_id, $description, $payment_method, $amount, $expense_date]);
                log_activity($pdo, $user_id, 'ADD_EXPENSE', "Spent " . format_currency($amount) . " on " . $description);
            } elseif ($action === 'edit' && $expense_id > 0) {
                $stmt = $pdo->prepare("UPDATE expenses SET category_id = ?, description = ?, payment_method = ?, amount = ?, expense_date = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$category_id, $description, $payment_method, $amount, $expense_date, $expense_id, $user_id]);
                log_activity($pdo, $user_id, 'EDIT_EXPENSE', "Updated expense ID #{$expense_id}");
            }
            header("Location: index.php");
            exit();
        }
    }
}

if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->execute([$delete_id, $user_id]);
    log_activity($pdo, $user_id, 'DELETE_EXPENSE', "Deleted expense record #{$delete_id}");
    header("Location: index.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$payment_filter = $_GET['payment_method'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$sql = "SELECT e.*, c.name AS category_name FROM expenses e JOIN categories c ON e.category_id = c.id WHERE e.user_id = :uid";
$params = [':uid' => $user_id];

if (!empty($search)) {
    $sql .= " AND (e.description LIKE :search OR c.name LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if (!empty($category_filter)) {
    $sql .= " AND e.category_id = :cat";
    $params[':cat'] = $category_filter;
}
if (!empty($payment_filter)) {
    $sql .= " AND e.payment_method = :pm";
    $params[':pm'] = $payment_filter;
}

switch ($sort) {
    case 'oldest': $sql .= " ORDER BY e.expense_date ASC, e.id ASC"; break;
    case 'highest': $sql .= " ORDER BY e.amount DESC"; break;
    case 'lowest': $sql .= " ORDER BY e.amount ASC"; break;
    case 'newest': default: $sql .= " ORDER BY e.expense_date DESC, e.id DESC"; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$total_stmt = $pdo->prepare("SELECT SUM(amount) AS total, MAX(amount) as largest FROM expenses WHERE user_id = ?");
$total_stmt->execute([$user_id]);
$agg = $total_stmt->fetch();
$total_expenses = $agg['total'] ?? 0.00;
$largest_expense = $agg['largest'] ?? 0.00;

$weekly_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM expenses WHERE user_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$weekly_stmt->execute([$user_id]);
$weekly_expenses = $weekly_stmt->fetch()['total'] ?? 0.00;

$monthly_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM expenses WHERE user_id = ? AND MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())");
$monthly_stmt->execute([$user_id]);
$monthly_expenses = $monthly_stmt->fetch()['total'] ?? 0.00;

$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'expense' ORDER BY name ASC");
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1>Expense Records</h1>
            <p style="color: var(--text-secondary);">Log and analyze your daily spending and category distributions.</p>
        </div>
        <button onclick="openExpenseModal('add')" class="btn" style="background: var(--danger);">
            <span class="material-symbols-rounded">add</span> Add Expense
        </button>
    </div>

    <div class="card-grid">
        <div class="stat-card">
            <h4>Weekly Expenses</h4>
            <div class="value" style="color: var(--danger);"><?php echo format_currency($weekly_expenses); ?></div>
        </div>
        <div class="stat-card">
            <h4>Monthly Expenses</h4>
            <div class="value" style="color: var(--danger);"><?php echo format_currency($monthly_expenses); ?></div>
        </div>
        <div class="stat-card">
            <h4>Largest Expense</h4>
            <div class="value" style="color: #EA580C;"><?php echo format_currency($largest_expense); ?></div>
        </div>
        <div class="stat-card">
            <h4>Total Expenses</h4>
            <div class="value" style="color: var(--danger);"><?php echo format_currency($total_expenses); ?></div>
        </div>
    </div>

    <form method="GET" action="index.php" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
        <input type="text" name="search" class="form-control" placeholder="Search expenses..." value="<?php echo sanitize($search); ?>" style="flex:1; min-width: 180px;">
        <select name="category" class="form-control" style="width: auto;">
            <option value="">All Categories</option>
            <?php foreach($categories as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $category_filter == $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="payment_method" class="form-control" style="width: auto;">
            <option value="">All Payment Methods</option>
            <option value="Cash" <?php echo $payment_filter == 'Cash' ? 'selected' : ''; ?>>Cash</option>
            <option value="GCash" <?php echo $payment_filter == 'GCash' ? 'selected' : ''; ?>>GCash</option>
            <option value="Maya" <?php echo $payment_filter == 'Maya' ? 'selected' : ''; ?>>Maya</option>
            <option value="Bank Transfer" <?php echo $payment_filter == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
        </select>
        <button type="submit" class="btn" style="background: var(--surface); color: var(--text-primary); border: 1px solid var(--border);">Filter</button>
    </form>

    <div class="table-container">
        <?php if (empty($expenses)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                <span class="material-symbols-rounded" style="font-size: 48px; color: var(--border);">receipt_long</span>
                <p style="margin-top: 8px;">No expense records found. Record your spending to update your budget automatically.</p>
                <button onclick="openExpenseModal('add')" class="btn" style="margin-top: 12px; background: var(--danger);">+ Add Expense</button>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($e['expense_date'])); ?></td>
                            <td><span class="badge badge-danger"><?php echo sanitize($e['category_name']); ?></span></td>
                            <td><?php echo sanitize($e['description'] ?: '—'); ?></td>
                            <td><?php echo sanitize($e['payment_method']); ?></td>
                            <td style="font-weight: 700; color: var(--danger);"><?php echo format_currency($e['amount']); ?></td>
                            <td style="text-align: right;">
                                <button onclick='editExpense(<?php echo json_encode($e); ?>)' style="background:none; border:none; color: var(--primary); cursor:pointer; margin-right: 8px;">
                                    <span class="material-symbols-rounded">edit</span>
                                </button>
                                <button onclick="confirmDelete(<?php echo $e['id']; ?>)" style="background:none; border:none; color: var(--danger); cursor:pointer;">
                                    <span class="material-symbols-rounded">delete</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<div id="expenseModal" class="modal-overlay">
    <div class="modal-card">
        <h3 id="modalTitle">Add Expense</h3>
        <form method="POST" action="index.php" style="margin-top: 16px; text-align: left;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="expense_id" id="expenseId" value="">

            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Category</label>
                <select name="category_id" id="expCategory" class="form-control" required style="margin-top: 4px;">
                    <?php foreach($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Payment Method</label>
                <select name="payment_method" id="expMethod" class="form-control" style="margin-top: 4px;">
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash</option>
                    <option value="Maya">Maya</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Amount (₱)</label>
                <input type="number" step="0.01" name="amount" id="expAmount" class="form-control" placeholder="0.00" required style="margin-top: 4px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Date</label>
                <input type="date" name="expense_date" id="expDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="margin-top: 4px;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Description</label>
                <input type="text" name="description" id="expDescription" class="form-control" placeholder="e.g. Lunch at Cafeteria" style="margin-top: 4px;">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeExpenseModal()" class="modal-btn-cancel">Cancel</button>
                <button type="submit" class="btn" style="flex:1; background: var(--danger);">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-icon-wrapper"><span class="material-symbols-rounded">warning</span></div>
        <h3>Delete Expense Record?</h3>
        <p>This action cannot be undone and will recalculate actual spending.</p>
        <div class="modal-actions">
            <button onclick="closeDeleteModal()" class="modal-btn-cancel">Cancel</button>
            <a id="deleteConfirmBtn" href="#" class="modal-btn-logout">Delete</a>
        </div>
    </div>
</div>

<script>
function openExpenseModal(act) {
    document.getElementById('formAction').value = act;
    document.getElementById('modalTitle').innerText = act === 'add' ? 'Add Expense' : 'Edit Expense';
    if(act === 'add') {
        document.getElementById('expenseId').value = '';
        document.getElementById('expAmount').value = '';
        document.getElementById('expDescription').value = '';
    }
    document.getElementById('expenseModal').classList.add('active');
}
function closeExpenseModal() { document.getElementById('expenseModal').classList.remove('active'); }
function editExpense(rec) {
    openExpenseModal('edit');
    document.getElementById('expenseId').value = rec.id;
    document.getElementById('expCategory').value = rec.category_id;
    document.getElementById('expMethod').value = rec.payment_method;
    document.getElementById('expAmount').value = rec.amount;
    document.getElementById('expDate').value = rec.expense_date;
    document.getElementById('expDescription').value = rec.description;
}
function confirmDelete(id) {
    document.getElementById('deleteConfirmBtn').href = 'index.php?delete=' + id;
    document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }
</script>

<?php require_once '../includes/footer.php'; ?>