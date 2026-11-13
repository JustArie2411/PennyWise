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
        $category_id = (int)($_POST['category_id'] ?? 0);
        $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $period = $_POST['period'] ?? 'monthly';

        if ($category_id <= 0 || $amount === false || $amount <= 0) {
            $errors[] = "Provide a valid category and positive budget limit.";
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO budgets (user_id, category_id, amount, period) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE amount = VALUES(amount), period = VALUES(period)
            ");
            $stmt->execute([$user_id, $category_id, $amount, $period]);
            log_activity($pdo, $user_id, 'SAVE_BUDGET', "Set budget of " . format_currency($amount) . " for category #{$category_id}");
            header("Location: index.php");
            exit();
        }
    }
}

if (isset($_GET['delete'])) {
    $b_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ? AND user_id = ?");
    $stmt->execute([$b_id, $user_id]);
    log_activity($pdo, $user_id, 'DELETE_BUDGET', "Deleted budget #{$b_id}");
    header("Location: index.php");
    exit();
}

$inc_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM income WHERE user_id = ?");
$inc_stmt->execute([$user_id]);
$total_income = $inc_stmt->fetch()['total'] ?? 0.00;

$exp_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM expenses WHERE user_id = ?");
$exp_stmt->execute([$user_id]);
$total_expenses = $exp_stmt->fetch()['total'] ?? 0.00;

$sav_contrib_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM savings_contributions WHERE user_id = ? AND (funding_source = 'unallocated' OR funding_source IS NULL)");
$sav_contrib_stmt->execute([$user_id]);
$unallocated_savings_contributions = $sav_contrib_stmt->fetch()['total'] ?? 0.00;

$budgets_stmt = $pdo->prepare("
    SELECT b.*, c.name AS category_name,
    (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.user_id = b.user_id AND e.category_id = b.category_id) AS actual_spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ?
    ORDER BY c.name ASC
");
$budgets_stmt->execute([$user_id]);
$budgets = $budgets_stmt->fetchAll();

$total_planned = 0.00;
$total_actual_spent = 0.00;
foreach ($budgets as $b) {
    $total_planned += $b['amount'];
    $total_actual_spent += $b['actual_spent'];
}

$unallocated_money = ($total_income - $total_planned) - $unallocated_savings_contributions;

$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'expense' ORDER BY name ASC");
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1>Budget Management</h1>
            <p style="color: var(--text-secondary);">Automated category spending limits and allocation balances.</p>
        </div>
        <button onclick="openBudgetModal()" class="btn">
            <span class="material-symbols-rounded">add</span> Create Budget
        </button>
    </div>

    <div class="card-grid">
        <div class="stat-card">
            <h4>Total Planned Budget</h4>
            <div class="value" style="color: var(--primary);"><?php echo format_currency($total_planned); ?></div>
        </div>
        <div class="stat-card">
            <h4>Total Spent (Actual)</h4>
            <div class="value" style="color: var(--danger);"><?php echo format_currency($total_actual_spent); ?></div>
        </div>
        <div class="stat-card">
            <h4>Total Remaining</h4>
            <div class="value" style="color: var(--success);"><?php echo format_currency($total_planned - $total_actual_spent); ?></div>
        </div>
        <div class="stat-card">
            <h4>Unallocated Money</h4>
            <div class="value" style="color: <?php echo $unallocated_money >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                <?php echo format_currency($unallocated_money); ?>
            </div>
            <p style="font-size: 0.75rem; margin-top: 4px; color: var(--text-secondary);">
                <?php 
                    if ($unallocated_money > 0) echo "You have " . format_currency($unallocated_money) . " unallocated.";
                    elseif ($unallocated_money == 0) echo "All available money allocated.";
                    else echo "Budget exceeds available money by " . format_currency(abs($unallocated_money)) . "!";
                ?>
            </p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
        <?php if (empty($budgets)): ?>
            <div style="grid-column: 1 / -1; background: var(--surface); padding: 40px; text-align: center; border-radius: var(--radius); border: 1px solid var(--border); color: var(--text-secondary);">
                <span class="material-symbols-rounded" style="font-size: 48px; color: var(--border);">pie_chart</span>
                <p style="margin-top: 8px;">No active budgets found. Create a budget to start automated expense monitoring.</p>
                <button onclick="openBudgetModal()" class="btn" style="margin-top: 12px;">+ Create Budget</button>
            </div>
        <?php else: ?>
            <?php foreach ($budgets as $b): 
                $spent = $b['actual_spent'];
                $limit = $b['amount'];
                $remaining = $limit - $spent;
                $status_info = get_budget_status($spent, $limit);
            ?>
                <div style="background: var(--surface); padding: 20px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="font-size: 1.1rem; font-weight: 700;"><?php echo sanitize($b['category_name']); ?></h3>
                        <span class="badge <?php echo $status_info['class']; ?>"><?php echo $status_info['status']; ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 8px;">
                        <span>Spent: <strong><?php echo format_currency($spent); ?></strong></span>
                        <span>Limit: <strong><?php echo format_currency($limit); ?></strong></span>
                    </div>

                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo min(100, $status_info['percentage']); ?>%; background: <?php echo $status_info['color']; ?>;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; font-size: 0.85rem;">
                        <span style="color: var(--text-secondary);"><?php echo $status_info['percentage']; ?>% used</span>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span style="font-weight: 600; color: <?php echo $remaining >= 0 ? 'var(--text-primary)' : 'var(--danger)'; ?>;">
                                <?php echo $remaining >= 0 ? format_currency($remaining) . " left" : format_currency(abs($remaining)) . " over"; ?>
                            </span>
                            <button onclick="confirmDelete(<?php echo $b['id']; ?>)" style="background:none; border:none; color: var(--danger); cursor:pointer;">
                                <span class="material-symbols-rounded" style="font-size: 18px;">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<div id="budgetModal" class="modal-overlay">
    <div class="modal-card">
        <h3>Create / Set Budget</h3>
        <form method="POST" action="index.php" style="margin-top: 16px; text-align: left;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Category</label>
                <select name="category_id" class="form-control" required style="margin-top: 4px;">
                    <?php foreach($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Planned Budget Limit (₱)</label>
                <input type="number" step="0.01" name="amount" class="form-control" placeholder="350.00" required style="margin-top: 4px;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Frequency Period</label>
                <select name="period" class="form-control" style="margin-top: 4px;">
                    <option value="monthly">Monthly</option>
                    <option value="weekly">Weekly</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeBudgetModal()" class="modal-btn-cancel">Cancel</button>
                <button type="submit" class="btn" style="flex:1;">Save Budget</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-icon-wrapper"><span class="material-symbols-rounded">warning</span></div>
        <h3>Delete Budget Limit?</h3>
        <p>This action will remove automated limit alerts for this category.</p>
        <div class="modal-actions">
            <button onclick="closeDeleteModal()" class="modal-btn-cancel">Cancel</button>
            <a id="deleteConfirmBtn" href="#" class="modal-btn-logout">Delete</a>
        </div>
    </div>
</div>

<script>
function openBudgetModal() { document.getElementById('budgetModal').classList.add('active'); }
function closeBudgetModal() { document.getElementById('budgetModal').classList.remove('active'); }
function confirmDelete(id) {
    document.getElementById('deleteConfirmBtn',).href = 'index.php?delete=' + id;
    document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }
</script>

<?php require_once '../includes/footer.php'; ?>