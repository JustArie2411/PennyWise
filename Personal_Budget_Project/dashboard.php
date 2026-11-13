<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/connect.php';
require_once 'includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();
$period = $_GET['period'] ?? 'this_month';

$date_filter_income = "";
$date_filter_expense = "";

switch ($period) {
    case 'today':
        $date_filter_income = " AND DATE(i.income_date) = CURDATE()";
        $date_filter_expense = " AND DATE(e.expense_date) = CURDATE()";
        break;
    case 'this_week':
        $date_filter_income = " AND i.income_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $date_filter_expense = " AND e.expense_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'last_month':
        $date_filter_income = " AND MONTH(i.income_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(i.income_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        $date_filter_expense = " AND MONTH(e.expense_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(e.expense_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
    case 'this_year':
        $date_filter_income = " AND YEAR(i.income_date) = YEAR(CURDATE())";
        $date_filter_expense = " AND YEAR(e.expense_date) = YEAR(CURDATE())";
        break;
    case 'this_month':
    default:
        $date_filter_income = " AND MONTH(i.income_date) = MONTH(CURDATE()) AND YEAR(i.income_date) = YEAR(CURDATE())";
        $date_filter_expense = " AND MONTH(e.expense_date) = MONTH(CURDATE()) AND YEAR(e.expense_date) = YEAR(CURDATE())";
        break;
}

// Filtered income and expenses for the selected period cards
$inc_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM income i WHERE i.user_id = ?" . $date_filter_income);
$inc_stmt->execute([$user_id]);
$total_income = $inc_stmt->fetch()['total'] ?? 0.00;

$exp_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM expenses e WHERE e.user_id = ?" . $date_filter_expense);
$exp_stmt->execute([$user_id]);
$total_expenses = $exp_stmt->fetch()['total'] ?? 0.00;

$bud_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM budgets WHERE user_id = ?");
$bud_stmt->execute([$user_id]);
$total_planned_budgets = $bud_stmt->fetch()['total'] ?? 0.00;

// To avoid negative unallocated money when viewing filtered short-term periods (like Today/This Week),
// we calculate overall unallocated pool from total cumulative income vs total budgets, 
// or you can scope it properly. Let's use overall cumulative income for unallocated money so it doesn't break on daily filters:
$all_inc_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM income WHERE user_id = ?");
$all_inc_stmt->execute([$user_id]);
$cumulative_income = $all_inc_stmt->fetch()['total'] ?? 0.00;

$sav_contrib_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM savings_contributions WHERE user_id = ? AND funding_source = 'unallocated'");
$sav_contrib_stmt->execute([$user_id]);
$unallocated_savings_contributions = $sav_contrib_stmt->fetch()['total'] ?? 0.00;

// Unallocated money uses total cumulative income minus total planned budgets
$unallocated_money = ($cumulative_income - $total_planned_budgets) - $unallocated_savings_contributions;

$sav_stmt = $pdo->prepare("SELECT SUM(current_amount) AS total FROM savings_goals WHERE user_id = ?");
$sav_stmt->execute([$user_id]);
$total_savings = $sav_stmt->fetch()['total'] ?? 0.00;

$budgets_stmt = $pdo->prepare("
    SELECT b.*, c.name AS category_name,
    (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.user_id = b.user_id AND e.category_id = b.category_id) AS actual_spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ?
    LIMIT 4
");
$budgets_stmt->execute([$user_id]);
$budgets = $budgets_stmt->fetchAll();

$trans_stmt = $pdo->prepare("
    SELECT 'income' AS type, i.id, c.name AS category_name, i.description, i.amount, i.income_date AS trans_date 
    FROM income i JOIN categories c ON i.category_id = c.id WHERE i.user_id = ?
    UNION ALL
    SELECT 'expense' AS type, e.id, c.name AS category_name, e.description, e.amount, e.expense_date AS trans_date 
    FROM expenses e JOIN categories c ON e.category_id = c.id WHERE e.user_id = ?
    ORDER BY trans_date DESC LIMIT 5
");
$trans_stmt->execute([$user_id, $user_id]);
$recent_transactions = $trans_stmt->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1>Dashboard Overview</h1>
            <p style="color: var(--text-secondary);">Welcome back! Here is your real-time financial summary.</p>
        </div>
        
        <form method="GET" action="dashboard.php">
            <select name="period" onchange="this.form.submit()" class="form-control" style="padding: 8px 14px; font-weight: 600; font-size: 0.875rem;">
                <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="this_week" <?php echo $period == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                <option value="this_month" <?php echo $period == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                <option value="last_month" <?php echo $period == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                <option value="this_year" <?php echo $period == 'this_year' ? 'selected' : ''; ?>>This Year</option>
            </select>
        </form>
    </div>

    <div class="card-grid">
        <div class="stat-card" style="animation: fadeInUp 0.4s ease-out 0.05s backwards;">
            <h4>Total Income</h4>
            <div class="value" style="color: var(--success);"><?php echo format_currency($total_income); ?></div>
        </div>
        <div class="stat-card" style="animation: fadeInUp 0.4s ease-out 0.10s backwards;">
            <h4>Total Expenses</h4>
            <div class="value" style="color: var(--danger);"><?php echo format_currency($total_expenses); ?></div>
        </div>
        <div class="stat-card" style="animation: fadeInUp 0.4s ease-out 0.15s backwards;">
            <h4>Unallocated Money</h4>
            <div class="value" style="color: <?php echo $unallocated_money >= 0 ? 'var(--primary)' : 'var(--danger)'; ?>;"><?php echo format_currency($unallocated_money); ?></div>
        </div>
        <div class="stat-card" style="animation: fadeInUp 0.4s ease-out 0.20s backwards;">
            <h4>Total Savings</h4>
            <div class="value" style="color: var(--success);"><?php echo format_currency($total_savings); ?></div>
        </div>
    </div>

    <div style="margin-bottom: 28px; animation: fadeInUp 0.4s ease-out 0.25s backwards;">
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 12px; color: var(--text-primary);">Financial Alerts & Status</h3>
        <?php 
        $over_budget = false;
        foreach($budgets as $b) {
            if ($b['actual_spent'] > $b['amount']) { $over_budget = true; break; }
        }
        if ($over_budget): ?>
            <div style="background: var(--danger-bg); border: 1px solid #FCA5A5; color: var(--danger); padding: 14px 18px; border-radius: var(--radius); display: flex; align-items: center; gap: 10px; font-weight: 500;">
                <span class="material-symbols-rounded">error</span> Warning: One or more of your budget limits has been exceeded!
            </div>
        <?php else: ?>
            <div style="background: var(--success-bg); border: 1px solid #A7F3D0; color: var(--success); padding: 14px 18px; border-radius: var(--radius); display: flex; align-items: center; gap: 10px; font-weight: 500;">
                <span class="material-symbols-rounded">check_circle</span> You are within your budget limits. Great job managing your finances!
            </div>
        <?php endif; ?>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px;">

        <div class="stat-card" style="animation: fadeInUp 0.4s ease-out 0.30s backwards; padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 1.1rem; font-weight: 700;">Budget Overview</h3>
                <a href="budgets/index.php" style="font-size: 0.85rem; color: var(--primary); text-decoration: none; font-weight: 600;">View All</a>
            </div>

            <?php if (empty($budgets)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 30px 0; font-size: 0.9rem;">No active budgets found.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach($budgets as $b): 
                        $spent = $b['actual_spent']; 
                        $limit = $b['amount']; 
                        $status = get_budget_status($spent, $limit);
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; font-weight: 600; margin-bottom: 6px;">
                                <span><?php echo sanitize($b['category_name']); ?></span>
                                <span style="color: var(--text-secondary);"><?php echo format_currency($spent); ?> / <?php echo format_currency($limit); ?></span>
                            </div>
                            <div class="progress-bar-container">
                               <div class="progress-bar-fill" style="width: <?php echo min(100, $status['percentage']); ?>% !important; background: <?php echo $status['color']; ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="stat-card" style="animation: fadeInUp 0.4s ease-out 0.35s backwards; padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 1.1rem; font-weight: 700;">Recent Transactions</h3>
                <a href="expenses/index.php" style="font-size: 0.85rem; color: var(--primary); text-decoration: none; font-weight: 600;">View History</a>
            </div>

            <?php if (empty($recent_transactions)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 30px 0; font-size: 0.9rem;">No transactions recorded yet.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach($recent_transactions as $t): 
                        $is_inc = $t['type'] === 'income';
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: <?php echo $is_inc ? 'var(--success-bg)' : 'var(--danger-bg)'; ?>; color: <?php echo $is_inc ? 'var(--success)' : 'var(--danger)'; ?>; display: flex; align-items: center; justify-content: center;">
                                    <span class="material-symbols-rounded" style="font-size: 20px;"><?php echo $is_inc ? 'arrow_downward' : 'arrow_upward'; ?></span>
                                </div>
                                <div>
                                    <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary); margin-bottom: 2px;"><?php echo sanitize($t['category_name']); ?></h4>
                                    <span style="font-size: 0.775rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($t['trans_date'])); ?></span>
                                </div>
                            </div>
                            <span style="font-size: 0.9rem; font-weight: 700; color: <?php echo $is_inc ? 'var(--success)' : 'var(--danger)'; ?>;">
                                <?php echo ($is_inc ? '+' : '-') . format_currency($t['amount']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
