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

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get course data
function getCourseDataById($courseId) {
    $courses = [
        'salsa' => [
            'title' => 'Salsa Fundamentals',
            'description' => 'Learn basic steps, turns, and partner work in this vibrant Latin dance style',
            'image' => 'salsa.jpg',
            'difficulty' => 'Beginner',
            'lessons' => 8,
            'rating' => '4.8★',
            'instructor' => 'Maria Rodriguez',
            'duration' => '6 weeks',
            'certificate' => true
        ],
        'kathak' => [
            'title' => 'Kathak Essentials',
            'description' => 'Discover the storytelling art of North Indian classical dance',
            'image' => 'kathak.jpg',
            'difficulty' => 'Beginner',
            'lessons' => 6,
            'rating' => '4.6★',
            'instructor' => 'Priya Sharma',
            'duration' => '4 weeks',
            'certificate' => true
        ],
        'flamenco' => [
            'title' => 'Flamenco Basics',
            'description' => 'Master the passionate rhythms of Spanish Flamenco',
            'image' => 'flamenco.jpg',
            'difficulty' => 'All Levels',
            'lessons' => 10,
            'rating' => '4.9★',
            'instructor' => 'Carmen Vega',
            'duration' => '8 weeks',
            'certificate' => true
        ],
        'irish' => [
            'title' => 'Irish Basics',
            'description' => 'Proof that joy can be measured in taps per minute!',
            'image' => 'irish.jpg',
            'difficulty' => 'All Levels',
            'lessons' => 10,
            'rating' => '4.9★',
            'instructor' => 'Sean O\'Connor',
            'duration' => '7 weeks',
            'certificate' => true
        ],
        'african' => [
            'title' => 'African Dance',
            'description' => 'Ancestral energy flows through these movements. Dance is our living history.',
            'image' => 'african.jpg',
            'difficulty' => 'Intermediate',
            'lessons' => 12,
            'rating' => '4.7★',
            'instructor' => 'Kwame Asante',
            'duration' => '10 weeks',
            'certificate' => true
        ]
    ];
    
    return $courses[$courseId] ?? null;
}

