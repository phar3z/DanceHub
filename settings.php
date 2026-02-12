<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'dance_hub';
$username = 'root';
$password = '';

// Initialize variables for notifications
$settingsError = '';
$settingsSuccess = '';
$currentTheme = 'light';
$notificationsEnabled = true;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if users table exists, if not create it
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `email` varchar(255) NOT NULL,
                `password` varchar(255) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
    
    // Get the current user's ID from the database
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // If user doesn't exist, create them
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([
            $_SESSION['name'] ?? 'User',
            $_SESSION['email'],
            'temp_password' // You should hash this properly
        ]);
        $user_id = $pdo->lastInsertId();
        $_SESSION['name'] = $_SESSION['name'] ?? 'User';
    } else {
        $user_id = $user['id'];
        $_SESSION['name'] = $user['name']; // Update session name from database
    }
    
    // Create user_settings table WITHOUT foreign key constraint to avoid issues
    $result = $pdo->query("SHOW TABLES LIKE 'user_settings'");
    if ($result->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE `user_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `theme` enum('light','dark','blue') DEFAULT 'light',
                `notifications_enabled` tinyint(1) DEFAULT 1,
                `email_notifications` tinyint(1) DEFAULT 1,
                `push_notifications` tinyint(1) DEFAULT 0,
                `language` varchar(10) DEFAULT 'en',
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
    
    // Get or create user settings
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userSettings) {
        // Create default settings for user
        $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, theme, notifications_enabled) VALUES (?, 'light', 1)");
        $stmt->execute([$user_id]);
        
        // Fetch the newly created settings
        $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Set current values
    $currentTheme = $userSettings['theme'] ?? 'light';
    $notificationsEnabled = $userSettings['notifications_enabled'] ?? true;
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $theme = $_POST['theme'] ?? 'light';
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        
        // Validate theme
        if (!in_array($theme, ['light', 'dark', 'blue'])) {
            $theme = 'light';
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE user_settings SET theme = ?, notifications_enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $stmt->execute([$theme, $notifications, $user_id]);
            
            $settingsSuccess = "Settings updated successfully!";
            $currentTheme = $theme;
            $notificationsEnabled = $notifications;
            
        } catch (PDOException $e) {
            $settingsError = "Failed to update settings: " . $e->getMessage();
        }
    }
    
} catch(PDOException $e) {
    $settingsError = "Database error: " . $e->getMessage();
    
    // Log the full error for debugging
    error_log("Settings page database error: " . $e->getMessage());
    
} catch(Exception $e) {
    $settingsError = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dance Hub - Settings</title>
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
            transition: all 0.3s ease;
        }

        /* Theme Classes */
        body.theme-dark {
            background: #0f172a;
            color: #e2e8f0;
        }

        body.theme-dark .main-content {
            background-color: #1e293b;
        }

        body.theme-dark .settings-section {
            background: #334155;
            color: #e2e8f0;
        }

        body.theme-dark .setting-label {
            color: #e2e8f0;
        }

        body.theme-dark .settings-group h2 {
            color: #e2e8f0;
            border-bottom-color: #475569;
        }

        body.theme-dark h1 {
            color: #e2e8f0;
        }

        body.theme-dark .setting-item {
            border-bottom-color: #475569;
        }

        body.theme-blue {
            background: #f0f9ff;
            color: #0c4a6e;
        }

        body.theme-blue .main-content {
            background-color: #e0f2fe;
        }

        body.theme-blue .settings-section {
            background: #bae6fd;
            color: #0c4a6e;
        }

        body.theme-blue .setting-label {
            color: #0c4a6e;
        }

        body.theme-blue .settings-group h2 {
            color: #0c4a6e;
            border-bottom-color: #7dd3fc;
        }

        body.theme-blue h1 {
            color: #0c4a6e;
        }

        body.theme-blue .setting-item {
            border-bottom-color: #7dd3fc;
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

        .settings-section {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        h1 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .settings-group {
            margin-bottom: 2rem;
        }

        .settings-group h2 {
            color: var(--dark);
            margin-bottom: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .setting-label {
            font-weight: 500;
            color: var(--dark);
        }

        .setting-control {
            display: flex;
            align-items: center;
        }

        .theme-selector {
            display: flex;
            gap: 0.5rem;
        }

        .theme-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .theme-option:hover {
            transform: scale(1.1);
        }

        .theme-option.selected {
            border-color: var(--primary);
            transform: scale(1.1);
        }

        .theme-light {
            background: #f8fafc;
        }

        .theme-dark {
            background: #1e293b;
        }

        .theme-blue {
            background: #e0f2fe;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--primary);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .btn {
            display: inline-block;
            padding: 0.65rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 1.5rem;
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

        /* Debug info styling */
        .debug-info {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .setting-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
            <img src="https://via.placeholder.com/40/1e40af/ffffff?text=<?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>" alt="User profile">
        </div>
    </header>
    
    <div class="container">
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="user_page.php" data-icon="📊">Dashboard</a></li>
                    <li><a href="my_courses.php" data-icon="🎓">My Courses</a></li>
                    <li><a href="my_profile.php" data-icon="👤">My Profile</a></li>
                    <li><a href="settings.php" class="active" data-icon="⚙️">Settings</a></li>
                    <li><a href="logout.php" data-icon="🚪">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content" id="mainContent">
            <?php if ($settingsError): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($settingsError); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($settingsSuccess): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($settingsSuccess); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="settings-section">
                <h1>Account Settings</h1>
                
                <form method="post">
                    <div class="settings-group">
                        <h2>Appearance</h2>
                        
                        <div class="setting-item">
                            <div class="setting-label">Theme</div>
                            <div class="setting-control">
                                <div class="theme-selector">
                                    <div class="theme-option theme-light <?= $currentTheme === 'light' ? 'selected' : '' ?>" 
                                         data-theme="light" onclick="selectTheme('light')"></div>
                                    <div class="theme-option theme-dark <?= $currentTheme === 'dark' ? 'selected' : '' ?>" 
                                         data-theme="dark" onclick="selectTheme('dark')"></div>
                                    <div class="theme-option theme-blue <?= $currentTheme === 'blue' ? 'selected' : '' ?>" 
                                         data-theme="blue" onclick="selectTheme('blue')"></div>
                                </div>
                                <input type="hidden" name="theme" id="theme-input" value="<?= $currentTheme ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-group">
                        <h2>Notifications</h2>
                        
                        <div class="setting-item">
                            <div class="setting-label">Email Notifications</div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notifications" <?= $notificationsEnabled ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Apply theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Apply saved theme
            const currentTheme = '<?= $currentTheme ?>';
            applyTheme(currentTheme);
            
            // Check for saved sidebar preference
            if (typeof(Storage) !== "undefined") {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    document.getElementById('sidebar').classList.add('collapsed');
                    document.getElementById('mainContent').classList.add('expanded');
                }
            }
        });

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

        // Theme selection
        function selectTheme(theme) {
            document.querySelectorAll('.theme-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelector(`.theme-option[data-theme="${theme}"]`).classList.add('selected');
            document.getElementById('theme-input').value = theme;
            
            // Apply theme immediately for preview
            applyTheme(theme);
        }

        // Apply theme function
        function applyTheme(theme) {
            const body = document.body;
            
            // Remove all theme classes
            body.classList.remove('theme-light', 'theme-dark', 'theme-blue');
            
            // Add selected theme class
            if (theme !== 'light') {
                body.classList.add('theme-' + theme);
            }
            
            // Store theme preference
            if (typeof(Storage) !== "undefined") {
                localStorage.setItem('selectedTheme', theme);
            }
        }

        // Load theme from localStorage if user hasn't saved settings yet
        window.addEventListener('load', function() {
            if (typeof(Storage) !== "undefined") {
                const savedTheme = localStorage.getItem('selectedTheme');
                if (savedTheme && savedTheme !== '<?= $currentTheme ?>') {
                    applyTheme(savedTheme);
                    
                    // Update the theme selector to match
                    document.querySelectorAll('.theme-option').forEach(option => {
                        option.classList.remove('selected');
                    });
                    document.querySelector(`.theme-option[data-theme="${savedTheme}"]`)?.classList.add('selected');
                    document.getElementById('theme-input').value = savedTheme;
                }
            }
        });
    </script>
</body>
</html>