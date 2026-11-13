<?php
require_once 'includes/connect.php';
require_once 'includes/config.php';
require_once 'includes/csrf.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $username_email = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
        $stmt->execute([
            ':username' => $username_email,
            ':email' => $username_email
        ]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];

            $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $log->execute([$user['id'], 'LOGIN', 'User logged in successfully.']);

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Personal Budget System</title>
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
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            overflow-x: hidden;
            background: #0F172A url('assets/css/budget_bg.jpg') no-repeat center center/cover;
        }

        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes floatCard {
            0% {
                transform: translateY(0px);
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            }
            50% {
                transform: translateY(-8px);
                box-shadow: 0 35px 60px -12px rgba(79, 70, 229, 0.25);
            }
            100% {
                transform: translateY(0px);
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            }
        }

        .auth-nav {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1200px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            margin-bottom: 20px;
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.25rem;
            color: #FFFFFF;
            text-decoration: none;
        }

        .auth-brand .brand-icon {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            color: #FFFFFF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .login-card {
            position: relative;
            z-index: 2;
            background: #FFFFFF;
            width: 100%;
            max-width: 440px;
            padding: 36px 36px 32px 36px;
            border-radius: 24px;
            margin: auto 20px;
            animation: popIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards, 
                       floatCard 5s ease-in-out 0.45s infinite;
        }

        @keyframes popIn {
            0% {
                opacity: 0;
                transform: scale(0.90) translateY(20px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            color: #94A3B8;
            text-decoration: none;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .close-btn:hover {
            background: #F1F5F9;
            color: #0F172A;
            transform: rotate(90deg);
        }

        .login-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-badge {
            width: 52px;
            height: 52px;
            background: #EEF2FF;
            color: #4F46E5;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            animation: bounceIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.1s backwards;
        }

        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            60% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); }
        }

        .brand-badge span {
            font-size: 28px;
        }

        .system-subtitle {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748B;
            margin-bottom: 4px;
        }

        .login-header h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 4px;
        }

        .login-header p {
            font-size: 0.875rem;
            color: #64748B;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-label span {
            font-size: 18px;
            color: #4F46E5;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper span.input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 20px;
            transition: color 0.2s ease;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px 12px 42px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.9rem;
            color: #0F172A;
            outline: none;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .form-control:focus {
            background: #FFFFFF;
            border-color: #4F46E5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 20px;
            cursor: pointer;
            user-select: none;
            transition: color 0.2s ease;
        }

        .toggle-password:hover {
            color: #4F46E5;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.825rem;
            margin-bottom: 22px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748B;
            cursor: pointer;
        }

        .forgot-link {
            color: #4F46E5;
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.2s ease;
        }

        .forgot-link:hover {
            text-decoration: underline;
            opacity: 0.85;
        }

        .btn-submit {
            width: 100%;
            background: #4F46E5;
            color: white;
            border: none;
            padding: 13px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-submit:hover {
            background: #4338CA;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
        }

        .signup-prompt {
            text-align: center;
            margin-top: 20px;
            font-size: 0.875rem;
            color: #64748B;
        }

        .signup-prompt a {
            color: #4F46E5;
            font-weight: 600;
            text-decoration: none;
        }

        .secure-footer {
            margin-top: 22px;
            padding: 10px;
            background: #F8FAFC;
            border-radius: 10px;
            text-align: center;
            font-size: 0.75rem;
            color: #64748B;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 1px solid #E2E8F0;
        }

        .secure-footer span {
            font-size: 16px;
            color: #4F46E5;
        }
    </style>
</head>
<body>

    <div class="bg-overlay"></div>

    <nav class="auth-nav">
        <a href="index.php" class="auth-brand">
            <div class="brand-icon">
                <span class="material-symbols-rounded">account_balance_wallet</span>
            </div>
            PennyWise
        </a>
    </nav>

    <div class="login-card">
        <a href="index.php" class="close-btn">
            <span class="material-symbols-rounded">close</span>
        </a>

        <div class="login-header">
            <div class="brand-badge">
                <span class="material-symbols-rounded">account_balance_wallet</span>
            </div>
            <div class="system-subtitle">Personal Budget System</div>
            <h2>Welcome Back</h2>
            <p>Log in to access your personal dashboard.</p>
        </div>

        <?php if(!empty($error)): ?>
            <div style="background: #FEE2E2; color: #DC2626; padding: 12px; border-radius: 10px; margin-bottom: 18px; font-size: 0.85rem; font-weight: 500; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label class="form-label">
                    <span class="material-symbols-rounded">mail</span> Email or Username
                </label>
                <div class="input-wrapper">
                    <span class="material-symbols-rounded input-icon">alternate_email</span>
                    <input type="text" name="username_email" class="form-control" placeholder="Enter your email or username" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <span class="material-symbols-rounded">lock</span> Password
                </label>
                <div class="input-wrapper">
                    <span class="material-symbols-rounded input-icon">key</span>
                    <input type="password" id="passwordInput" name="password" class="form-control" placeholder="Enter your password" required>
                    <span class="material-symbols-rounded toggle-password" id="togglePassword">visibility</span>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" style="accent-color: #4F46E5;"> Remember me
                </label>
                <a href="#" onclick="alert('Please contact your administrator to reset your password.'); return false;" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-submit">
                Log In <span class="material-symbols-rounded" style="font-size: 20px;">arrow_forward</span>
            </button>
        </form>

        <div class="signup-prompt">
            Don't have an account? <a href="register.php">Sign Up</a>
        </div>

        <div class="secure-footer">
            <span class="material-symbols-rounded">verified_user</span> Secure Personal Budget Login
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                togglePassword.textContent = isPassword ? 'visibility_off' : 'visibility';
            });
        }
    });
    </script>
</body>
</html>