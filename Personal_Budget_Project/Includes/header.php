<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/connect.php';
require_login();

$is_subfolder = file_exists('../includes/connect.php');
$asset_path = $is_subfolder ? '../assets/css/style.css' : 'assets/css/style.css';
$root_path = $is_subfolder ? '../' : '';

$user_id = get_logged_in_user_id();
$unread_count = 0;

try {
    $unread_stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0");
    $unread_stmt->execute([$user_id]);
    $unread_count = (int)($unread_stmt->fetch()['count'] ?? 0);
} catch (Exception $e) {
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PennyWise - Personal Budget Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?php echo $asset_path; ?>">
    <style>
        .hamburger-btn {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-primary, #0F172A);
            font-size: 26px;
            padding: 4px;
            align-items: center;
            justify-content: center;
        }
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .mobile-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .mobile-menu-drawer {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100%;
            background: #0F172A;
            z-index: 1000;
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            padding: 24px;
            transition: left 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            color: #FFFFFF;
            overflow-y: auto;
        }
        .mobile-menu-drawer.active {
            left: 0;
        }
        .mobile-nav-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 20px;
        }
        .mobile-nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #94A3B8;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .mobile-nav-links a:hover, .mobile-nav-links a.active {
            background: #4F46E5;
            color: #FFFFFF;
        }
        @media (max-width: 992px) {
            .hamburger-btn {
                display: flex;
            }
            .app-sidebar, .sidebar {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<div class="app-container">
    <header class="top-header" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: var(--surface, #FFFFFF); border-bottom: 1px solid var(--border, #E2E8F0);">
        <div style="display: flex; align-items: center; gap: 16px;">
            <button class="hamburger-btn" id="hamburgerToggle" aria-label="Toggle navigation menu">
                <span class="material-symbols-rounded">menu</span>
            </button>
            <a href="<?php echo $root_path; ?>dashboard.php" style="text-decoration: none; color: var(--text-primary); font-family: 'Poppins', sans-serif; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded" style="color: var(--primary); font-size: 28px;">account_balance_wallet</span>
                PennyWise
            </a>
        </div>

        <div style="display: flex; align-items: center; gap: 20px;">
            <a href="<?php echo $root_path; ?>notifications.php" title="Notifications" style="position: relative; color: var(--text-primary); text-decoration: none; display: flex; align-items: center;">
                <span class="material-symbols-rounded" style="font-size: 26px;">notifications</span>
                <?php if ($unread_count > 0): ?>
                    <span style="position: absolute; top: -4px; right: -4px; background: var(--danger, #EF4444); color: white; font-size: 0.65rem; font-weight: 700; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 99px; display: flex; align-items: center; justify-content: center; border: 2px solid var(--surface, #FFFFFF);">
                        <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="<?php echo $root_path; ?>profile.php" title="Profile Settings" style="display: flex; align-items: center; gap: 8px; text-decoration: none; color: var(--text-primary); font-size: 0.875rem; font-weight: 600;">
                <div style="width: 34px; height: 34px; border-radius: 50%; background: #EEF2FF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
                </div>
            </a>
        </div>
    </header>

    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    <div class="mobile-menu-drawer" id="mobileMenuDrawer">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <div style="display: flex; align-items: center; gap: 10px; font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.15rem; color: #FFFFFF;">
                <div style="width: 32px; height: 32px; background: #EEF2FF; color: #4F46E5; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span class="material-symbols-rounded" style="font-size: 18px;">account_balance_wallet</span>
                </div>
                PennyWise
            </div>
            <button id="hamburgerClose" style="background: rgba(255,255,255,0.1); border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #FFFFFF;">
                <span class="material-symbols-rounded" style="font-size: 18px;">close</span>
            </button>
        </div>

        <ul class="mobile-nav-links">
            <li><a href="<?php echo $root_path; ?>dashboard.php"><span class="material-symbols-rounded">dashboard</span> Dashboard</a></li>
            <li><a href="<?php echo $root_path; ?>income/index.php"><span class="material-symbols-rounded">payments</span> Income</a></li>
            <li><a href="<?php echo $root_path; ?>expenses/index.php"><span class="material-symbols-rounded">receipt_long</span> Expenses</a></li>
            <li><a href="<?php echo $root_path; ?>budgets/index.php"><span class="material-symbols-rounded">pie_chart</span> Budgets</a></li>
            <li><a href="<?php echo $root_path; ?>savings/index.php"><span class="material-symbols-rounded">savings</span> Savings Goals</a></li>
            <li><a href="<?php echo $root_path; ?>reports/index.php"><span class="material-symbols-rounded">bar_chart</span> Reports</a></li>
            <li><a href="<?php echo $root_path; ?>profile.php"><span class="material-symbols-rounded">person</span> Profile</a></li>
            <li><a href="<?php echo $root_path; ?>settings.php"><span class="material-symbols-rounded">settings</span> Settings</a></li>
            <li style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px;"><a href="<?php echo $root_path; ?>logout.php" style="color: #FCA5A5;"><span class="material-symbols-rounded">logout</span> Logout</a></li>
        </ul>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerToggle = document.getElementById('hamburgerToggle');
    const hamburgerClose = document.getElementById('hamburgerClose');
    const mobileMenuDrawer = document.getElementById('mobileMenuDrawer');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

    function toggleMenu() {
        if (mobileMenuDrawer && mobileMenuOverlay) {
            mobileMenuDrawer.classList.toggle('active');
            mobileMenuOverlay.classList.toggle('active');
            document.body.style.overflow = mobileMenuDrawer.classList.contains('active') ? 'hidden' : '';
        }
    }

    if (hamburgerToggle) hamburgerToggle.addEventListener('click', toggleMenu);
    if (hamburgerClose) hamburgerClose.addEventListener('click', toggleMenu);
    if (mobileMenuOverlay) mobileMenuOverlay.addEventListener('click', toggleMenu);
});
</script>