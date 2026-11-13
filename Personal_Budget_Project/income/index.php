<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/connect.php';
require_once '../includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();
$csrf_token = generate_csrf_token();
$errors = [];
$success_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token mismatch. Please reload the page.";
    } else {
        $action = $_POST['action'] ?? '';
        $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $income_date = $_POST['income_date'] ?? date('Y-m-d');
        $income_id = (int)($_POST['income_id'] ?? 0);

        if ($amount === false || $amount <= 0) {
            $errors[] = "Please enter a valid positive income amount.";
        }
        if ($category_id <= 0) {
            $errors[] = "Please select a valid income category.";
        }
        if (empty($income_date)) {
            $errors[] = "Please specify a valid transaction date.";
        }

        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO income (user_id, category_id, description, amount, income_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $category_id, $description, $amount, $income_date]);
                log_activity($pdo, $user_id, 'ADD_INCOME', "Added income of " . format_currency($amount) . " (" . $description . ")");
                $success_msg = "Income record successfully added!";
            } elseif ($action === 'edit' && $income_id > 0) {
                $stmt = $pdo->prepare("UPDATE income SET category_id = ?, description = ?, amount = ?, income_date = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$category_id, $description, $amount, $income_date, $income_id, $user_id]);
                log_activity($pdo, $user_id, 'EDIT_INCOME', "Updated income ID #{$income_id} to " . format_currency($amount));
                $success_msg = "Income record updated successfully!";
            }
        }
    }
}
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM income WHERE id = ? AND user_id = ?");
    $stmt->execute([$delete_id, $user_id]);
    log_activity($pdo, $user_id, 'DELETE_INCOME', "Deleted income record #{$delete_id}");
    header("Location: index.php?msg=deleted");
    exit();
}

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$category_filter = $_GET['category'] ?? '';

$sql = "SELECT i.*, c.name AS category_name FROM income i JOIN categories c ON i.category_id = c.id WHERE i.user_id = :uid";
$params = [':uid' => $user_id];

if (!empty($search)) {
    $sql .= " AND (i.description LIKE :search OR c.name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($category_filter)) {
    $sql .= " AND i.category_id = :cat";
    $params[':cat'] = $category_filter;
}

