<?php
require_once 'includes/config.php';
require_once 'includes/connect.php';

$user_count = 0;
try {
    $u_stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $u_stmt->fetchColumn() ?: 0;
} catch (Exception $e) {}

$is_logged_in = isset($_SESSION['user_id']);
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
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #F8FAFC;
            color: #0F172A;
            overflow-x: hidden;
        }

        .landing-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 5%;
            max-width: 1200px;
            margin: 0 auto;
            background: #FFFFFF;
            border-bottom: 1px solid #E2E8F0;
            border-radius: 0 0 16px 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .landing-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.25rem;
            color: #0F172A;
            text-decoration: none;
        }

        .landing-brand .brand-icon {
            width: 36px;
            height: 36px;
            background: #EEF2FF;
            color: #4F46E5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-links {
            display: flex;
            gap: 24px;
            list-style: none;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #64748B;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .nav-links a:hover, .nav-links a.active {
            color: #4F46E5;
            font-weight: 600;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-login {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #0F172A;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 10px;
            transition: background 0.2s ease;
        }

        .btn-login:hover {
            background: #F1F5F9;
        }

        .btn-signup {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #4F46E5;
            color: #FFFFFF;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 9px 18px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
            transition: all 0.2s ease;
        }

        .btn-signup:hover {
            background: #4338CA;
            transform: translateY(-1px);
        }

        .hamburger-btn {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            color: #0F172A;
            font-size: 28px;
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
            background: rgba(15, 23, 42, 0.4);
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
            right: -300px;
            width: 300px;
            height: 100%;
            background: #FFFFFF;
            z-index: 1000;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            padding: 24px;
            transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .mobile-menu-drawer.active {
            right: 0;
        }

        .mobile-drawer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 16px;
            border-bottom: 1px solid #E2E8F0;
        }

        .mobile-drawer-close {
            background: #F1F5F9;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748B;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .mobile-drawer-close:hover {
            background: #E2E8F0;
            color: #0F172A;
        }

        .mobile-nav-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 30px;
        }

        .mobile-nav-links a {
            text-decoration: none;
            color: #334155;
            font-size: 1.05rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .mobile-nav-links a:hover, .mobile-nav-links a.active {
            background: #EEF2FF;
            color: #4F46E5;
        }

        .mobile-nav-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: auto;
        }

        .mobile-nav-buttons a {
            width: 100%;
            justify-content: center;
            padding: 12px;
            font-size: 0.95rem;
        }

        .hero-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .hero-box {
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
            border: 1px solid #C7D2FE;
            border-radius: 20px;
            padding: 40px 50px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 30px;
            align-items: center;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.05);
            animation: fadeInUp 0.4s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FFFFFF;
            color: #4F46E5;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.775rem;
            font-weight: 600;
            margin-bottom: 16px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }

        .hero-content h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.4rem;
            font-weight: 700;
            color: #0F172A;
            line-height: 1.2;
            margin-bottom: 14px;
        }

        .hero-content h1 span {
            color: #4F46E5;
        }

        .hero-content p {
            color: #475569;
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
        }

        .btn-primary-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #4F46E5;
            color: #FFFFFF;
            text-decoration: none;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            transition: all 0.2s ease;
        }

        .btn-primary-action:hover {
            background: #4338CA;
            transform: translateY(-2px);
        }

        .btn-outline-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FFFFFF;
            color: #334155;
            text-decoration: none;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid #CBD5E1;
            transition: all 0.2s ease;
        }

        .btn-outline-action:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
        }

        .hero-image-wrapper {
            width: 100%;
            height: 280px;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.1);
            background: #FFFFFF;
        }

        .hero-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .stats-section {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            text-align: center;
        }

        .stat-box {
            background: #FFFFFF;
            padding: 24px;
            border-radius: 14px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s ease;
        }

        .stat-box:hover {
            transform: translateY(-2px);
            border-color: #4F46E5;
        }

        .stat-box span.material-symbols-rounded {
            font-size: 28px;
            color: #4F46E5;
            margin-bottom: 6px;
        }

        .stat-box h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 2px;
        }

        .stat-box p {
            color: #64748B;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .about-section {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .about-box {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            padding: 45px 50px;
            display: grid;
            grid-template-columns: 1fr 1.1fr;
            gap: 40px;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .about-content h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 14px;
        }

        .about-content h2 span {
            color: #4F46E5;
        }

        .about-content p {
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .about-features-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .about-feature-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .about-feature-row span.material-symbols-rounded {
            background: #EEF2FF;
            color: #4F46E5;
            padding: 6px;
            border-radius: 8px;
            font-size: 20px;
        }

        .about-feature-row div h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #0F172A;
            margin-bottom: 2px;
        }

        .about-feature-row div p {
            font-size: 0.85rem;
            color: #64748B;
            margin-bottom: 0;
        }

        .about-visual {
            background: linear-gradient(135deg, #F8FAFC 0%, #EEF2FF 100%);
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
        }

        .about-visual span.material-symbols-rounded {
            font-size: 64px;
            color: #4F46E5;
            margin-bottom: 16px;
        }

        .about-visual h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 8px;
        }

        .about-visual p {
            font-size: 0.875rem;
            color: #64748B;
            line-height: 1.5;
        }

        .features-container {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 20px;
            text-align: center;
        }

        .features-container h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 8px;
        }

        .features-container p.section-sub {
            color: #64748B;
            font-size: 0.95rem;
            margin-bottom: 30px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            text-align: left;
        }

        .feature-item {
            background: #FFFFFF;
            padding: 24px;
            border-radius: 14px;
            border: 1px solid #E2E8F0;
            transition: all 0.2s ease;
        }

        .feature-item:hover {
            border-color: #4F46E5;
            box-shadow: 0 8px 16px rgba(0,0,0,0.04);
            transform: translateY(-2px);
        }

        .feature-icon-box {
            width: 42px;
            height: 42px;
            background: #EEF2FF;
            color: #4F46E5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }

        .feature-item h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 6px;
        }

        .feature-item p {
            color: #64748B;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .landing-footer {
            background: #FFFFFF;
            border-top: 1px solid #E2E8F0;
            text-align: center;
            padding: 24px;
            color: #64748B;
            font-size: 0.85rem;
            margin-top: 60px;
        }

        @media (max-width: 900px) {
            .nav-links, .nav-buttons {
                display: none;
            }
            .hamburger-btn {
                display: flex;
            }
            .hero-box, .about-box {
                grid-template-columns: 1fr;
                padding: 24px;
            }
            .stats-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <nav class="landing-nav">
        <a href="index.php" class="landing-brand">
            <div class="brand-icon">
                <span class="material-symbols-rounded">account_balance_wallet</span>
            </div>
            PennyWise
        </a>
        <ul class="nav-links">
            <li><a href="index.php" class="active">Home</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#features">Features</a></li>
        </ul>
        <div class="nav-buttons">
            <a href="login.php" class="btn-login">
                <span class="material-symbols-rounded">login</span> Login
            </a>
            <a href="register.php" class="btn-signup">
                <span class="material-symbols-rounded">person_add</span> Register
            </a>
        </div>

        <button class="hamburger-btn" id="hamburgerToggle" aria-label="Toggle navigation menu">
            <span class="material-symbols-rounded" style="font-size: 28px;">menu</span>
        </button>
    </nav>

    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    <div class="mobile-menu-drawer" id="mobileMenuDrawer">
        <div class="mobile-drawer-header">
            <a href="index.php" class="landing-brand" style="font-size: 1.1rem;">
                <div class="brand-icon" style="width: 32px; height: 32px;">
                    <span class="material-symbols-rounded" style="font-size: 20px;">account_balance_wallet</span>
                </div>
                PennyWise
            </a>
            <button class="mobile-drawer-close" id="hamburgerClose" aria-label="Close navigation menu">
                <span class="material-symbols-rounded" style="font-size: 20px;">close</span>
            </button>
        </div>

        <ul class="mobile-nav-links">
            <li><a href="index.php"><span class="material-symbols-rounded">home</span> Home</a></li>
            <li><a href="#about"><span class="material-symbols-rounded">info</span> About</a></li>
            <li><a href="#features"><span class="material-symbols-rounded">star</span> Features</a></li>
        </ul>

        <div class="mobile-nav-buttons">
            <a href="login.php" class="btn-outline-action" style="justify-content: center; border-color: #E2E8F0;">
                <span class="material-symbols-rounded">login</span> Login
            </a>
            <a href="register.php" class="btn-signup">
                <span class="material-symbols-rounded">person_add</span> Register
            </a>
        </div>
    </div>

    <div class="hero-container">
        <div class="hero-box">
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="material-symbols-rounded" style="font-size: 16px;">verified</span> Smart Financial & Budget Platform
                </div>
                <h1>Expand Your Wealth with <span>PennyWise System</span></h1>
                <p>
                    Track income and expenses, monitor monthly category spending limits, and achieve your financial targets seamlessly in one place. Built for modern students and budget planners.
                </p>
                <div class="hero-actions">
                    <a href="register.php" class="btn-primary-action">
                        Get Started <span class="material-symbols-rounded">arrow_forward</span>
                    </a>
                    <a href="login.php" class="btn-outline-action">Sign In</a>
                </div>
            </div>
            <div class="hero-image-wrapper">
                <img src="assets/css/budget_bg.jpg" alt="Budget Management Overview">
            </div>
        </div>
    </div>

    <div class="stats-section">
        <div class="stat-box">
            <span class="material-symbols-rounded">group</span>
            <h2><?php echo $user_count; ?></h2>
            <p>Active Platform Members</p>
        </div>
        <div class="stat-box">
            <span class="material-symbols-rounded">security</span>
            <h2>100%</h2>
            <p>Private & Secure Ledger</p>
        </div>
    </div>

    <div class="about-section" id="about">
        <div class="about-box">
            <div class="about-content">
                <h2>About <span>PennyWise</span></h2>
                <p>
                    PennyWise is an intuitive personal finance and wealth management platform designed to help individuals take absolute control of their financial lives[cite: 1]. Whether you are managing daily student allowances or planning long-term savings, our platform brings clarity to your cash flow.
                </p>
                <div class="about-features-list">
                    <div class="about-feature-row">
                        <span class="material-symbols-rounded">bolt</span>
                        <div>
                            <h4>Real-Time Tracking</h4>
                            <p>Instantly log income and expenses to keep your current balance accurate and up-to-date.</p>
                        </div>
                    </div>
                    <div class="about-feature-row">
                        <span class="material-symbols-rounded">verified_user</span>
                        <div>
                            <h4>Secure & Private</h4>
                            <p>Your financial records are protected with robust encryption, secure session tokens, and strict data privacy.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="about-visual">
                <span class="material-symbols-rounded">track_changes</span>
                <h3>Empowering Financial Freedom</h3>
                <p>Build disciplined spending habits, avoid overspending with custom category limits, and watch your savings goals grow effortlessly.</p>
            </div>
        </div>
    </div>

    <div class="features-container" id="features">
        <h2>Explore System Features</h2>
        <p class="section-sub">Discover powerful tools curated to help you manage your financial lifestyle.</p>
        
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon-box">
                    <span class="material-symbols-rounded">pie_chart</span>
                </div>
                <h3>Budget Limits</h3>
                <p>Set custom monthly threshold limits per expense category and monitor your cash flow in real-time.</p>
            </div>

            <div class="feature-item">
                <div class="feature-icon-box">
                    <span class="material-symbols-rounded">savings</span>
                </div>
                <h3>Savings Milestone Goals</h3>
                <p>Create target savings funds and contribute incrementally with live visual progress bars.</p>
            </div>

            <div class="feature-item">
                <div class="feature-icon-box">
                    <span class="material-symbols-rounded">bar_chart</span>
                </div>
                <h3>Interactive Analytics</h3>
                <p>Gain deep insight into your financial habits using dynamic Chart.js doughnut and trend line reports.</p>
            </div>
        </div>
    </div>

    <footer class="landing-footer">
        &copy; <?php echo date('Y'); ?> PennyWise — Personal Budget Management System. All rights reserved.
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerToggle = document.getElementById('hamburgerToggle');
            const hamburgerClose = document.getElementById('hamburgerClose');
            const mobileMenuDrawer = document.getElementById('mobileMenuDrawer');
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
            const mobileLinks = document.querySelectorAll('.mobile-nav-links a, .mobile-nav-buttons a');

            function toggleMenu() {
                mobileMenuDrawer.classList.toggle('active');
                mobileMenuOverlay.classList.toggle('active');
                document.body.style.overflow = mobileMenuDrawer.classList.contains('active') ? 'hidden' : '';
            }

            if (hamburgerToggle) hamburgerToggle.addEventListener('click', toggleMenu);
            if (hamburgerClose) hamburgerClose.addEventListener('click', toggleMenu);
            if (mobileMenuOverlay) mobileMenuOverlay.addEventListener('click', toggleMenu);

            // Close drawer when a link is clicked
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (mobileMenuDrawer.classList.contains('active')) {
                        toggleMenu();
                    }
                });
            });
        });
    </script>
</body>
</html>