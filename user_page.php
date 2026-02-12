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

// Function to get course data from database
function getCourseDataById($pdo, $courseId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$courseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching course data: " . $e->getMessage());
        return null;
    }
}

// Function to get all available courses (not enrolled by user)
function getAvailableCourses($pdo, $userEmail) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.* 
            FROM courses c
            LEFT JOIN user_courses uc ON c.id = uc.course_id AND uc.user_email = ?
            WHERE uc.course_id IS NULL
            ORDER BY c.title
        ");
        $stmt->execute([$userEmail]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching available courses: " . $e->getMessage());
        return [];
    }
}

// Function to get user's enrolled courses from database
function getUserCourses($pdo, $userEmail) {
    try {
        $stmt = $pdo->prepare("
            SELECT uc.course_id, uc.progress, uc.enrolled_at, c.*
            FROM user_courses uc
            JOIN courses c ON uc.course_id = c.id
            WHERE uc.user_email = ?
        ");
        $stmt->execute([$userEmail]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching user courses: " . $e->getMessage());
        return [];
    }
}

// Function to get lessons for a course
function getCourseLessons($pdo, $courseId) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, description, cultural_notes, video_path, created_at
            FROM lessons 
            WHERE course_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching course lessons: " . $e->getMessage());
        return [];
    }
}

