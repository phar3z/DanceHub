<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

// Include avatar helper
require_once 'avatars.php';

// Database configuration
$host = 'localhost';
$dbname = 'dance_hub';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Handle profile update
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        
        // Validation
        if (empty($name) || empty($email)) {
            $_SESSION['profile_error'] = 'All fields are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['profile_error'] = 'Invalid email format';
        } else {
            // Check if email exists (only if changed)
            if ($email !== $_SESSION['email']) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                if ($stmt->rowCount() > 0) {
                    $_SESSION['profile_error'] = 'Email already in use by another account';
                }
            }
            
            if (!isset($_SESSION['profile_error'])) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $_SESSION['user_id']]);
                    
                    // Update session
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                    $_SESSION['profile_success'] = 'Profile updated successfully';
                } catch(PDOException $e) {
                    $_SESSION['profile_error'] = 'Error updating profile: ' . $e->getMessage();
                }
            }
        }
    } 
    elseif (isset($_POST['change_password'])) {
        // Handle password change
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['password_error'] = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['password_error'] = 'New passwords do not match';
        } elseif (strlen($new_password) < 8) {
            $_SESSION['password_error'] = 'Password must be at least 8 characters';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    $_SESSION['password_success'] = 'Password changed successfully';
                } catch(PDOException $e) {
                    $_SESSION['password_error'] = 'Error changing password: ' . $e->getMessage();
                }
            } else {
                $_SESSION['password_error'] = 'Current password is incorrect';
            }
        }
    }
    elseif (isset($_POST['update_avatar'])) {
        // Handle avatar update
        $avatar_id = intval($_POST['avatar_id'] ?? 1);
        
        // Validate avatar ID (1-4)
        if ($avatar_id < 1 || $avatar_id > 4) {
            $_SESSION['profile_error'] = 'Invalid avatar selection';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET avatar_id = ? WHERE id = ?");
                $stmt->execute([$avatar_id, $_SESSION['user_id']]);
                $_SESSION['profile_success'] = 'Profile picture updated successfully';
            } catch(PDOException $e) {
                $_SESSION['profile_error'] = 'Error updating avatar: ' . $e->getMessage();
            }
        }
    }
    
    header("Location: my_profile.php");
    exit();
}

