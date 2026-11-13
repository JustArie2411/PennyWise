<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/connect.php';
require_once 'includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currency = trim($_POST['currency'] ?? 'PHP');
    $date_format = trim($_POST['date_format'] ?? 'Y-m-d');
    $theme = trim($_POST['theme'] ?? 'light');
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $budget_alerts = isset($_POST['budget_alerts']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, currency, date_format, theme, email_notifications, budget_alerts) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE currency = ?, date_format = ?, theme = ?, email_notifications = ?, budget_alerts = ?
        ");
        $stmt->execute([
            $user_id, $currency, $date_format, $theme, $email_notifications, $budget_alerts,
            $currency, $date_format, $theme, $email_notifications, $budget_alerts
        ]);
    } catch (Exception $e) {
       
        $_SESSION['settings'] = [
            'currency' => $currency,
            'date_format' => $date_format,
            'theme' => $theme
        ];
    }

    log_activity($pdo, $user_id, 'UPDATE_SETTINGS', "Updated system application settings");
    $success_msg = "Application settings updated successfully!";
}

$settings = [
    'currency' => 'PHP',
    'date_format' => 'Y-m-d',
    'theme' => 'light',
    'email_notifications' => 1,
    'budget_alerts' => 1
];

try {
    $set_stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $set_stmt->execute([$user_id]);
    $res = $set_stmt->fetch();
    if ($res) {
        $settings = $res;
    }
} catch (Exception $e) {
    
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <div style="max-width: 700px; margin: 0 auto;">
        <div style="margin-bottom: 24px;">
            <h1>Application Settings</h1>
            <p style="color: var(--text-secondary);">Customize your currency, display preferences, and notification rules.</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div style="background: #ECFDF5; color: #059669; padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded">check_circle</span>
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <form action="settings.php" method="POST">
            <div style="background: var(--surface); padding: 30px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 24px;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded">tune</span> General Preferences
                </h3>

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Default Currency</label>
                    <select name="currency" class="form-control" style="width: 100%;">
                        <option value="PHP" <?php echo ($settings['currency'] ?? 'PHP') === 'PHP' ? 'selected' : ''; ?>>Philippine Peso (PHP ₱)</option>
                        <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD $)</option>
                        <option value="EUR" <?php echo ($settings['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR €)</option>
                    </select>
                </div>

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Date Format</label>
                    <select name="date_format" class="form-control" style="width: 100%;">
                        <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2026-11-03)</option>
                        <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (11/03/2026)</option>
                        <option value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (03/11/2026)</option>
                    </select>
                </div>

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Interface Theme</label>
                    <select name="theme" class="form-control" style="width: 100%;">
                        <option value="light" <?php echo ($settings['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light Theme (Default Purple/Blue)</option>
                        <option value="dark" <?php echo ($settings['theme'] ?? '') === 'dark' ? 'selected' : ''; ?>>Dark Theme (Coming Soon)</option>
                    </select>
                </div>
            </div>

            <div style="background: var(--surface); padding: 30px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 24px;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded">notifications</span> Notification Preferences
                </h3>

                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                    <input type="checkbox" name="budget_alerts" id="budget_alerts" value="1" <?php echo !empty($settings['budget_alerts']) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <label for="budget_alerts" style="font-size: 0.9rem; font-weight: 500; cursor: pointer;">Enable automated budget limit and spending alerts</label>
                </div>

                <div style="display: flex; align-items: center; gap: 12px;">
                    <input type="checkbox" name="email_notifications" id="email_notifications" value="1" <?php echo !empty($settings['email_notifications']) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <label for="email_notifications" style="font-size: 0.9rem; font-weight: 500; cursor: pointer;">Receive weekly financial summary and goal updates</label>
                </div>
            </div>

            <button type="submit" class="btn" style="padding: 12px 24px;">Save Settings</button>
        </form>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>