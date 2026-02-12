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

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is admin
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$user = $stmt->fetch();

if (!$user || !$user['is_admin']) {
    header("Location: user_page.php");
    exit();
}

// Handle student actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_student'])) {
        $studentId = $_POST['student_id'];
        try {
            $pdo->beginTransaction();
            
            // First delete from user_courses
            $stmt = $pdo->prepare("DELETE FROM user_courses WHERE user_email = (SELECT email FROM users WHERE id = ?)");
            $stmt->execute([$studentId]);
            
            // Then delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
            $stmt->execute([$studentId]);
            
            $pdo->commit();
            $success = "Student account deleted successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error deleting student: " . $e->getMessage();
        }
    }
}

// Get all students (non-admin users)
$students = $pdo->query("
    SELECT u.id, u.name, u.email, u.created_at, 
           COUNT(uc.id) as course_count
    FROM users u
    LEFT JOIN user_courses uc ON u.email = uc.user_email
    WHERE u.is_admin = 0
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dance Hub - Manage Students</title>
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

        /* Container and Sidebar */
        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--dark), var(--secondary));
            padding: 1.5rem;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            overflow-y: auto;
            flex-shrink: 0;
            position: fixed;
            top: 70px;
            bottom: 0;
            left: 0;
            z-index: 90;
        }

        .sidebar.collapsed {
            width: 0;
            padding: 0;
            overflow: hidden;
        }

        .sidebar-nav ul {
            list-style: none;
            margin-top: 1rem;
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
            white-space: nowrap;
        }

        .sidebar-nav a::before {
            content: attr(data-icon);
            font-size: 1.2rem;
            margin-right: 0.75rem;
            transition: all 0.3s ease;
        }

        .sidebar-nav a:hover, 
        .sidebar-nav a.active {
            background-color: rgba(255,255,255,0.15);
            color: white;
        }

        .sidebar-nav a.active {
            background-color: rgba(255,255,255,0.25);
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            background-color: #f8fafc;
            transition: all 0.3s ease;
            margin-left: 250px;
            width: calc(100% - 250px);
            margin-top: 70px;
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        /* Dashboard Sections */
        .dashboard-section {
            background: white;
            padding: 1.75rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.03);
            border-left: 4px solid var(--primary);
        }

        h1 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        h2 {
            color: var(--dark);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
            font-weight: 600;
            font-size: 1.4rem;
        }

        /* Student Table */
        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .students-table th,
        .students-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .students-table th {
            background-color: #eff6ff;
            color: var(--dark);
            font-weight: 600;
        }

        .students-table tr:last-child td {
            border-bottom: none;
        }

        .students-table tr:hover {
            background-color: #f8fafc;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 0.75rem;
        }

        .student-info {
            display: flex;
            align-items: center;
        }

        .student-name {
            font-weight: 500;
            color: var(--dark);
        }

        .student-email {
            font-size: 0.85rem;
            color: #64748b;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-active {
            background-color: #ecfdf5;
            color: #065f46;
        }

        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .course-count {
            font-weight: 500;
            color: var(--dark);
        }

        .joined-date {
            color: #64748b;
            font-size: 0.85rem;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--error), #c1121f);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c1121f, #a4161a);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
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

        .btn-group {
            display: flex;
            gap: 0.5rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: #ecfdf5;
            border-color: var(--success);
            color: #065f46;
        }

        .alert-error {
            background-color: #fee2e2;
            border-color: var(--error);
            color: #991b1b;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 99;
                height: calc(100vh - 70px);
            }
        
            .main-content {
                width: 100%;
                margin-left: 0;
            }
            
            .students-table {
                display: block;
                overflow-x: auto;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 1rem;
            }
            
            .main-content {
                padding: 1rem;
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
                    <li><a href="manage_students.php" class="active" data-icon="👥">Manage Students</a></li>
                    <li><a href="create_new_lesson.php" data-icon="➕">Create New Lesson</a></li>
                    <li><a href="admin_settings.php" data-icon="⚙️">Settings</a></li>
                    <li><a href="logout.php" data-icon="🚪">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content" id="mainContent">
            <section class="dashboard-section">
                <h1>Manage Students</h1>
                <p>View and manage all student accounts in the Dance Hub system.</p>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Courses</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <img src="https://via.placeholder.com/40/1e40af/ffffff?text=<?= strtoupper(substr($student['name'], 0, 1)); ?>" 
                                             alt="Student avatar" class="student-avatar">
                                        <div>
                                            <div class="student-name"><?= htmlspecialchars($student['name']) ?></div>
                                            <div class="student-email"><?= htmlspecialchars($student['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="course-count"><?= $student['course_count'] ?></span> enrolled
                                </td>
                                <td>
                                    <span class="joined-date"><?= date('M j, Y', strtotime($student['created_at'])) ?></span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this student account? All their course progress will be lost.');">
                                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                            <button type="submit" name="delete_student" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                        <a href="view_student.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-outline">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 2rem; color: #64748b;">
                                    No student accounts found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <section class="dashboard-section">
                <h2>Student Statistics</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <h3 style="font-size: 1rem; color: #64748b; margin-bottom: 0.5rem;">Total Students</h3>
                        <p style="font-size: 2rem; font-weight: bold; color: var(--primary);"><?= count($students) ?></p>
                    </div>
                    
                    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <h3 style="font-size: 1rem; color: #64748b; margin-bottom: 0.5rem;">Avg. Courses/Student</h3>
                        <p style="font-size: 2rem; font-weight: bold; color: var(--secondary);">
                            <?= count($students) > 0 ? round(array_sum(array_column($students, 'course_count')) / count($students), 1) : 0 ?>
                        </p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Toggle sidebar
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Confirm before deleting student
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm(this.getAttribute('data-confirm') || 'Are you sure?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>