// Get user data
$stmt = $pdo->prepare("SELECT id, name, email, role, created_at, last_login, avatar_id FROM users WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$_SESSION['user_id'] = $userData['id'] ?? null;

// Default avatar if none set
$currentAvatar = $userData['avatar_id'] ?? 1;

// Get avatars from helper function
$avatars = getAvatars();

// Handle messages
$profileError = $_SESSION['profile_error'] ?? '';
$profileSuccess = $_SESSION['profile_success'] ?? '';
$passwordError = $_SESSION['password_error'] ?? '';
$passwordSuccess = $_SESSION['password_success'] ?? '';
unset($_SESSION['profile_error'], $_SESSION['profile_success'], $_SESSION['password_error'], $_SESSION['password_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dance Hub - My Profile</title>
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
            background: #f8fafc;
            color: #334155;
        }

        /* Header Styles */
        header {
            background: linear-gradient(135deg, var(--dark), var(--secondary));
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: #fff;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
        }

        .logo span {
            color: var(--primary-light);
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-menu img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
        }

        /* Main Content */
        .container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--dark), var(--secondary));
            padding: 1.5rem;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            width: 0;
            padding: 0;
            overflow: hidden;
        }

        .sidebar-nav ul {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 0.75rem;
        }

        .sidebar-nav a {
            text-decoration: none;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .sidebar-nav a::before {
            content: attr(data-icon);
            font-size: 1.2rem;
            margin-right: 0.75rem;
        }

        .sidebar-nav a:hover, .sidebar-nav a.active {
            background-color: rgba(255,255,255,0.15);
            color: white;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            padding: 2rem;
            background-color: #f8fafc;
            transition: all 0.3s ease;
        }

        .main-content.expanded {
            width: 100%;
        }

        .profile-section {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-light);
            margin-right: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            border-color: var(--primary);
        }

        .profile-info h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .profile-info p {
            color: #64748b;
        }

        .profile-details {
            margin-top: 2rem;
        }

        .detail-row {
            display: flex;
            padding: 1rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-label {
            width: 200px;
            font-weight: 500;
            color: var(--dark);
        }

        .detail-value {
            flex: 1;
            color: #64748b;
        }

        .btn {
            display: inline-block;
            padding: 0.65rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 1.5rem;
            margin-right: 1rem;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #d62839);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--dark);
        }

        /* Edit Form Styles */
        .edit-form {
            display: none;
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .edit-form h3 {
            color: var(--dark);
            margin-bottom: 1.5rem;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
            outline: none;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        /* Avatar Selection Styles */
        .avatar-selection {
            margin-top: 1.5rem;
        }

        .avatar-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }

        .avatar-option {
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .avatar-option img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .avatar-option:hover img {
            border-color: var(--primary-light);
            transform: scale(1.05);
        }

        .avatar-option.selected img {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.2);
        }

        .avatar-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .avatar-check {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--success);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s ease;
        }

        .avatar-option.selected .avatar-check {
            opacity: 1;
            transform: scale(1);
        }

        /* Notification Styles */
        .notification {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }

        .notification.error {
            background-color: rgba(230, 57, 70, 0.1);
            border-left: 4px solid var(--error);
        }

        .notification.success {
            background-color: rgba(42, 157, 143, 0.1);
            border-left: 4px solid var(--success);
        }

        .notification i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .notification.error i {
            color: var(--error);
        }

        .notification.success i {
            color: var(--success);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .detail-row {
                flex-direction: column;
            }

            .detail-label {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .avatar-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <span>Dance <span>Hub</span></span>
        </div>
        <div class="user-menu">
            <span>Hello, <?= htmlspecialchars($_SESSION['name'] ?? 'User'); ?>!</span>
            <img src="<?= $avatars[$currentAvatar]; ?>" alt="User profile">
        </div>
    </header>
    
    <div class="container">
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="user_page.php" data-icon="📊">Dashboard</a></li>
                    <li><a href="my_courses.php" data-icon="🎓">My Courses</a></li>
                    <li><a href="my_profile.php" class="active" data-icon="👤">My Profile</a></li>
                    <li><a href="settings.php" data-icon="⚙️">Settings</a></li>
                    <li><a href="logout.php" data-icon="🚪">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content" id="mainContent">
            <?php if ($profileError): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($profileError); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($profileSuccess): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($profileSuccess); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($passwordError): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($passwordError); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($passwordSuccess): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($passwordSuccess); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="profile-section">
                <div class="profile-header">
                    <img src="<?= $avatars[$currentAvatar]; ?>" 
                         alt="Profile" class="profile-avatar" onclick="showAvatarSelection()">
                    <div class="profile-info">
                        <h1><?= htmlspecialchars($_SESSION['name'] ?? 'User'); ?></h1>
                        <p>Member since <?= date('F Y', strtotime($userData['created_at'] ?? 'now')) ?></p>
                    </div>
                </div>
                
                <div class="profile-details">
                    <div class="detail-row">
                        <div class="detail-label">Full Name</div>
                        <div class="detail-value"><?= htmlspecialchars($_SESSION['name'] ?? 'Not provided'); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Email Address</div>
                        <div class="detail-value"><?= htmlspecialchars($_SESSION['email'] ?? 'Not provided'); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Account Type</div>
                        <div class="detail-value"><?= htmlspecialchars(ucfirst($userData['role'] ?? 'user')); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Last Login</div>
                        <div class="detail-value"><?= $userData['last_login'] ? date('F j, Y \a\t g:i a', strtotime($userData['last_login'])) : 'Never logged in'; ?></div>
                    </div>
                    
                    <div class="profile-actions">
                        <button onclick="showAvatarSelection()" class="btn btn-secondary">Change Avatar</button>
                        <button onclick="showEditForm()" class="btn btn-outline">Edit Profile</button>
                        <button onclick="showChangePasswordForm()" class="btn btn-primary">Change Password</button>
                    </div>
                </div>
                
                <!-- Avatar Selection Form -->
                <div id="avatar-selection-form" class="edit-form">
                    <h3>Choose Your Avatar</h3>
                    <form method="post" id="avatarForm">
                        <input type="hidden" name="update_avatar" value="1">
                        <div class="avatar-selection">
                            <div class="avatar-grid">
                                <?php foreach ($avatars as $id => $url): ?>
                                    <label class="avatar-option <?= ($id == $currentAvatar) ? 'selected' : ''; ?>" data-avatar="<?= $id; ?>">
                                        <input type="radio" name="avatar_id" value="<?= $id; ?>" <?= ($id == $currentAvatar) ? 'checked' : ''; ?>>
                                        <img src="<?= $url; ?>" alt="Avatar <?= $id; ?>">
                                        <div class="avatar-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Avatar</button>
                            <button type="button" class="btn btn-outline" onclick="cancelAvatarSelection()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Edit Profile Form -->
                <div id="edit-profile-form" class="edit-form">
                    <h3>Edit Profile</h3>
                    <form method="post">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="input-group">
                            <label for="edit-name">Full Name</label>
                            <input type="text" id="edit-name" name="name" value="<?= htmlspecialchars($_SESSION['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="edit-email">Email Address</label>
                            <input type="email" id="edit-email" name="email" value="<?= htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <button type="button" class="btn btn-outline" onclick="cancelEdit()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password Form -->
                <div id="change-password-form" class="edit-form">
                    <h3>Change Password</h3>
                    <form method="post">
                        <input type="hidden" name="change_password" value="1">
                        <div class="input-group">
                            <label for="current-password">Current Password</label>
                            <input type="password" id="current-password" name="current_password" required>
                        </div>
                        
                        <div class="input-group">
                            <label for="new-password">New Password</label>
                            <input type="password" id="new-password" name="new_password" required minlength="8">
                        </div>
                        
                        <div class="input-group">
                            <label for="confirm-password">Confirm New Password</label>
                            <input type="password" id="confirm-password" name="confirm_password" required minlength="8">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                            <button type="button" class="btn btn-outline" onclick="cancelPasswordChange()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle sidebar
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Store preference in localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            if (typeof(Storage) !== "undefined") {
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            }
        });
        
        // Check for saved preference on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof(Storage) !== "undefined") {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    document.getElementById('sidebar').classList.add('collapsed');
                    document.getElementById('mainContent').classList.add('expanded');
                }
            }
        });

        // Avatar Selection Functions
        function showAvatarSelection() {
            hideAllForms();
            document.getElementById('avatar-selection-form').style.display = 'block';
        }

        function cancelAvatarSelection() {
            document.getElementById('avatar-selection-form').style.display = 'none';
        }

        // Avatar selection handling
        document.addEventListener('DOMContentLoaded', function() {
            const avatarOptions = document.querySelectorAll('.avatar-option');
            
            avatarOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    avatarOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Check the radio button
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                });
            });
        });

        // Profile Edit Functions
        function showEditForm() {
            hideAllForms();
            document.getElementById('edit-profile-form').style.display = 'block';
            document.getElementById('edit-name').focus();
        }

        function cancelEdit() {
            document.getElementById('edit-profile-form').style.display = 'none';
        }

        function showChangePasswordForm() {
            hideAllForms();
            document.getElementById('change-password-form').style.display = 'block';
            document.getElementById('current-password').focus();
        }

        function cancelPasswordChange() {
            document.getElementById('change-password-form').style.display = 'none';
        }

        function hideAllForms() {
            document.getElementById('avatar-selection-form').style.display = 'none';
            document.getElementById('edit-profile-form').style.display = 'none';
            document.getElementById('change-password-form').style.display = 'none';
        }

        function validatePasswordForm() {
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (newPassword.length < 8) {
                alert('Password must be at least 8 characters long');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                alert('Passwords do not match');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>