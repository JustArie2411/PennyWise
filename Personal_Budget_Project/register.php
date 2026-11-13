<?php
require_once 'includes/connect.php';
require_once 'includes/config.php';
require_once 'includes/csrf.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            $error = "Username or email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (name, username, email, password) VALUES (?, ?, ?, ?)");
            if ($insert->execute([$name, $username, $email, $hashed_password])) {
                $success = "Registration successful! You can now log in.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
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
    <title>Sign Up - Personal Budget System</title>
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
            padding-bottom: 40px;
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

        .register-card {
            position: relative;
            z-index: 2;
            background: #FFFFFF;
            width: 100%;
            max-width: 460px;
            padding: 36px 36px 32px 36px;
            border-radius: 24px;
            margin: auto 20px;
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
        }

        .register-header {
            text-align: center;
            margin-bottom: 22px;
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
        }

        .register-header h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 4px;
        }

        .register-header p {
            font-size: 0.85rem;
            color: #64748B;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.825rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
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
        }

        .form-control {
            width: 100%;
            padding: 11px 14px 11px 42px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            font-size: 0.9rem;
            color: #0F172A;
            outline: none;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 20px;
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            background: #4F46E5;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 6px;
        }

        .signin-prompt {
            text-align: center;
            margin-top: 18px;
            font-size: 0.85rem;
            color: #64748B;
        }

        .signin-prompt a {
            color: #4F46E5;
            font-weight: 600;
            text-decoration: none;
        }

        .secure-footer {
            margin-top: 18px;
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

    <div class="register-card">
        <a href="index.php" class="close-btn">
            <span class="material-symbols-rounded">close</span>
        </a>

        <div class="register-header">
            <div class="brand-badge">
                <span class="material-symbols-rounded">account_balance_wallet</span>
            </div>
            <div class="system-subtitle">Personal Budget System</div>
            <h2>Create Your Account</h2>
            <p>Sign up to start tracking your finances.</p>
        </div>

        <?php if(!empty($error)): ?>
            <div style="background: #FEE2E2; color: #DC2626; padding: 10px; border-radius: 10px; margin-bottom: 16px; font-size: 0.825rem; font-weight: 500; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($success)): ?>
            <div style="background: #EEF2FF; color: #4F46E5; padding: 10px; border-radius: 10px; margin-bottom: 16px; font-size: 0.825rem; font-weight: 500; text-align: center;">
                <?php echo $success; ?> <a href="login.php" style="color: #4338CA; text-decoration: underline; font-weight: 700;">Log in here</a>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label class="form-label">
                    <span class="material-symbols-rounded" style="font-size:18px; color:#4F46E5;">person</span> Full Name
                </label>
                <div class="input-wrapper">
                    <span class="material-symbols-rounded input-icon">badge</span>
                    <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <span class="material-symbols-rounded" style="font-size:18px; color:#4F46E5;">account_circle</span> Username
                </label>
                <div class="input-wrapper">
                    <span class="material-symbols-rounded input-icon">alternate_email</span>
                    <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <span class="material-symbols-rounded" style="font-size:18px; color:#4F46E5;">mail</span> Email Address
                </label>
                <div class="input-wrapper">
                    <span class="material-symbols-rounded input-icon">mail</span>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email address" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <span class="material-symbols-rounded" style="font-size:18px; color:#4F46E5;">lock</span> Password
                </label>
                <div class="input-wrapper">
                    <span class="material-symbols-rounded input-icon">key</span>
                    <input type="password" id="passwordInput" name="password" class="form-control" placeholder="Create a secure password" required>
                    <span class="material-symbols-rounded toggle-password" id="togglePassword">visibility</span>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                Register <span class="material-symbols-rounded" style="font-size: 20px;">arrow_forward</span>
            </button>
        </form>

        <div class="signin-prompt">
            Already have an account? <a href="login.php">Sign In</a>
        </div>

        <div class="secure-footer">
            <span class="material-symbols-rounded" style="font-size:16px; color:#4F46E5;">verified_user</span> Your information is securely protected.
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