switch ($sort) {
    case 'oldest': $sql .= " ORDER BY i.income_date ASC, i.id ASC"; break;
    case 'highest': $sql .= " ORDER BY i.amount DESC"; break;
    case 'lowest': $sql .= " ORDER BY i.amount ASC"; break;
    case 'newest': default: $sql .= " ORDER BY i.income_date DESC, i.id DESC"; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$income_records = $stmt->fetchAll();

$total_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM income WHERE user_id = ?");
$total_stmt->execute([$user_id]);
$historical_total = $total_stmt->fetch()['total'] ?? 0.00;

$monthly_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM income WHERE user_id = ? AND MONTH(income_date) = MONTH(CURDATE()) AND YEAR(income_date) = YEAR(CURDATE())");
$monthly_stmt->execute([$user_id]);
$monthly_total = $monthly_stmt->fetch()['total'] ?? 0.00;

$weekly_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM income WHERE user_id = ? AND income_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$weekly_stmt->execute([$user_id]);
$weekly_total = $weekly_stmt->fetch()['total'] ?? 0.00;

$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'income' ORDER BY name ASC");
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1>Income Records</h1>
            <p style="color: var(--text-secondary);">Manage and track your historical income sources and allowances.</p>
        </div>
        <button onclick="openIncomeModal('add')" class="btn">
            <span class="material-symbols-rounded">add</span> Add Income
        </button>
    </div>

    <div class="card-grid">
        <div class="stat-card">
            <h4>Weekly Total</h4>
            <div class="value" style="color: var(--success);"><?php echo format_currency($weekly_total); ?></div>
        </div>
        <div class="stat-card">
            <h4>Monthly Total</h4>
            <div class="value" style="color: var(--success);"><?php echo format_currency($monthly_total); ?></div>
        </div>
        <div class="stat-card">
            <h4>Historical Total</h4>
            <div class="value" style="color: var(--primary);"><?php echo format_currency($historical_total); ?></div>
        </div>
    </div>

    <form method="GET" action="index.php" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
        <input type="text" name="search" class="form-control" placeholder="Search description or category..." value="<?php echo sanitize($search); ?>" style="flex: 1; min-width: 200px;">
        <select name="category" class="form-control" style="width: auto;">
            <option value="">All Categories</option>
            <?php foreach($categories as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $category_filter == $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sort" class="form-control" style="width: auto;">
            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
            <option value="highest" <?php echo $sort == 'highest' ? 'selected' : ''; ?>>Highest Amount</option>
            <option value="lowest" <?php echo $sort == 'lowest' ? 'selected' : ''; ?>>Lowest Amount</option>
        </select>
        <button type="submit" class="btn" style="background: var(--surface); color: var(--text-primary); border: 1px solid var(--border);">Filter</button>
    </form>

    <div class="table-container">
        <?php if (empty($income_records)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                <span class="material-symbols-rounded" style="font-size: 48px; color: var(--border);">account_balance_wallet</span>
                <p style="margin-top: 8px;">No income records found. Start recording your earnings!</p>
                <button onclick="openIncomeModal('add')" class="btn" style="margin-top: 12px;">+ Add Income</button>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($income_records as $rec): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($rec['income_date'])); ?></td>
                            <td><span class="badge badge-success"><?php echo sanitize($rec['category_name']); ?></span></td>
                            <td><?php echo sanitize($rec['description'] ?: '—'); ?></td>
                            <td style="font-weight: 700; color: var(--success);"><?php echo format_currency($rec['amount']); ?></td>
                            <td style="text-align: right;">
                                <button onclick='editIncome(<?php echo json_encode($rec); ?>)' style="background:none; border:none; color: var(--primary); cursor:pointer; margin-right: 8px;">
                                    <span class="material-symbols-rounded">edit</span>
                                </button>
                                <button onclick="confirmDelete(<?php echo $rec['id']; ?>)" style="background:none; border:none; color: var(--danger); cursor:pointer;">
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

<div id="incomeModal" class="modal-overlay">
    <div class="modal-card">
        <h3 id="modalTitle">Add Income</h3>
        <form method="POST" action="index.php" style="margin-top: 16px; text-align: left;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="income_id" id="incomeId" value="">

            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Category</label>
                <select name="category_id" id="incomeCategory" class="form-control" required style="margin-top: 4px;">
                    <?php foreach($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Amount (₱)</label>
                <input type="number" step="0.01" name="amount" id="incomeAmount" class="form-control" placeholder="0.00" required style="margin-top: 4px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Date</label>
                <input type="date" name="income_date" id="incomeDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="margin-top: 4px;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Description</label>
                <input type="text" name="description" id="incomeDescription" class="form-control" placeholder="e.g. Weekly Allowance" style="margin-top: 4px;">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeIncomeModal()" class="modal-btn-cancel">Cancel</button>
                <button type="submit" class="btn" style="flex:1;">Save Record</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-icon-wrapper">
            <span class="material-symbols-rounded">warning</span>
        </div>
        <h3>Delete Income Record?</h3>
        <p>This action cannot be undone and will update your available balance.</p>
        <div class="modal-actions">
            <button onclick="closeDeleteModal()" class="modal-btn-cancel">Cancel</button>
            <a id="deleteConfirmBtn" href="#" class="modal-btn-logout">Delete</a>
        </div>
    </div>
</div>

<script>
function openIncomeModal(action) {
    document.getElementById('formAction').value = action;
    document.getElementById('modalTitle').innerText = action === 'add' ? 'Add Income' : 'Edit Income';
    if(action === 'add') {
        document.getElementById('incomeId').value = '';
        document.getElementById('incomeAmount').value = '';
        document.getElementById('incomeDescription').value = '';
    }
    document.getElementById('incomeModal').classList.add('active');
}
function closeIncomeModal() { document.getElementById('incomeModal').classList.remove('active'); }
function editIncome(rec) {
    openIncomeModal('edit');
    document.getElementById('incomeId').value = rec.id;
    document.getElementById('incomeCategory').value = rec.category_id;
    document.getElementById('incomeAmount').value = rec.amount;
    document.getElementById('incomeDate').value = rec.income_date;
    document.getElementById('incomeDescription').value = rec.description;
}
function confirmDelete(id) {
    document.getElementById('deleteConfirmBtn').href = 'index.php?delete=' + id;
    document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }
</script>

<?php require_once '../includes/footer.php'; ?>