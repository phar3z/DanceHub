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
$emailNotifications = true;
$pushNotifications = false;
$language = 'en';

// Admin-specific settings
$maintenanceMode = false;
$registrationEnabled = true;
$analyticsEnabled = true;
$emailVerificationRequired = false;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if user is admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['is_admin']) {
        header("Location: user_page.php");
        exit();
    }
    
    // Get or create user settings
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userSettings) {
        // Create default settings for user
        $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, theme, notifications_enabled, email_notifications, push_notifications, language) VALUES (?, 'light', 1, 1, 0, 'en')");
        $stmt->execute([$user['id']]);
        
        // Fetch the newly created settings
        $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get admin settings from database
    $result = $pdo->query("SHOW TABLES LIKE 'admin_settings'");
    if ($result->rowCount() == 0) {
        // Create admin_settings table if it doesn't exist
        $pdo->exec("
            CREATE TABLE `admin_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `maintenance_mode` tinyint(1) DEFAULT 0,
                `registration_enabled` tinyint(1) DEFAULT 1,
                `analytics_enabled` tinyint(1) DEFAULT 1,
                `email_verification_required` tinyint(1) DEFAULT 0,
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            
            INSERT INTO `admin_settings` VALUES (1, 0, 1, 1, 0, CURRENT_TIMESTAMP);
        ");
    }
    
    // Get admin settings
    $stmt = $pdo->query("SELECT * FROM admin_settings LIMIT 1");
    $adminSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Set current values
    $currentTheme = $userSettings['theme'] ?? 'light';
    $notificationsEnabled = $userSettings['notifications_enabled'] ?? true;
    $emailNotifications = $userSettings['email_notifications'] ?? true;
    $pushNotifications = $userSettings['push_notifications'] ?? false;
    $language = $userSettings['language'] ?? 'en';
    
    // Admin settings
    $maintenanceMode = $adminSettings['maintenance_mode'] ?? false;
    $registrationEnabled = $adminSettings['registration_enabled'] ?? true;
    $analyticsEnabled = $adminSettings['analytics_enabled'] ?? true;
    $emailVerificationRequired = $adminSettings['email_verification_required'] ?? false;
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Personal settings
        $theme = $_POST['theme'] ?? 'light';
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        $emailNotifs = isset($_POST['email_notifications']) ? 1 : 0;
        $pushNotifs = isset($_POST['push_notifications']) ? 1 : 0;
        $newLanguage = $_POST['language'] ?? 'en';
        
        // Admin settings
        $maintenance = isset($_POST['maintenance_mode']) ? 1 : 0;
        $registration = isset($_POST['registration_enabled']) ? 1 : 0;
        $analytics = isset($_POST['analytics_enabled']) ? 1 : 0;
        $emailVerify = isset($_POST['email_verification_required']) ? 1 : 0;
        
        // Validate theme
        if (!in_array($theme, ['light', 'dark', 'blue'])) {
            $theme = 'light';
        }
        
        // Validate language
        if (!in_array($newLanguage, ['en', 'es', 'fr', 'de'])) {
            $newLanguage = 'en';
        }
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update user settings
            $stmt = $pdo->prepare("UPDATE user_settings SET 
                theme = ?, 
                notifications_enabled = ?, 
                email_notifications = ?, 
                push_notifications = ?, 
                language = ?, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE user_id = ?");
            $stmt->execute([
                $theme, 
                $notifications, 
                $emailNotifs, 
                $pushNotifs, 
                $newLanguage, 
                $user['id']
            ]);
            
            // Update admin settings
            $stmt = $pdo->prepare("UPDATE admin_settings SET 
                maintenance_mode = ?, 
                registration_enabled = ?, 
                analytics_enabled = ?, 
                email_verification_required = ?, 
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = 1");
            $stmt->execute([
                $maintenance, 
                $registration, 
                $analytics, 
                $emailVerify
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $settingsSuccess = "Settings updated successfully!";
            
            // Update current values
            $currentTheme = $theme;
            $notificationsEnabled = $notifications;
            $emailNotifications = $emailNotifs;
            $pushNotifications = $pushNotifs;
            $language = $newLanguage;
            $maintenanceMode = $maintenance;
            $registrationEnabled = $registration;
            $analyticsEnabled = $analytics;
            $emailVerificationRequired = $emailVerify;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $settingsError = "Failed to update settings: " . $e->getMessage();
        }
    }
    
} catch(PDOException $e) {
    $settingsError = "Database error: " . $e->getMessage();
    error_log("Admin settings page database error: " . $e->getMessage());
} catch(Exception $e) {
    $settingsError = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dance Hub - Admin Settings</title>
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
            position: fixed;
            width: 100%;
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
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--dark), var(--secondary));
            padding: 1.5rem;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 90;
            top: 70px; /* Height of the header */
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
           margin-left: 250px; /* Same as sidebar width */
           margin-top: 70px; /* Height of the header */
           min-height: calc(100vh - 70px);
        }

        .main-content.expanded {
            margin-left: 0;
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

        .setting-description {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.25rem;
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

        select.form-control {
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            background-color: #f9fafb;
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

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .btn-danger:hover {
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

        /* Warning box for maintenance mode */
        .warning-box {
            background-color: rgba(234, 179, 8, 0.1);
            border-left: 4px solid #d97706;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
        }

        .warning-box i {
            color: #d97706;
            margin-right: 0.75rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
           .sidebar {
               position: fixed;
               z-index: 99;
               height: 100vh;
               top: 70px;
           }
    
           .main-content {
               width: 100%;
               margin-left: 0;
               margin-top: 70px;
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
            <span>Hello, <?= htmlspecialchars($user['name'] ?? 'Admin'); ?>!</span>
            <img src="https://via.placeholder.com/40/1e40af/ffffff?text=<?= strtoupper(substr($user['name'] ?? 'A', 0, 1)); ?>" alt="Admin profile">
        </div>
    </header>
    
    <div class="container">
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin_page.php" data-icon="📊">Dashboard</a></li>
                    <li><a href="manage_courses.php" data-icon="🎓">Manage Courses</a></li>
                    <li><a href="manage_students.php" data-icon="👥">Manage Students</a></li>
                    <li><a href="create_new_lesson.php" data-icon="➕">Create New Lesson</a></li>
                    <li><a href="admin_settings.php" class="active" data-icon="⚙️">Settings</a></li>
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
            
            <?php if ($maintenanceMode): ?>
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><strong>Maintenance mode is currently enabled.</strong> Regular users will see a maintenance page and won't be able to access the site.</span>
                </div>
            <?php endif; ?>
            
            <div class="settings-section">
                <h1>Admin Settings</h1>
                
                <form method="post">
                    <!-- Personal Settings -->
                    <div class="settings-group">
                        <h2>Personal Preferences</h2>
                        
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
                        
                        <div class="setting-item">
                            <div class="setting-label">
                                Language
                                <div class="setting-description">Interface language for your admin panel</div>
                            </div>
                            <div class="setting-control">
                                <select name="language" class="form-control">
                                    <option value="en" <?= $language === 'en' ? 'selected' : '' ?>>English</option>
                                    <option value="es" <?= $language === 'es' ? 'selected' : '' ?>>Español</option>
                                    <option value="fr" <?= $language === 'fr' ? 'selected' : '' ?>>Français</option>
                                    <option value="de" <?= $language === 'de' ? 'selected' : '' ?>>Deutsch</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notification Settings -->
                    <div class="settings-group">
                        <h2>Notification Preferences</h2>
                        
                        <div class="setting-item">
                            <div class="setting-label">
                                Enable Notifications
                                <div class="setting-description">Receive system notifications</div>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notifications" <?= $notificationsEnabled ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-label">
                                Email Notifications
                                <div class="setting-description">Receive notifications via email</div>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_notifications" <?= $emailNotifications ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-label">
                                Push Notifications
                                <div class="setting-description">Receive browser push notifications</div>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="push_notifications" <?= $pushNotifications ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Settings -->
                    <div class="settings-group">
                        <h2>System Configuration</h2>
                        
                        <div class="setting-item">
                            <div class="setting-label">
                                Maintenance Mode
                                <div class="setting-description">When enabled, only admins can access the site</div>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="maintenance_mode" <?= $maintenanceMode ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-label">
                                New Registrations
                                <div class="setting-description">Allow new users to register accounts</div>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="registration_enabled" <?= $registrationEnabled ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-label">
                                Email Verification
                                <div class="setting-description">Require email verification for new accounts</div>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_verification_required" <?= $emailVerificationRequired ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-label">
                                Analytics Tracking
                                <div class="setting-description">Enable usage analytics and tracking</div>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="analytics_enabled" <?= $analyticsEnabled ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    
                    <?php if ($maintenanceMode): ?>
                        <button type="button" class="btn btn-danger" onclick="confirmDisableMaintenance()" style="margin-left: 1rem;">
                            Disable Maintenance Mode
                        </button>
                    <?php endif; ?>
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

        // Confirm maintenance mode disable
        function confirmDisableMaintenance() {
            if (confirm('Are you sure you want to disable maintenance mode? The site will be immediately accessible to all users.')) {
                document.querySelector('input[name="maintenance_mode"]').checked = false;
                document.querySelector('form').submit();
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