// Function to add course to database
function addCourseToDatabase($pdo, $userEmail, $courseId) {
    try {
        $checkStmt = $pdo->prepare("SELECT id FROM user_courses WHERE user_email = ? AND course_id = ?");
        $checkStmt->execute([$userEmail, $courseId]);
        
        if ($checkStmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Course already enrolled'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO user_courses (user_email, course_id, progress, enrolled_at) 
            VALUES (?, ?, 0, NOW())
        ");
        $result = $stmt->execute([$userEmail, $courseId]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Course added successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to execute insert statement'];
        }
        
    } catch(PDOException $e) {
        error_log("Error adding course: " . $e->getMessage());
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

// Get available courses
$availableCourses = getAvailableCourses($pdo, $_SESSION['email']);

// Get user's enrolled courses
$userCourses = getUserCourses($pdo, $_SESSION['email']);
$myCourses = [];

foreach ($userCourses as $userCourse) {
    $courseData = $userCourse;
    $courseData['progress'] = $userCourse['progress'];
    $courseData['enrolled_at'] = $userCourse['enrolled_at'];
    $courseData['lessons_content'] = getCourseLessons($pdo, $userCourse['course_id']);
    $myCourses[$userCourse['course_id']] = $courseData;
}

// Handle course addition/removal if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['add_course'])) {
        $courseId = $_POST['course_id'];
        $result = addCourseToDatabase($pdo, $_SESSION['email'], $courseId);
        $response = array_merge($response, $result);
    } 
    elseif (isset($_POST['remove_course'])) {
        $courseId = $_POST['course_id'];
        $result = removeCourseFromDatabase($pdo, $_SESSION['email'], $courseId);
        $response = array_merge($response, $result);
    }
    
    // Return JSON response for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Redirect for non-AJAX requests
    if ($response['success']) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dance Hub - User Dashboard</title>
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
            transition: all 0.3s ease;
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
            overflow: hidden;
            flex-shrink: 0;
            position: fixed; /* Changed to fixed */
            top: 70px; /* Adjusted to account for the height of the header bar */
            left: 0; /* Align to the left */
            height: calc(100vh - 70px); /* Full height minus header */
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

        /* Main Content Area */
        .main-content {
            flex: 1;
            padding: 2rem;
            background-color: #f8fafc;
            transition: all 0.3s ease;
            margin-left: 250px; /* Adjusted to account for fixed sidebar */
        }

        .main-content.expanded {
            margin-left: 0; /* Adjust to fill the space when sidebar is collapsed */
        }

        .welcome-section {
            background: white;
            padding: 1.75rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.03);
            border-left: 4px solid var(--primary);
        }

        h1 {
            color: var(--dark);
            margin-bottom: 0.75rem;
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

        /* Dance Categories */
        .categories {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .category-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
            border-color: var(--primary-light);
        }

        .category-img {
            height: 180px;
            background-color: #e2e8f0;
            background-size: cover;
            background-position: center;
        }

        .category-info {
            padding: 1.5rem;
        }

        .category-info h3 {
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 600;
        }

        .category-info p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .progress-bar {
            height: 6px;
            background-color: #e2e8f0;
            border-radius: 3px;
            margin: 1rem 0;
        }

        .progress {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 3px;
            width: 30%;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), #d62839);
            color: white;
            padding: 0.65rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 0.75rem;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #d62839, #c1121f);
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

        /* Course Selection */
        .content-section {
            margin-top: 2rem;
        }

        .course-details {
            display: flex;
            gap: 0.5rem;
            margin: 0.75rem 0;
            flex-wrap: wrap;
        }

        .badge {
            background-color: #e0e7ff;
            color: var(--dark);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .btn-add-course {
            background: linear-gradient(135deg, var(--success), #059669) !important;
        }

        .btn-add-course:hover {
            background: linear-gradient(135deg, #059669, #047857) !important;
        }

        .added-to-courses {
            background: #e0e7ff !important;
            color: var(--dark) !important;
            cursor: default;
        }

        .btn-remove-course {
            background: linear-gradient(135deg, var(--error), #c1121f) !important;
            margin-left: 0.5rem;
        }

        .btn-remove-course:hover {
            background: linear-gradient(135deg, #c1121f, #a4161a) !important;
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Lessons Section */
        .lessons-section {
            margin-top: 2rem;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .lessons-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .lessons-header h4 {
            color: var(--dark);
            font-size: 1.1rem;
            font-weight: 600;
        }

        .lessons-count {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .lesson-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .lesson-item:hover {
            border-color: var(--primary-light);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .lesson-item:last-child {
            margin-bottom: 0;
        }

        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .lesson-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
            margin: 0;
        }

        .lesson-date {
            font-size: 0.8rem;
            color: #64748b;
            white-space: nowrap;
        }

        .lesson-description {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .lesson-video {
            width: 100%;
            max-width: 400px;
            height: 225px;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .cultural-notes {
            background: #f0f9ff;
            border-left: 4px solid var(--secondary);
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
        }

        .cultural-notes h5 {
            color: var(--secondary);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .cultural-notes p {
            color: #475569;
            font-size: 0.85rem;
            line-height: 1.4;
            margin: 0;
        }

        .no-lessons {
            text-align: center;
            color: #64748b;
            font-style: italic;
            padding: 2rem;
        }

        .no-video {
            background: #f1f5f9;
            border: 2px dashed #cbd5e1;
            border-radius: 6px;
            padding: 2rem;
            text-align: center;
            color: #64748b;
            margin-bottom: 1rem;
        }

        /* Notification Style */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--success);
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
            opacity: 1;
            transition: opacity 0.5s;
        }

        .notification.error {
            background-color: var(--error);
        }

        .empty-state {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            grid-column: 1 / -1;
            color: #64748b;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .categories {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: fixed;
                z-index: 99;
                height: 100vh;
            }
            
            .main-content {
                width: 100%;
            }

            .course-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .btn-remove-course {
                margin-left: 0;
            }

            .lessons-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .lesson-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
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
                    <li><a href="user_page.php" class="active" data-icon="📊">Dashboard</a></li>
                    <li><a href="my_courses.php" data-icon="🎓">My Courses</a></li>
                    <li><a href="my_profile.php" data-icon="👤">My Profile</a></li>
                    <li><a href="settings.php" data-icon="⚙️">Settings</a></li>
                    <li><button onclick="window.location.href='logout.php'" class="sidebar-btn" data-icon="🚪">Logout</button></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content" id="mainContent">
            <section class="welcome-section">
                <h1>Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'User'); ?></h1>
                <p>Continue your traditional dance journey with these recommended lessons.</p>
            </section>
            
            <!-- Available Courses Section -->
            <section class="content-section">
                <h2>Available Courses</h2>
                <div class="categories">
                    <?php foreach ($availableCourses as $course): ?>
                    <div class="category-card">
                        <div class="category-img" style="background-image: url('<?= htmlspecialchars($course['image'] ?? 'default.jpg') ?>')"></div>
                        <div class="category-info">
                            <h3><?= htmlspecialchars($course['title']) ?></h3>
                            <p><?= htmlspecialchars($course['description']) ?></p>
                            <div class="course-details">
                                <span class="badge"><?= htmlspecialchars($course['difficulty'] ?? 'All Levels') ?></span>
                                <span class="badge"><?= htmlspecialchars($course['lessons'] ?? 0) ?> Lessons</span>
                                <span class="badge"><?= htmlspecialchars($course['rating'] ?? '4.5★') ?></span>
                            </div>
                            <button class="btn btn-add-course" data-course="<?= htmlspecialchars($course['id']) ?>">
                                Add to My Courses
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($availableCourses)): ?>
                    <div class="empty-state">
                        <p>No available courses at the moment. Check back later!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Current Courses Section -->
            <section class="content-section">
                <h2>Your Dance Courses</h2>
                <div class="categories">
                    <?php foreach ($myCourses as $courseId => $course): ?>
                    <div class="category-card">
                        <div class="category-img" style="background-image: url('<?= htmlspecialchars($course['image'] ?? 'default.jpg') ?>')"></div>
                        <div class="category-info">
                            <h3><?= htmlspecialchars($course['title']) ?></h3>
                            <p><?= htmlspecialchars($course['description']) ?></p>
                            <div class="course-details">
                                <span class="badge"><?= htmlspecialchars($course['difficulty'] ?? 'All Levels') ?></span>
                                <span class="badge"><?= htmlspecialchars($course['lessons'] ?? 0) ?> Lessons</span>
                                <span class="badge"><?= htmlspecialchars($course['rating'] ?? '4.5★') ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?= $course['progress'] ?? 0 ?>%;"></div>
                            </div>
                            <p><?= $course['progress'] ?? 0 ?>% completed</p>
                            <div class="course-actions">
                                <a href="#" class="btn">Continue Learning</a>
                                <button class="btn btn-remove-course" data-course="<?= $courseId ?>">Remove Course</button>
                            </div>
                            
                            <!-- Lessons Section -->
                            <?php if (!empty($course['lessons_content'])): ?>
                            <div class="lessons-section">
                                <div class="lessons-header">
                                    <h4>Traditional Dance Lessons</h4>
                                    <span class="lessons-count"><?= count($course['lessons_content']) ?> Lesson<?= count($course['lessons_content']) != 1 ? 's' : '' ?></span>
                                </div>
                                
                                <?php foreach ($course['lessons_content'] as $lesson): ?>
                                <div class="lesson-item">
                                    <div class="lesson-header">
                                        <h5 class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></h5>
                                        <span class="lesson-date"><?= date('M j, Y', strtotime($lesson['created_at'])) ?></span>
                                    </div>
                                    
                                    <p class="lesson-description"><?= htmlspecialchars($lesson['description']) ?></p>
                                    
                                    <?php if ($lesson['video_path']): ?>
                                        <video class="lesson-video" controls>
                                            <source src="<?= htmlspecialchars($lesson['video_path']) ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php else: ?>
                                        <div class="no-video">
                                            <i class="fas fa-video" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                            <p>No video available for this lesson</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($lesson['cultural_notes']): ?>
                                    <div class="cultural-notes">
                                        <h5>Cultural Context</h5>
                                        <p><?= htmlspecialchars($lesson['cultural_notes']) ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="lessons-section">
                                <div class="no-lessons">
                                    <i class="fas fa-music" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    <p>No lessons available yet. Check back soon for new content!</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($myCourses)): ?>
                    <div class="empty-state">
                        <p>You haven't added any courses yet. Browse our available courses to get started!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Explore More Section -->
            <section class="content-section">
                <h2>Explore Traditional Dances</h2>
                <div class="categories">
                    <div class="category-card">
                        <div class="category-img" style="background-image: url('https://source.unsplash.com/random/300x180/?odissi,dance')"></div>
                        <div class="category-info">
                            <h3>Odissi</h3>
                            <p>The classical dance form from Eastern India</p>
                            <a href="#" class="btn btn-outline">Explore</a>
                        </div>
                    </div>
                    
                    <div class="category-card">
                        <div class="category-img" style="background-image: url('https://source.unsplash.com/random/300x180/?hula,dance')"></div>
                        <div class="category-info">
                            <h3>Hula</h3>
                            <p>The storytelling dance of Hawaii</p>
                            <a href="#" class="btn btn-outline">Explore</a>
                        </div>
                    </div>
                    
                    <div class="category-card">
                        <div class="category-img" style="background-image: url('https://source.unsplash.com/random/300x180/?folk,dance')"></div>
                        <div class="category-info">
                            <h3>Folk Dances</h3>
                            <p>Traditional dances from around the world</p>
                            <a href="#" class="btn btn-outline">Explore</a>
                        </div>
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

        // Course Selection Functionality
        document.querySelectorAll('.btn-add-course').forEach(button => {
            button.addEventListener('click', async function() {
                const courseId = this.getAttribute('data-course');
                const originalText = this.textContent;
                this.textContent = 'Adding...';
                this.disabled = true;
                
                try {
                    const response = await fetch('user_page.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `add_course=1&course_id=${encodeURIComponent(courseId)}`
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to add course');
                    }

                    this.textContent = 'Added ✓';
                    this.classList.add('added-to-courses');
                    showNotification('Course added successfully!', 'success');
                    
                    // Update the courses section
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                    
                } catch (error) {
                    console.error('Error:', error);
                    this.textContent = originalText;
                    this.disabled = false;
                    showNotification(error.message || 'Failed to add course', 'error');
                }
            });
        });

        // Remove Course Functionality
        document.addEventListener('click', async function(e) {
            if (e.target.classList.contains('btn-remove-course')) {
                const button = e.target;
                const courseId = button.getAttribute('data-course');
                const card = button.closest('.category-card');
                const originalText = button.textContent;
                button.textContent = 'Removing...';
                button.disabled = true;
                
                try {
                    const response = await fetch('user_page.php', {
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
                    
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to remove course');
                    }

                    showNotification('Course removed successfully!', 'success');
                    
                    // Reload page to show updated courses
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                    
                } catch (error) {
                    console.error('Error:', error);
                    button.textContent = originalText;
                    button.disabled = false;
                    showNotification(error.message || 'Failed to remove course', 'error');
                }
            }
        });

        function showNotification(message, type) {
            // Remove existing notifications
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle" style="margin-right: 8px;"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, 4000);
        }
    </script>
</body>
</html>