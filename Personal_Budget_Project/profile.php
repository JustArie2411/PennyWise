<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/connect.php';
require_once 'includes/functions.php';
require_login();

$user_id = get_logged_in_user_id();
$success_msg = '';
$error_msg = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($username) || empty($email)) {
            $error_msg = "All profile fields are required.";
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check->execute([$username, $email, $user_id]);
            if ($check->rowCount() > 0) {
                $error_msg = "Username or email is already in use by another account.";
            } else {
                $update = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ? WHERE id = ?");
                $update->execute([$name, $username, $email, $user_id]);
                log_activity($pdo, $user_id, 'UPDATE_PROFILE', "Updated personal profile details");
                $success_msg = "Profile information updated successfully!";
 
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        }
    }

    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_msg = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_msg = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_msg = "New password must be at least 6 characters long.";
        } else {
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $pass_update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $pass_update->execute([$hashed_password, $user_id]);
                
                log_activity($pdo, $user_id, 'CHANGE_PASSWORD', "Changed account password securely");
                $success_msg = "Password changed successfully!";
            } else {
                $error_msg = "Current password is incorrect.";
            }
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <div style="max-width: 700px; margin: 0 auto;">
        <div style="margin-bottom: 24px;">
            <h1>Account Profile & Security</h1>
            <p style="color: var(--text-secondary);">Manage your personal information and update your password securely.</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div style="background: #ECFDF5; color: #059669; padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded">check_circle</span>
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div style="background: #FEE2E2; color: #DC2626; padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded">warning</span>
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div style="background: var(--surface); padding: 30px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 24px;">
            <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded">person</span> Personal Information
            </h3>

            <form action="profile.php" method="POST">
                <input type="hidden" name="action" value="update_profile">

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo sanitize($user['name'] ?? ''); ?>" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo sanitize($user['username'] ?? ''); ?>" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo sanitize($user['email'] ?? ''); ?>" required style="width: 100%;">
                </div>

                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>

        <div style="background: var(--surface); padding: 30px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
            <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded">lock</span> Change Password
            </h3>

            <form action="profile.php" method="POST">
                <input type="hidden" name="action" value="change_password">

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Current Password</label>
                    <input type="password" name="current_password" class="form-control" placeholder="••••••••" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required style="width: 100%;">
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required style="width: 100%;">
                </div>

                <button type="submit" class="btn" style="background: var(--primary);">Update Password</button>
            </form>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>