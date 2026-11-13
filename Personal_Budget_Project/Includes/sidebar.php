<?php
$is_subfolder = file_exists('../includes/connect.php');
$root_path = $is_subfolder ? '../' : '';
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$user_name = $_SESSION['name'] ?? 'User';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <span class="material-symbols-rounded">account_balance_wallet</span>
            <h2>PennyWise</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="<?php echo $root_path; ?>dashboard.php" class="<?php echo $current_page == 'dashboard.php' && !$is_subfolder ? 'active' : ''; ?>"><span class="material-symbols-rounded">dashboard</span>Dashboard</a></li>
            <li><a href="<?php echo $root_path; ?>income/index.php" class="<?php echo $current_dir == 'income' ? 'active' : ''; ?>"><span class="material-symbols-rounded">payments</span>Income</a></li>
            <li><a href="<?php echo $root_path; ?>expenses/index.php" class="<?php echo $current_dir == 'expenses' ? 'active' : ''; ?>"><span class="material-symbols-rounded">receipt_long</span>Expenses</a></li>
            <li><a href="<?php echo $root_path; ?>budgets/index.php" class="<?php echo $current_dir == 'budgets' ? 'active' : ''; ?>"><span class="material-symbols-rounded">pie_chart</span>Budgets</a></li>
            <li><a href="<?php echo $root_path; ?>savings/index.php" class="<?php echo $current_dir == 'savings' ? 'active' : ''; ?>"><span class="material-symbols-rounded">savings</span>Savings Goals</a></li>
            <li><a href="<?php echo $root_path; ?>reports/index.php" class="<?php echo $current_dir == 'reports' ? 'active' : ''; ?>"><span class="material-symbols-rounded">bar_chart</span>Reports</a></li>
            <li><a href="#" id="logoutBtn"><span class="material-symbols-rounded">logout</span>Logout</a></li>
        </ul>
    </div>

    <div class="sidebar-user" id="profileToggle">
        <div class="user-avatar"><?php echo $user_initial; ?></div>
        <div class="sidebar-user-info" style="flex: 1; overflow: hidden;">
            <div class="name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="role" style="color: var(--sidebar-text); font-size: 0.75rem;">Student</div>
        </div>
        <span class="material-symbols-rounded" style="color: var(--sidebar-text); font-size: 18px;">unfold_more</span>
        
        <div class="profile-dropdown" id="profileDropdown">
            <a href="<?php echo $root_path; ?>profile.php" class="profile-dropdown-item">
                <span class="material-symbols-rounded">person</span> My Profile
            </a>
            <a href="#" id="dropdownLogout" class="profile-dropdown-item" style="color: var(--danger);">
                <span class="material-symbols-rounded">logout</span> Logout
            </a>
        </div>
    </div>
</aside>

<div class="modal-overlay" id="logoutModal">
    <div class="modal-card">
        <div class="modal-icon-wrapper">
            <span class="material-symbols-rounded">logout</span>
        </div>
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to log out of your session?</p>
        <div class="modal-actions">
            <button type="button" class="modal-btn-cancel" id="cancelLogout">Cancel</button>
            <a href="<?php echo $root_path;?>logout.php" class="modal-btn-logout">Logout</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logoutBtn');
    const dropdownLogout = document.getElementById('dropdownLogout');
    const logoutModal = document.getElementById('logoutModal');
    const cancelLogout = document.getElementById('cancelLogout');
    
    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('active');
        });

        document.addEventListener('click', function(e) {
            if (!profileDropdown.contains(e.target) && !profileToggle.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });
    }

    function openLogoutModal(e) {
        e.preventDefault();
        if(profileDropdown) profileDropdown.classList.remove('active');
        logoutModal.classList.add('active');
    }

    if (logoutBtn) logoutBtn.addEventListener('click', openLogoutModal);
    if (dropdownLogout) dropdownLogout.addEventListener('click', openLogoutModal);

    if (cancelLogout && logoutModal) {
        cancelLogout.addEventListener('click', function() {
            logoutModal.classList.remove('active');
        });

        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                logoutModal.classList.remove('active');
            }
        });
    }
});
</script>