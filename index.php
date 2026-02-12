<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'dance_hub';
$username = 'root';
$password = '';

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$success = $_SESSION['register_success'] ?? '';
$activeForm = $_SESSION['active_form'] ?? 'login';

// Clear session messages after displaying them
unset($_SESSION['login_error']);
unset($_SESSION['register_error']);
unset($_SESSION['register_success']);
unset($_SESSION['active_form']);

function showError($error) {
    return !empty($error) ? "<div class='message error'><div class='message-icon'><i class='fas fa-exclamation-circle'></i></div><div class='message-content'><p>$error</p></div></div>" : '';
}

function showSuccess($message) {
    return !empty($message) ? "<div class='message success'><div class='message-icon'><i class='fas fa-check-circle'></i></div><div class='message-content'><p>$message</p></div></div>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dance Hub | Traditional Dance Community</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #e63946;
            --primary-light: #f8a5a5;
            --secondary: #457b9d;
            --dark: #1d3557;
            --light: #f1faee;
            --success: #2a9d8f;
            --error: #e63946;
            --warning: #f4a261;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            position: relative;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .form-header {
            padding: 32px 32px 0;
            text-align: center;
        }

        .form-header h1 {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 8px;
            font-weight: 700;
        }

        .form-header h1 span {
            color: var(--primary);
        }

        .form-box {
            padding: 24px 32px 32px;
            display: none;
            animation: fadeIn 0.4s ease forwards;
        }

        .form-box.active {
            display: block;
        }

        h2 {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 24px;
            text-align: center;
            font-weight: 600;
        }

        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-field {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f9fafb;
        }

        .input-field:focus {
            border-color: var(--primary-light);
            background-color: white;
            box-shadow: 0 0 0 4px rgba(230, 57, 70, 0.1);
            outline: none;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 14px;
            color: #9ca3af;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 14px;
            color: #9ca3af;
            cursor: pointer;
        }

        select.input-field {
            padding: 14px 16px;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary), #d62839);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(230, 57, 70, 0.2);
        }

        .btn:active {
            transform: translateY(0);
        }

        .form-footer {
            text-align: center;
            margin-top: 24px;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            animation: slideIn 0.3s ease forwards;
        }

        .message.error {
            background-color: rgba(230, 57, 70, 0.1);
            border-left: 4px solid var(--error);
        }

        .message.success {
            background-color: rgba(42, 157, 143, 0.1);
            border-left: 4px solid var(--success);
        }

        .message-icon {
            margin-right: 12px;
            font-size: 1.2rem;
        }

        .message.error .message-icon {
            color: var(--error);
        }

        .message.success .message-icon {
            color: var(--success);
        }

        .message-content {
            flex: 1;
            font-size: 0.9rem;
            color: #4b5563;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            to { transform: translateX(0); opacity: 1; }
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            .container {
                border-radius: 12px;
            }
            
            .form-box {
                padding: 24px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-header">
            <h1>Dance <span>Hub</span></h1>
            <p>Connect with traditional dance communities worldwide</p>
        </div>

        <!-- Login Form -->
        <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
            <form action="login_register.php" method="post">
                <h2>Login</h2>
                <?= showError($errors['login']); ?>
                <?= showSuccess($success); ?>
                
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" class="input-field" placeholder="Email" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" class="input-field" placeholder="Password" required>
                    <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                </div>

                <button type="submit" name="login" class="btn">Sign In</button>
                
                <div class="form-footer">
                    Don't have an account? <a href="#" onclick="showForm('register-form'); return false;">Register here</a>
                </div>
            </form>
        </div>

        <!-- Register Form -->
        <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
            <form action="login_register.php" method="post">
                <h2>Register</h2>
                <?= showError($errors['register']); ?>
                
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="name" class="input-field" placeholder="Full Name" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" class="input-field" placeholder="Email" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" class="input-field" placeholder="Password" required>
                    <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                </div>

                <div class="input-group">
                    <i class="fas fa-user-tag input-icon"></i>
                    <select name="role" class="input-field" required>
                        <option value="">-- Select Role --</option>
                        <option value="user">Dancer</option>
                        <option value="admin">Instructor/Admin</option>
                    </select>
                </div>

                <button type="submit" name="register" class="btn">Create Account</button>
                
                <div class="form-footer">
                    Already have an account? <a href="#" onclick="showForm('login-form'); return false;">Login here</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showForm(formId) {
            document.querySelectorAll('.form-box').forEach(form => {
                form.classList.remove('active');
            });
            document.getElementById(formId).classList.add('active');
        }

        function togglePassword(icon) {
            const input = icon.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Initialize with correct form
        document.addEventListener('DOMContentLoaded', function() {
            const activeForm = '<?= $activeForm ?>';
            if (activeForm === 'register') {
                showForm('register-form');
            }
        });
    </script>
</body>
</html>