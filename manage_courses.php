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

// Function to ensure tables exist
function ensureTablesExist($pdo) {
    try {
        // Create courses table if not exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'courses'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE courses (
                    id VARCHAR(50) PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    description TEXT NOT NULL,
                    image VARCHAR(255),
                    difficulty VARCHAR(50),
                    lessons INT,
                    rating VARCHAR(10),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Insert default courses
            $defaultCourses = [
                [
                    'id' => 'salsa',
                    'title' => 'Salsa Fundamentals',
                    'description' => 'Learn basic steps, turns, and partner work in this vibrant Latin dance style',
                    'image' => 'salsa.jpg',
                    'difficulty' => 'Beginner',
                    'lessons' => 8,
                    'rating' => '4.8★'
                ],
                [
                    'id' => 'kathak',
                    'title' => 'Kathak Essentials',
                    'description' => 'Discover the storytelling art of North Indian classical dance',
                    'image' => 'kathak.jpg',
                    'difficulty' => 'Beginner',
                    'lessons' => 6,
                    'rating' => '4.6★'
                ],
                [
                    'id' => 'flamenco',
                    'title' => 'Flamenco Basics',
                    'description' => 'Master the passionate rhythms of Spanish Flamenco',
                    'image' => 'flamenco.jpg',
                    'difficulty' => 'All Levels',
                    'lessons' => 10,
                    'rating' => '4.9★'
                ],
                [
                    'id' => 'irish',
                    'title' => 'Irish Basics',
                    'description' => 'Proof that joy can be measured in taps per minute!',
                    'image' => 'irish.jpg',
                    'difficulty' => 'All Levels',
                    'lessons' => 10,
                    'rating' => '4.9★'
                ],
                [
                    'id' => 'african',
                    'title' => 'African Dance',
                    'description' => 'Ancestral energy flows through these movements. Dance is our living history.',
                    'image' => 'african.jpg',
                    'difficulty' => 'Intermediate',
                    'lessons' => 12,
                    'rating' => '4.7★'
                ]
            ];
            
            foreach ($defaultCourses as $course) {
                $stmt = $pdo->prepare("
                    INSERT INTO courses (id, title, description, image, difficulty, lessons, rating)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute(array_values($course));
            }
        }
        
        // Create user_courses table if not exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_courses'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE user_courses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_email VARCHAR(255) NOT NULL,
                    course_id VARCHAR(50) NOT NULL,
                    progress INT DEFAULT 0,
                    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_course (user_email, course_id)
                )
            ");
        }
        
        // Create lessons table if not exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'lessons'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE lessons (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    course_id VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    cultural_notes TEXT,
                    video_path VARCHAR(500),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_course_id (course_id)
                )
            ");
        }
    } catch(PDOException $e) {
        die("Error creating tables: " . $e->getMessage());
    }
}

// Ensure tables exist
ensureTablesExist($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_course'])) {
        $courseId = $_POST['course_id'];
        try {
            $pdo->beginTransaction();
            
            // First delete from user_courses
            $stmt = $pdo->prepare("DELETE FROM user_courses WHERE course_id = ?");
            $stmt->execute([$courseId]);
            
            // Then delete from lessons
            $stmt = $pdo->prepare("DELETE FROM lessons WHERE course_id = ?");
            $stmt->execute([$courseId]);
            
            // Finally delete the course
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$courseId]);
            
            $pdo->commit();
            $success = "Course deleted successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error deleting course: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_course'])) {
        $id = strtolower(str_replace(' ', '_', $_POST['id']));
        $title = $_POST['title'];
        $description = $_POST['description'];
        $image = $_POST['image'] ?? 'default.jpg';
        $difficulty = $_POST['difficulty'] ?? 'All Levels';
        $lessons = $_POST['lessons'] ?? 0;
        $rating = $_POST['rating'] ?? '4.5★';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO courses (id, title, description, image, difficulty, lessons, rating)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id, $title, $description, $image, $difficulty, $lessons, $rating]);
            $success = "Course added successfully!";
        } catch (Exception $e) {
            $error = "Error adding course: " . $e->getMessage();
        }
    }
}

// Get all courses
$courses = $pdo->query("SELECT * FROM courses ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dance Hub - Manage Courses</title>
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

        /* Course Grid */
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .course-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }

        .course-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }

        .course-header {
            background: #eff6ff;
            padding: 1rem;
            border-bottom: 1px solid #dbeafe;
        }

        .course-header h3 {
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .course-header .category {
            font-size: 0.85rem;
            color: #64748b;
        }

        .course-body {
            padding: 1.25rem;
        }

        .course-body p {
            color: #475569;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), #d62839);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
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

        .btn-danger {
            background: linear-gradient(135deg, var(--error), #c1121f);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            transition: all 0.3s;
            background-color: #f9fafb;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
            background-color: white;
        }

        textarea.form-control {
            min-height: 120px;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 40px;
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
            
            .course-grid {
                grid-template-columns: 1fr;
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
                    <li><a href="manage_courses.php" class="active" data-icon="🎓">Manage Courses</a></li>
                    <li><a href="manage_students.php" data-icon="👥">Manage Students</a></li>
                    <li><a href="#" data-icon="➕">Create New Lesson</a></li>
                    <li><a href="admin_settings.php" data-icon="⚙️">Settings</a></li>
                    <li><a href="logout.php" data-icon="🚪">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content" id="mainContent">
            <section class="dashboard-section">
                <h1>Manage Traditional Dance Courses</h1>
                
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
                
                <div class="course-grid">
                    <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><?= htmlspecialchars($course['title']); ?></h3>
                            <span class="category"><?= htmlspecialchars($course['id']); ?></span>
                        </div>
                        <div class="course-body">
                            <p><?= htmlspecialchars($course['description']); ?></p>
                            <div class="course-actions">
                                <a href="edit_course.php?id=<?= $course['id']; ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="course_id" value="<?= $course['id']; ?>">
                                    <button type="submit" name="delete_course" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('Are you sure you want to delete this course? All related lessons will also be deleted.');">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($courses)): ?>
                    <div class="course-card">
                        <div class="course-body">
                            <p>No courses found. Add your first course below.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <section class="dashboard-section">
                <h2>Add New Traditional Dance Course</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="id">Course ID *</label>
                        <input type="text" id="id" name="id" class="form-control" placeholder="E.g., 'salsa', 'kathak'" required>
                        <small style="color: #64748b;">Use lowercase letters and underscores only (no spaces)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Course Title *</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="E.g., 'Salsa Fundamentals'" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Course Description *</label>
                        <textarea id="description" name="description" class="form-control" placeholder="Describe what students will learn..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Image File Name</label>
                        <input type="text" id="image" name="image" class="form-control" placeholder="E.g., 'salsa.jpg'">
                    </div>
                    
                    <div class="form-group">
                        <label for="difficulty">Difficulty Level</label>
                        <select id="difficulty" name="difficulty" class="form-control">
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                            <option value="All Levels" selected>All Levels</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="lessons">Number of Lessons</label>
                        <input type="number" id="lessons" name="lessons" class="form-control" value="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="rating">Rating</label>
                        <input type="text" id="rating" name="rating" class="form-control" placeholder="E.g., '4.5★'">
                    </div>
                    
                    <button type="submit" name="add_course" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Course
                    </button>
                </form>
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

        // Simple form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const id = document.getElementById('id').value.trim();
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            
            if (!id || !title || !description) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return;
            }
            
            // Validate ID format
            if (!/^[a-z_]+$/.test(id)) {
                e.preventDefault();
                alert('Course ID must contain only lowercase letters and underscores');
                return;
            }
        });
    </script>
</body>
</html>