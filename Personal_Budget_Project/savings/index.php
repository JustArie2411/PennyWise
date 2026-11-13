<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/connect.php';
require_once '../includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();
$csrf_token = generate_csrf_token();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_goal') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $target_amount = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $target_date = $_POST['target_date'] ?? null;

        if (empty($title) || $target_amount <= 0) {
            $errors[] = "Provide a goal title and valid target amount.";
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO savings_goals (user_id, title, target_amount, current_amount, target_date, status) VALUES (?, ?, ?, 0.00, ?, 'In Progress')");
            $stmt->execute([$user_id, $title, $target_amount, $target_date]);
            log_activity($pdo, $user_id, 'CREATE_SAVINGS_GOAL', "Created goal '{$title}' target " . format_currency($target_amount));
            header("Location: index.php");
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_money') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed.";
    } else {
        $goal_id = (int)($_POST['goal_id'] ?? 0);
        $contribution = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $funding_source = $_POST['funding_source'] ?? 'unallocated';

        if ($goal_id <= 0 || $contribution <= 0) {
            $errors[] = "Enter a valid positive contribution amount.";
        }

        if (empty($errors)) {
            $g_stmt = $pdo->prepare("SELECT * FROM savings_goals WHERE id = ? AND user_id = ?");
            $g_stmt->execute([$goal_id, $user_id]);
            $goal = $g_stmt->fetch();

            if ($goal) {
                $new_amount = $goal['current_amount'] + $contribution;
                if ($new_amount > $goal['target_amount']) {
                    $new_amount = $goal['target_amount'];
                }
                $new_status = ($new_amount >= $goal['target_amount']) ? 'Completed' : 'In Progress';

                try {
                    $pdo->beginTransaction();

                    $upd = $pdo->prepare("UPDATE savings_goals SET current_amount = ?, status = ? WHERE id = ? AND user_id = ?");
                    $upd->execute([$new_amount, $new_status, $goal_id, $user_id]);

                    $ins = $pdo->prepare("INSERT INTO savings_contributions (goal_id, user_id, amount, funding_source) VALUES (?, ?, ?, ?)");
                    $ins->execute([$goal_id, $user_id, $contribution, $funding_source]);

                    $pdo->commit();
                    log_activity($pdo, $user_id, 'ADD_SAVINGS', "Added " . format_currency($contribution) . " to goal '{$goal['title']}' via {$funding_source}");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Error processing deposit transaction.";
                }
            }
            if (empty($errors)) {
                header("Location: index.php");
                exit();
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM savings_goals WHERE id = ? AND user_id = ?");
    $stmt->execute([$del_id, $user_id]);
    log_activity($pdo, $user_id, 'DELETE_SAVINGS', "Deleted savings goal #{$del_id}");
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM savings_goals WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$goals = $stmt->fetchAll();

$total_saved_stmt = $pdo->prepare("SELECT SUM(current_amount) AS total FROM savings_goals WHERE user_id = ?");
$total_saved_stmt->execute([$user_id]);
$total_savings = $total_saved_stmt->fetch()['total'] ?? 0.00;

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">
    <?php if (!empty($errors)): ?>
        <div style="background: var(--danger-bg, #fee2e2); border: 1px solid var(--danger, #ef4444); color: var(--danger, #991b1b); padding: 12px; border-radius: var(--radius); margin-bottom: 20px;">
            <?php foreach($errors as $err): ?>
                <p><?php echo sanitize($err); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1>Savings Goals</h1>
            <p style="color: var(--text-secondary);">Set financial targets and record recurring contributions.</p>
        </div>
        <button onclick="openGoalModal()" class="btn" style="background: var(--success);">
            <span class="material-symbols-rounded">add</span> Create Savings Goal
        </button>
    </div>

    <div class="card-grid">
        <div class="stat-card">
            <h4>Total Saved Across Goals</h4>
            <div class="value" style="color: var(--success);"><?php echo format_currency($total_savings); ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
        <?php if (empty($goals)): ?>
            <div style="grid-column: 1 / -1; background: var(--surface); padding: 40px; text-align: center; border-radius: var(--radius); border: 1px solid var(--border); color: var(--text-secondary);">
                <span class="material-symbols-rounded" style="font-size: 48px; color: var(--border);">savings</span>
                <p style="margin-top: 8px;">No savings goals found. Create your first goal to start saving!</p>
                <button onclick="openGoalModal()" class="btn" style="margin-top: 12px; background: var(--success);">+ Create Goal</button>
            </div>
        <?php else: ?>
            <?php foreach ($goals as $g): 
                $target = $g['target_amount'];
                $saved = $g['current_amount'];
                $remaining = max(0, $target - $saved);
                $pct = $target > 0 ? min(100, ($saved / $target) * 100) : 0;
                $is_completed = ($g['status'] === 'Completed' || $pct >= 100);

                $days_remaining = 'No deadline';
                if (!empty($g['target_date'])) {
                    $diff = (strtotime($g['target_date']) - time());
                    $days = floor($diff / (60 * 60 * 24));
                    $days_remaining = $days > 0 ? "{$days} days remaining" : ($days == 0 ? "Target is today!" : "Overdue by " . abs($days) . " days");
                }
            ?>
                <div style="background: var(--surface); padding: 20px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow-sm); position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="font-size: 1.1rem; font-weight: 700;"><?php echo sanitize($g['title']); ?></h3>
                        <span class="badge <?php echo $is_completed ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo $is_completed ? 'Completed' : 'In Progress'; ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 6px;">
                        <span>Target: <strong><?php echo format_currency($target); ?></strong></span>
                        <span>Saved: <strong style="color: var(--success);"><?php echo format_currency($saved); ?></strong></span>
                    </div>

                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $pct; ?>%; background: var(--success);"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; font-size: 0.825rem;">
                        <span style="color: var(--text-secondary);"><?php echo round($pct, 2); ?>% (Remaining: <?php echo format_currency($remaining); ?>)</span>
                        <span style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo $days_remaining; ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; border-top: 1px solid var(--border); padding-top: 12px;">
                        <?php if (!$is_completed): ?>
                            <button onclick="openAddMoneyModal(<?php echo $g['id']; ?>, '<?php echo sanitize(addslashes($g['title'])); ?>')" class="btn" style="padding: 6px 12px; font-size: 0.8rem; background: var(--success);">
                                + Add Money
                            </button>
                        <?php else: ?>
                            <span style="color: var(--success); font-weight: 700; display: flex; align-items: center; gap: 4px;">
                                <span class="material-symbols-rounded">check_circle</span> Goal Reached!
                            </span>
                        <?php endif; ?>
                        <button onclick="confirmDelete(<?php echo $g['id']; ?>)" style="background:none; border:none; color: var(--danger); cursor:pointer;">
                            <span class="material-symbols-rounded" style="font-size: 20px;">delete</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<div id="goalModal" class="modal-overlay">
    <div class="modal-card">
        <h3>New Savings Goal</h3>
        <form method="POST" action="index.php" style="margin-top: 16px; text-align: left;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="create_goal">
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Goal Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. New Laptop" required style="margin-top: 4px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Target Amount (₱)</label>
                <input type="number" step="0.01" name="target_amount" class="form-control" placeholder="30000.00" required style="margin-top: 4px;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Target Completion Date</label>
                <input type="date" name="target_date" class="form-control" style="margin-top: 4px;">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeGoalModal()" class="modal-btn-cancel">Cancel</button>
                <button type="submit" class="btn" style="flex:1; background: var(--success);">Create Goal</button>
            </div>
        </form>
    </div>
</div>

<div id="addMoneyModal" class="modal-overlay">
    <div class="modal-card">
        <h3 id="addMoneyTitle">Add Savings Contribution</h3>
        <form method="POST" action="index.php" style="margin-top: 16px; text-align: left;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="add_money">
            <input type="hidden" name="goal_id" id="addMoneyGoalId" value="">
            
            <div style="margin-bottom: 12px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Contribution Amount (₱)</label>
                <input type="number" step="0.01" name="amount" class="form-control" placeholder="2000.00" required style="margin-top: 4px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Funding Source</label>
                <select name="funding_source" class="form-control" required style="margin-top: 4px;">
                    <option value="unallocated">From Unallocated Money (Deduct from remaining balance)</option>
                    <option value="external">External Source (e.g. Bank / Extra Cash)</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeAddMoneyModal()" class="modal-btn-cancel">Cancel</button>
                <button type="submit" class="btn" style="flex:1; background: var(--success);">Deposit Money</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-icon-wrapper"><span class="material-symbols-rounded">warning</span></div>
        <h3>Delete Savings Goal?</h3>
        <p>This action will permanently delete this goal and its contribution history.</p>
        <div class="modal-actions">
            <button onclick="closeDeleteModal()" class="modal-btn-cancel">Cancel</button>
            <a id="deleteConfirmBtn" href="#" class="modal-btn-logout">Delete</a>
        </div>
    </div>
</div>

<script>
function openGoalModal() { document.getElementById('goalModal').classList.add('active'); }
function closeGoalModal() { document.getElementById('goalModal').classList.remove('active'); }
function openAddMoneyModal(id, title) {
    document.getElementById('addMoneyGoalId').value = id;
    document.getElementById('addMoneyTitle').innerText = 'Add Money: ' + title;
    document.getElementById('addMoneyModal').classList.add('active');
}
function closeAddMoneyModal() { document.getElementById('addMoneyModal').classList.remove('active'); }
function confirmDelete(id) {
    document.getElementById('deleteConfirmBtn').href = 'index.php?delete=' + id;
    document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }
</script>

<?php require_once '../includes/footer.php'; ?>