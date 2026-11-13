<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/connect.php';
require_once '../includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();

$period = $_GET['period'] ?? 'this_month';
$category_id = $_GET['category'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=financial_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Type', 'Category', 'Description', 'Amount', 'Date']);

    $i_csv = $pdo->prepare("SELECT 'Income' AS type, c.name AS category, i.description, i.amount, i.income_date FROM income i JOIN categories c ON i.category_id = c.id WHERE i.user_id = ?");
    $i_csv->execute([$user_id]);
    while ($row = $i_csv->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }

    $e_csv = $pdo->prepare("SELECT 'Expense' AS type, c.name AS category, e.description, e.amount, e.expense_date FROM expenses e JOIN categories c ON e.category_id = c.id WHERE e.user_id = ?");
    $e_csv->execute([$user_id]);
    while ($row = $e_csv->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }

    fclose($output);
    exit();
}

$date_filter_sql_income = "";
$date_filter_sql_expense = "";
if (!empty($date_from) && !empty($date_to)) {
    $date_filter_sql_income = " AND i.income_date BETWEEN :date_from AND :date_to";
    $date_filter_sql_expense = " AND e.expense_date BETWEEN :date_from AND :date_to";
} else {
    switch ($period) {
        case 'today':
            $date_filter_sql_income = " AND DATE(i.income_date) = CURDATE()";
            $date_filter_sql_expense = " AND DATE(e.expense_date) = CURDATE()";
            break;
        case 'this_week':
            $date_filter_sql_income = " AND i.income_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $date_filter_sql_expense = " AND e.expense_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'last_month':
            $date_filter_sql_income = " AND MONTH(i.income_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(i.income_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            $date_filter_sql_expense = " AND MONTH(e.expense_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(e.expense_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            break;
        case 'this_year':
            $date_filter_sql_income = " AND YEAR(i.income_date) = YEAR(CURDATE())";
            $date_filter_sql_expense = " AND YEAR(e.expense_date) = YEAR(CURDATE())";
            break;
        case 'this_month':
        default:
            $date_filter_sql_income = " AND MONTH(i.income_date) = MONTH(CURDATE()) AND YEAR(i.income_date) = YEAR(CURDATE())";
            $date_filter_sql_expense = " AND MONTH(e.expense_date) = MONTH(CURDATE()) AND YEAR(e.expense_date) = YEAR(CURDATE())";
            break;
    }
}

$inc_q = "SELECT SUM(amount) AS total FROM income i WHERE i.user_id = :uid" . $date_filter_sql_income;
$inc_stmt = $pdo->prepare($inc_q);
$params = [':uid' => $user_id];
if (!empty($date_from) && !empty($date_to)) {
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
}
$inc_stmt->execute($params);
$total_income = $inc_stmt->fetch()['total'] ?? 0.00;

$exp_q = "SELECT SUM(amount) AS total FROM expenses e WHERE e.user_id = :uid" . $date_filter_sql_expense;
$exp_stmt = $pdo->prepare($exp_q);
$exp_stmt->execute($params);
$total_expenses = $exp_stmt->fetch()['total'] ?? 0.00;

$bud_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM budgets WHERE user_id = ?");
$bud_stmt->execute([$user_id]);
$total_planned_budgets = $bud_stmt->fetch()['total'] ?? 0.00;

$sav_contrib_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM savings_contributions WHERE user_id = ? AND (funding_source = 'unallocated' OR funding_source IS NULL)");
$sav_contrib_stmt->execute([$user_id]);
$unallocated_savings_contributions = $sav_contrib_stmt->fetch()['total'] ?? 0.00;

$current_balance = ($total_income - $total_planned_budgets) - $unallocated_savings_contributions;

$sav_stmt = $pdo->prepare("SELECT SUM(current_amount) AS total FROM savings_goals WHERE user_id = ?");
$sav_stmt->execute([$user_id]);
$total_savings = $sav_stmt->fetch()['total'] ?? 0.00;

$cat_exp_stmt = $pdo->prepare("
    SELECT c.name, SUM(e.amount) AS total 
    FROM expenses e 
    JOIN categories c ON e.category_id = c.id 
    WHERE e.user_id = ? 
    GROUP BY c.name
");
$cat_exp_stmt->execute([$user_id]);
$category_expenses = $cat_exp_stmt->fetchAll();

$cat_labels = [];
$cat_data = [];
foreach ($category_expenses as $ce) {
    $cat_labels[] = $ce['name'];
    $cat_data[] = (float)$ce['total'];
}

$b_stmt = $pdo->prepare("
    SELECT c.name, b.amount AS budget_amount,
    (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.user_id = b.user_id AND e.category_id = b.category_id) AS actual_amount
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ?
");
$b_stmt->execute([$user_id]);
$budget_comparisons = $b_stmt->fetchAll();

$b_labels = [];
$b_budget_data = [];
$b_actual_data = [];
foreach ($budget_comparisons as $bc) {
    $b_labels[] = $bc['name'];
    $b_budget_data[] = (float)$bc['budget_amount'];
    $b_actual_data[] = (float)$bc['actual_amount'];
}

$cat_list_stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
$cat_list_stmt->execute();
$all_categories = $cat_list_stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; animation: fadeInUp 0.4s ease-out 0.05s backwards;">
        <div>
            <h1>Financial Reports & Analytics</h1>
            <p style="color: var(--text-secondary);">Analyze your income, spending trends, and budget performance.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="index.php?export=csv" class="btn" style="background: var(--surface); color: var(--text-primary); border: 1px solid var(--border);"><span class="material-symbols-rounded">download</span> Export CSV</a>
            <button onclick="window.print()" class="btn" style="background: var(--surface); color: var(--text-primary); border: 1px solid var(--border);"><span class="material-symbols-rounded">print</span> Print Report</button>
        </div>
    </div>

    <form method="GET" action="index.php" style="background: var(--surface); padding: 20px; border-radius: var(--radius); border: 1px solid var(--border); margin-bottom: 24px; display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end; animation: fadeInUp 0.4s ease-out 0.10s backwards;">
        <div>
            <label style="display: block; font-size: 0.775rem; font-weight: 600; margin-bottom: 4px; color: var(--text-secondary);">Period</label>
            <select name="period" class="form-control" style="min-width: 150px;">
                <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="this_week" <?php echo $period == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                <option value="this_month" <?php echo $period == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                <option value="last_month" <?php echo $period == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                <option value="this_year" <?php echo $period == 'this_year' ? 'selected' : ''; ?>>This Year</option>
            </select>
        </div>

        <div>
            <label style="display: block; font-size: 0.775rem; font-weight: 600; margin-bottom: 4px; color: var(--text-secondary);">Category</label>
            <select name="category" class="form-control" style="min-width: 160px;">
                <option value="all">All Categories</option>
                <?php foreach($all_categories as $ac): ?>
                    <option value="<?php echo $ac['id']; ?>" <?php echo $category_id == $ac['id'] ? 'selected' : ''; ?>><?php echo sanitize($ac['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display: block; font-size: 0.775rem; font-weight: 600; margin-bottom: 4px; color: var(--text-secondary);">From Date</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo sanitize($date_from); ?>">
        </div>

        <div>
            <label style="display: block; font-size: 0.775rem; font-weight: 600; margin-bottom: 4px; color: var(--text-secondary);">To Date</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo sanitize($date_to); ?>">
        </div>

        <button type="submit" class="btn">Generate Report</button>
        <a href="index.php" class="btn" style="background: var(--border); color: var(--text-primary);">Reset</a>
    </form>

    <div class="card-grid" style="animation: fadeInUp 0.4s ease-out 0.15s backwards;">
        <div class="stat-card">
            <h4>Total Income</h4>
            <div class="value" style="color: var(--success);"><?php echo format_currency($total_income); ?></div>
        </div>
        <div class="stat-card">
            <h4>Total Expenses</h4>
            <div class="value" style="color: var(--danger);"><?php echo format_currency($total_expenses); ?></div>
        </div>
        <div class="stat-card">
            <h4>Unallocated Money</h4>
            <div class="value" style="color: <?php echo $current_balance >= 0 ? 'var(--primary)' : 'var(--danger)'; ?>;"><?php echo format_currency($current_balance); ?></div>
        </div>
        <div class="stat-card">
            <h4>Total Savings</h4>
            <div class="value" style="color: var(--success);"><?php echo format_currency($total_savings); ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px; margin-bottom: 30px; animation: fadeInUp 0.4s ease-out 0.20s backwards;">

        <div class="stat-card" style="padding: 24px;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 16px;">Expenses by Category</h3>
            <div style="position: relative; height: 300px; display: flex; justify-content: center; align-items: center;">
                <canvas id="expenseCategoryChart"></canvas>
            </div>
        </div>

        <div class="stat-card" style="padding: 24px;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 16px;">Budget vs Actual Spending</h3>
            <div style="position: relative; height: 300px; display: flex; justify-content: center; align-items: center;">
                <canvas id="budgetComparisonChart"></canvas>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const catCtx = document.getElementById('expenseCategoryChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($cat_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($cat_data); ?>,
                backgroundColor: [
                    '#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#64748B'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1000
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    const budgetCtx = document.getElementById('budgetComparisonChart').getContext('2d');
    new Chart(budgetCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($b_labels); ?>,
            datasets: [
                {
                    label: 'Planned Budget',
                    data: <?php echo json_encode($b_budget_data); ?>,
                    backgroundColor: '#4F46E5',
                    borderRadius: 6
                },
                {
                    label: 'Actual Spending',
                    data: <?php echo json_encode($b_actual_data); ?>,
                    backgroundColor: '#EF4444',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1200,
                easing: 'easeOutQuart'
            },
            scales: {
                y: { beginAtZero: true }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>