// Function to get user's enrolled courses from database
function getUserCourses($pdo, $userEmail) {
    try {
        $stmt = $pdo->prepare("
            SELECT course_id, progress, enrolled_at
            FROM user_courses 
            WHERE user_email = ?
            ORDER BY enrolled_at DESC
        ");
        $stmt->execute([$userEmail]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching user courses: " . $e->getMessage());
        return [];
    }
}

// Function to update course progress
function updateCourseProgress($pdo, $userEmail, $courseId, $progress) {
    try {
        $stmt = $pdo->prepare("
            UPDATE user_courses 
            SET progress = ? 
            WHERE user_email = ? AND course_id = ?
        ");
        $result = $stmt->execute([$progress, $userEmail, $courseId]);
        return ['success' => $result, 'message' => $result ? 'Progress updated successfully' : 'Failed to update progress'];
    } catch(PDOException $e) {
        error_log("Error updating progress: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Function to remove course from database
function removeCourseFromDatabase($pdo, $userEmail, $courseId) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM user_courses 
            WHERE user_email = ? AND course_id = ?
        ");
        $result = $stmt->execute([$userEmail, $courseId]);
        
        if ($result && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Course removed successfully'];
        } else {
            return ['success' => false, 'message' => 'Course not found or already removed'];
        }
        
    } catch(PDOException $e) {
        error_log("Error removing course: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['update_progress'])) {
        $courseId = $_POST['course_id'];
        $progress = min(100, max(0, intval($_POST['progress'])));
        $result = updateCourseProgress($pdo, $_SESSION['email'], $courseId, $progress);
        $response = $result;
    } 
    elseif (isset($_POST['remove_course'])) {
        $courseId = $_POST['course_id'];
        $result = removeCourseFromDatabase($pdo, $_SESSION['email'], $courseId);
        $response = $result;
    }
    
    // Return JSON response for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}

// Get user's courses from database
$userCourses = getUserCourses($pdo, $_SESSION['email']);
$myCourses = [];

foreach ($userCourses as $userCourse) {
    $courseData = getCourseDataById($userCourse['course_id']);
    if ($courseData) {
        $courseData['progress'] = $userCourse['progress'];
        $courseData['enrolled_at'] = $userCourse['enrolled_at'];
        $myCourses[$userCourse['course_id']] = $courseData;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Dance Hub</title>
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
            --header-height: 70px;
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
            padding-top: var(--header-height);
        }

        /* Fixed Header Styles */
        header {
            background: linear-gradient(135deg, var(--dark), var(--secondary));
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: var(--header-height);
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
            min-height: calc(100vh - var(--header-height));
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--dark), var(--secondary));
            padding: 1.5rem;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            overflow: hidden;
            flex-shrink: 0;
            position: fixed;
            top: var(--header-height);
            bottom: 0;
            left: 0;
            z-index: 100;
        }

        .sidebar.collapsed {
            width: 0;
            padding: 0;
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
            white-space: nowrap;
        }

        .sidebar-nav a::before {
            content: attr(data-icon);
            font-size: 1.2rem;
            margin-right: 0.75rem;
            transition: all 0.3s ease;
        }

        .sidebar-nav a:hover, .sidebar-nav a.active {
            background-color: rgba(255,255,255,0.15);
            color: white;
        }

        .sidebar-btn {
            width: 100%;
            text-decoration: none;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            background: none;
            border: none;
            cursor: pointer;
            text-align: left;
        }

        .sidebar-btn::before {
            content: attr(data-icon);
            font-size: 1.2rem;
            margin-right: 0.75rem;
            transition: all 0.3s ease;
        }

        .sidebar-btn:hover {
            background-color: rgba(255,255,255,0.15);
            color: white;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            padding: 2rem;
            background-color: #f8fafc;
            transition: all 0.3s ease;
            margin-left: 250px;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.03);
            border-left: 4px solid var(--primary);
        }

        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 2rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.03);
            text-align: center;
            border-top: 3px solid var(--primary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Course Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 12px rgba(0,0,0,0.05);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
            border-color: var(--primary-light);
        }

        .course-image {
            height: 200px;
            background-color: #e2e8f0;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .course-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .course-content {
            padding: 1.5rem;
        }

        .course-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .course-instructor {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            font-weight: 500;
        }

        .course-description {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .course-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .detail-item i {
            color: var(--secondary);
            width: 16px;
        }

        .progress-section {
            margin: 1.5rem 0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .progress-label {
            font-weight: 500;
            color: var(--dark);
        }

        .progress-percentage {
            font-weight: 600;
            color: var(--primary);
        }

        .progress-bar {
            height: 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-top: 0.5rem;
        }

        .progress-btn {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .progress-btn:hover {
            background: var(--dark);
        }

        .course-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.9rem;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #d62839);
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #d62839, #c1121f);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--error);
            color: var(--error);
        }

        .btn-outline:hover {
            background: var(--error);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
        }

        .empty-state {
            background: white;
            padding: 3rem 2rem;
            border-radius: 12px;
            text-align: center;
            color: #64748b;
            box-shadow: 0 4px 6px rgba(0,0,0,0.03);
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
        }

        /* Notification */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--success);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1001;
            opacity: 1;
            transition: opacity 0.5s;
            font-weight: 500;
        }

        .notification.error {
            background-color: var(--error);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .courses-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .course-actions {
                flex-direction: column;
            }
        }

        /* Certificate Badge */
        .certificate-badge {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }

        .completion-badge {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            position: absolute;
            top: 1rem;
            left: 1rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
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
                    <li><a href="my_courses.php" class="active" data-icon="🎓">My Courses</a></li>
                    <li><a href="my_profile.php" data-icon="👤">My Profile</a></li>
                    <li><a href="settings.php" data-icon="⚙️">Settings</a></li>
                    <li><button onclick="window.location.href='logout.php'" class="sidebar-btn" data-icon="🚪">Logout</button></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content" id="mainContent">
            <div class="page-header">
                <h1>My Dance Courses</h1>
                <p>Track your progress and continue your dance journey</p>
            </div>

            <?php if (!empty($myCourses)): ?>
            <!-- Stats Section -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= count($myCourses) ?></div>
                    <div class="stat-label">Enrolled Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= array_sum(array_column($myCourses, 'progress')) ?></div>
                    <div class="stat-label">Total Progress Points</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($myCourses, function($course) { return $course['progress'] >= 100; })) ?></div>
                    <div class="stat-label">Completed Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= round(array_sum(array_column($myCourses, 'progress')) / count($myCourses)) ?>%</div>
                    <div class="stat-label">Average Progress</div>
                </div>
            </div>

            <!-- Courses Grid -->
            <div class="courses-grid">
                <?php foreach ($myCourses as $courseId => $course): ?>
                <div class="course-card" data-course-id="<?= $courseId ?>">
                    <?php if ($course['progress'] >= 100): ?>
                    <div class="completion-badge">
                        <i class="fas fa-trophy"></i>
                        Completed!
                    </div>
                    <?php endif; ?>
                    
                    <div class="course-image" style="background-image: url('<?= htmlspecialchars($course['image']) ?>')">
                        <div class="course-badge"><?= htmlspecialchars($course['difficulty']) ?></div>
                    </div>
                    
                    <div class="course-content">
                        <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>
                        <div class="course-instructor">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor']) ?>
                        </div>
                        <p class="course-description"><?= htmlspecialchars($course['description']) ?></p>
                        
                        <div class="course-details">
                            <div class="detail-item">
                                <i class="fas fa-play-circle"></i>
                                <?= $course['lessons'] ?> Lessons
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <?= $course['duration'] ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-star"></i>
                                <?= $course['rating'] ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                Enrolled: <?= date('M j, Y', strtotime($course['enrolled_at'])) ?>
                            </div>
                        </div>

                        <div class="progress-section">
                            <div class="progress-header">
                                <span class="progress-label">Progress</span>
                                <span class="progress-percentage"><?= $course['progress'] ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $course['progress'] ?>%;"></div>
                            </div>
                            <div class="progress-controls">
                                <button class="progress-btn" onclick="updateProgress('<?= $courseId ?>', <?= max(0, $course['progress'] - 10) ?>)">-10%</button>
                                <button class="progress-btn" onclick="updateProgress('<?= $courseId ?>', <?= min(100, $course['progress'] + 10) ?>)">+10%</button>
                                <button class="progress-btn" onclick="updateProgress('<?= $courseId ?>', 100)">Complete</button>
                            </div>
                        </div>

                        <?php if ($course['certificate'] && $course['progress'] >= 100): ?>
                        <div class="certificate-badge">
                            <i class="fas fa-certificate"></i>
                            Certificate Available
                        </div>
                        <?php endif; ?>

                        <div class="course-actions">
                            <?php if ($course['progress'] < 100): ?>
                            <a href="#" class="btn btn-primary">
                                <i class="fas fa-play"></i>
                                Continue Learning
                            </a>
                            <?php else: ?>
                            <a href="#" class="btn btn-success">
                                <i class="fas fa-certificate"></i>
                                View Certificate
                            </a>
                            <?php endif; ?>
                            <button class="btn btn-outline" onclick="removeCourse('<?= $courseId ?>')">
                                <i class="fas fa-trash"></i>
                                Remove Course
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h3>No Courses Yet</h3>
                <p>You haven't enrolled in any courses yet. Start your dance journey by browsing our available courses!</p>
                <a href="user_page.php" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Browse Courses
                </a>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Toggle sidebar
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const menuToggle = document.getElementById('menuToggle');
                
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Update course progress
        async function updateProgress(courseId, newProgress) {
            try {
                const response = await fetch('my_courses.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `update_progress=1&course_id=${encodeURIComponent(courseId)}&progress=${newProgress}`
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                if (data.success) {
                    // Update progress bar
                    const courseCard = document.querySelector(`[data-course-id="${courseId}"]`);
                    const progressFill = courseCard.querySelector('.progress-fill');
                    const progressPercentage = courseCard.querySelector('.progress-percentage');
                    
                    progressFill.style.width = newProgress + '%';
                    progressPercentage.textContent = newProgress + '%';
                    
                    // Show completion badge if 100%
                    if (newProgress >= 100) {
                        if (!courseCard.querySelector('.completion-badge')) {
                            const badge = document.createElement('div');
                            badge.className = 'completion-badge';
                            badge.innerHTML = '<i class="fas fa-trophy"></i> Completed!';
                            courseCard.querySelector('.course-image').appendChild(badge);
                        }
                        
                        // Update button to show certificate
                        const primaryBtn = courseCard.querySelector('.btn-primary');
                        if (primaryBtn) {
                            primaryBtn.className = 'btn btn-success';
                            primaryBtn.innerHTML = '<i class="fas fa-certificate"></i> View Certificate';
                        }
                        
                        // Show certificate badge
                        const certificateBadge = courseCard.querySelector('.certificate-badge');
                        if (certificateBadge) {
                            certificateBadge.style.display = 'block';
                        } else {
                            const badge = document.createElement('div');
                            badge.className = 'certificate-badge';
                            badge.innerHTML = '<i class="fas fa-certificate"></i> Certificate Available';
                            courseCard.querySelector('.course-content').appendChild(badge);
                        }
                    } else {
                        // Remove completion elements if progress is less than 100%
                        const completionBadge = courseCard.querySelector('.completion-badge');
                        if (completionBadge) {
                            completionBadge.remove();
                        }
                        
                        const primaryBtn = courseCard.querySelector('.btn-success');
                        if (primaryBtn) {
                            primaryBtn.className = 'btn btn-primary';
                            primaryBtn.innerHTML = '<i class="fas fa-play"></i> Continue Learning';
                        }
                    }
                    
                    // Update stats
                    updateStats();
                    
                    showNotification('Progress updated successfully!', 'success');
                } else {
                    throw new Error(data.message || 'Failed to update progress');
                }
                
            } catch (error) {
                console.error('Error:', error);
                showNotification(error.message || 'Failed to update progress', 'error');
            }
        }

        // Remove course
        async function removeCourse(courseId) {
            if (!confirm('Are you sure you want to remove this course? All progress will be lost.')) {
                return;
            }
            
            try {
                const response = await fetch('my_courses.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `remove_course=1&course_id=${encodeURIComponent(courseId)}`
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                if (data.success) {
                    // Remove the course card with animation
                    const courseCard = document.querySelector(`[data-course-id="${courseId}"]`);
                    courseCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    courseCard.style.opacity = '0';
                    courseCard.style.transform = 'scale(0.9)';
                    
                    setTimeout(() => {
                        courseCard.remove();
                        updateStats();
                        
                        // Check if no courses left
                        const remainingCourses = document.querySelectorAll('[data-course-id]');
                        if (remainingCourses.length === 0) {
                            location.reload(); // Reload to show empty state
                        }
                    }, 300);
                    
                    showNotification('Course removed successfully!', 'success');
                } else {
                    throw new Error(data.message || 'Failed to remove course');
                }
                
            } catch (error) {
                console.error('Error:', error);
                showNotification(error.message || 'Failed to remove course', 'error');
            }
        }

        // Update statistics
        function updateStats() {
            const courseCards = document.querySelectorAll('[data-course-id]');
            const totalCourses = courseCards.length;
            
            if (totalCourses === 0) return;
            
            let totalProgress = 0;
            let completedCourses = 0;
            
            courseCards.forEach(card => {
                const progressText = card.querySelector('.progress-percentage').textContent;
                const progress = parseInt(progressText.replace('%', ''));
                totalProgress += progress;
                
                if (progress >= 100) {
                    completedCourses++;
                }
            });
            
            const avgProgress = Math.round(totalProgress / totalCourses);
            
            // Update stat cards
            const statCards = document.querySelectorAll('.stat-card .stat-number');
            if (statCards[0]) statCards[0].textContent = totalCourses;
            if (statCards[1]) statCards[1].textContent = totalProgress;
            if (statCards[2]) statCards[2].textContent = completedCourses;
            if (statCards[3]) statCards[3].textContent = avgProgress + '%';
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }

        // Handle responsive behavior
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                if (!sidebar.classList.contains('collapsed')) {
                    mainContent.classList.remove('expanded');
                }
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading states to buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('progress-btn')) {
                e.target.style.opacity = '0.7';
                e.target.disabled = true;
                
                setTimeout(() => {
                    e.target.style.opacity = '1';
                    e.target.disabled = false;
                }, 1000);
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add entrance animations
            const cards = document.querySelectorAll('.course-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>