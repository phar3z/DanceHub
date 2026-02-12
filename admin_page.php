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

// Enable error reporting for display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Get all data from database
function getDashboardData($pdo) {
    $data = [];
    
    try {
        // Get course statistics with actual course data
        $courses = [
            'salsa' => ['title' => 'Salsa Fundamentals'],
            'kathak' => ['title' => 'Kathak Essentials'],
            'flamenco' => ['title' => 'Flamenco Basics'],
            'irish' => ['title' => 'Irish Basics'],
            'african' => ['title' => 'African Dance']
        ];
        
        $courseStats = [];
        foreach ($courses as $courseId => $courseInfo) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM user_courses WHERE course_id = ?");
            $stmt->execute([$courseId]);
            $studentCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT AVG(progress) as avg_progress FROM user_courses WHERE course_id = ?");
            $stmt->execute([$courseId]);
            $avgProgress = $stmt->fetchColumn();
            
            $courseStats[] = [
                'id' => $courseId,
                'title' => $courseInfo['title'],
                'student_count' => $studentCount,
                'avg_progress' => $avgProgress ?: 0
            ];
        }
        
        $data['courseStats'] = $courseStats;
        
        // Get recent student activity
        $stmt = $pdo->query("
            SELECT u.id, u.name, u.email, uc.course_id, uc.progress 
            FROM user_courses uc
            JOIN users u ON uc.user_email = u.email
            ORDER BY uc.enrolled_at DESC
            LIMIT 3
        ");
        $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add course titles to recent activity
        foreach ($recentActivity as &$activity) {
            $activity['title'] = $courses[$activity['course_id']]['title'] ?? 'Unknown Course';
        }
        $data['studentProgress'] = $recentActivity;
        
        // Get total counts
        $data['totalCourses'] = count($courses);
        
        $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM users WHERE is_admin = 0");
        $data['totalStudents'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("
            SELECT COUNT(*) as new_students 
            FROM users 
            WHERE is_admin = 0 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $data['newThisWeek'] = $stmt->fetchColumn();
        
        // Get average rating (assuming you have a ratings table)
        $data['avgRating'] = 4.7; // Default if no ratings
        
    } catch(PDOException $e) {
        error_log("Error fetching dashboard data: " . $e->getMessage());
    }
    
    return $data;
}

$dashboardData = getDashboardData($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dance Hub - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--dark), var(--secondary));
            padding: 1.5rem;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            overflow-y: auto;
            flex-shrink: 0;
            position: fixed;
            height: calc(100vh - 70px);
            z-index: 90;
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
            margin-left: 250px;
            width: calc(100% - 250px);
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

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

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }

        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .stat-card p {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
        }

        /* Content sections */
        .content-section {
            margin-top: 2rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .content-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }

        .content-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
            border-color: var(--primary-light);
        }

        .card-header {
            background-color: #eff6ff;
            padding: 1rem;
            border-bottom: 1px solid #dbeafe;
        }

        .card-header h3 {
            color: var(--dark);
        }

        .card-body {
            padding: 1.5rem;
        }

        .student-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }

        .student-item:last-child {
            border-bottom: none;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .student-info img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        .progress-indicator {
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Buttons */
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

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
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

        .btn-block {
            display: block;
            width: 100%;
            text-align: center;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669) !important;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857) !important;
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

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            color: #64748b;
            transition: all 0.3s;
        }

        .tab.active {
            border-bottom-color: var(--primary);
            font-weight: 600;
            color: var(--dark);
        }

        .tab:hover:not(.active) {
            color: var(--dark);
        }

        /* Notification */
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

        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Upload progress */
        .upload-progress {
            width: 100%;
            height: 4px;
            background-color: #e2e8f0;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: 0%;
            transition: width 0.3s ease;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 99;
                height: 100vh;
            }
        
            .main-content {
                width: 100%;
                margin-left: 0;
            }
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            header {
                padding: 1rem;
            }
            
            .main-content {
                padding: 1rem;
            }
        }
    </style>
    
    <div class="container">
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="active" data-icon="📊">Dashboard</a></li>
                    <li><a href="manage_courses.php" data-icon="🎓">Manage Courses</a></li>
                    <li><a href="manage_students.php" data-icon="👥">Manage Students</a></li>
                    <li><a href="create_new_lesson.php" data-icon="➕">Create New Lesson</a></li>
                    <li><a href="admin_settings.php" data-icon="⚙️">Settings</a></li>
                    <li><a href="logout.php" data-icon="🚪">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content" id="mainContent">
            <section class="dashboard-section">
                <h1>Welcome, <span><?= htmlspecialchars($user['name']); ?></span></h1>
                <p>Admin dashboard for managing traditional dance courses and student progress</p>
                
                <div class="stats-container">
                    <div class="stat-card">
                        <h3>Active Courses</h3>
                        <p><?= $dashboardData['totalCourses'] ?? 0 ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Students</h3>
                        <p><?= $dashboardData['totalStudents'] ?? 0 ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>New This Week</h3>
                        <p><?= $dashboardData['newThisWeek'] ?? 0 ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Avg. Rating</h3>
                        <p><?= $dashboardData['avgRating'] ?? '4.7' ?></p>
                    </div>
                </div>
            </section>
            
            <div class="tabs">
                <div class="tab active">Courses</div>
                <div class="tab">Students</div>
                <div class="tab">Content</div>
            </div>
            
            <section class="content-section">
                <h2>Traditional Dance Courses</h2>
                <div class="content-grid">
                    <?php foreach ($dashboardData['courseStats'] ?? [] as $course): ?>
                    <div class="content-card">
                        <div class="card-header">
                            <h3><?= htmlspecialchars($course['title']) ?></h3>
                        </div>
                        <div class="card-body">
                            <p><strong><?= $course['student_count'] ?></strong> students enrolled</p>
                            <p>Avg. progress: <strong><?= round($course['avg_progress'] ?? 0) ?>%</strong></p>
                            <a href="manage_course.php?id=<?= $course['id'] ?>" class="btn btn-block btn-sm">Manage Course</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($dashboardData['courseStats'])): ?>
                    <div class="content-card">
                        <div class="card-body">
                            <p>No courses found. <a href="add_course.php">Create your first course</a></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <section class="content-section">
                <h2>Recent Student Activity</h2>
                <div class="content-card">
                    <div class="card-body">
                        <?php foreach ($dashboardData['studentProgress'] ?? [] as $progress): ?>
                        <div class="student-item">
                            <div class="student-info">
                                <img src="https://via.placeholder.com/36/1e40af/ffffff?text=<?= strtoupper(substr($progress['name'], 0, 1)) ?>" alt="Student">
                                <div>
                                    <h4><?= htmlspecialchars($progress['name']) ?></h4>
                                    <p><?= htmlspecialchars($progress['title']) ?> • Progress: <?= $progress['progress'] ?>%</p>
                                </div>
                            </div>
                            <div class="progress-indicator">
                                <?= $progress['progress'] ?>% completed
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($dashboardData['studentProgress'])): ?>
                        <p>No recent student activity to display</p>
                        <?php endif; ?>
                        
                        <a href="manage_students.php" class="btn btn-outline btn-block" style="margin-top: 1rem;">View All Students</a>
                    </div>
                </div>
            </section>
            
            <section class="content-section">
                <h2>Create New Traditional Dance Lesson</h2>
                <div class="content-card">
                    <div class="card-body">
                        <form id="lessonForm" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="course-select">Select Dance Style</label>
                                <select id="course-select" name="course_id" class="form-control" required>
                                    <option value="">Select a dance style...</option>
                                    <?php foreach ($dashboardData['courseStats'] ?? [] as $course): ?>
                                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="lesson-title">Lesson Title *</label>
                                <input type="text" id="lesson-title" name="lesson_title" class="form-control" placeholder="E.g., 'Basic Footwork Patterns'" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="lesson-desc">Lesson Description *</label>
                                <textarea id="lesson-desc" name="lesson_desc" class="form-control" placeholder="Describe the traditional elements students will learn..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="video-upload">Upload Demonstration Video</label>
                                <input type="file" id="video-upload" name="video" class="form-control" accept="video/*">
                                <small style="color: #64748b; display: block; margin-top: 0.5rem;">Supported formats: MP4, WebM, OGG, AVI, MOV. Max file size: 100MB</small>
                                <div class="upload-progress" id="uploadProgress" style="display: none;">
                                    <div class="upload-progress-bar" id="uploadProgressBar"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="cultural-notes">Cultural Context Notes</label>
                                <textarea id="cultural-notes" name="cultural_notes" class="form-control" placeholder="Add historical/cultural context for this movement..."></textarea>
                            </div>
                            
                            <button type="submit" name="create_lesson" class="btn btn-success" id="submitBtn">Create Traditional Dance Lesson</button>
                        </form>
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

        // Tab functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                showNotification(`Switched to ${this.textContent} view`, 'success');
            });
        });

        // Enhanced form submission using separate handler
        document.getElementById('lessonForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            const uploadProgress = document.getElementById('uploadProgress');
            const uploadProgressBar = document.getElementById('uploadProgressBar');
            
            // Validate form before submission
            const courseId = formData.get('course_id');
            const lessonTitle = formData.get('lesson_title');
            const lessonDesc = formData.get('lesson_desc');
            
            if (!courseId || !lessonTitle || !lessonDesc) {
                showNotification('Please fill in all required fields', 'error');
                return;
            }
            
            // Disable form and show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Lesson...';
            this.classList.add('loading');
            
            // Show progress bar if file is being uploaded
            const videoFile = formData.get('video');
            if (videoFile && videoFile.size > 0) {
                uploadProgress.style.display = 'block';
                uploadProgressBar.style.width = '0%';
            }
            
            try {
                const xhr = new XMLHttpRequest();
                
                // Track upload progress
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        uploadProgressBar.style.width = percentComplete + '%';
                        submitBtn.textContent = `Uploading... ${Math.round(percentComplete)}%`;
                    }
                });
                
                // Handle response
                xhr.addEventListener('load', function() {
                    uploadProgress.style.display = 'none';
                    
                    // Always restore form state
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    document.getElementById('lessonForm').classList.remove('loading');
                    
                    if (xhr.status !== 200) {
                        showNotification(`HTTP error! status: ${xhr.status}`, 'error');
                        console.error('Response:', xhr.responseText);
                        return;
                    }
                    
                    let result;
                    try {
                        const responseText = xhr.responseText.trim();
                        console.log('Raw response:', responseText); // Debug log
                        
                        // Check if response starts with valid JSON
                        if (!responseText.startsWith('{')) {
                            console.error('Response does not start with JSON:', responseText.substring(0, 100));
                            showNotification('Server returned invalid response. Check browser console for details.', 'error');
                            return;
                        }
                        
                        result = JSON.parse(responseText);
                    } catch (jsonError) {
                        console.error('JSON parse error:', jsonError);
                        console.error('Response was not valid JSON:', xhr.responseText);
                        showNotification('Server returned invalid JSON response. Check browser console for details.', 'error');
                        return;
                    }
                    
                    if (result.success) {
                        showNotification(result.message, 'success');
                        document.getElementById('lessonForm').reset();
                        
                        // Show additional success information
                        setTimeout(() => {
                            showNotification('Students will now see this lesson in their courses!', 'success');
                        }, 2000);
                    } else {
                        showNotification(result.message || 'Error creating lesson', 'error');
                    }
                });
                
                // Handle network errors
                xhr.addEventListener('error', function() {
                    uploadProgress.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    document.getElementById('lessonForm').classList.remove('loading');
                    showNotification('Network error occurred. Please check your connection.', 'error');
                });
                
                // Handle timeout
                xhr.addEventListener('timeout', function() {
                    uploadProgress.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    document.getElementById('lessonForm').classList.remove('loading');
                    showNotification('Upload timed out. Please try again with a smaller file.', 'error');
                });
                
                // Set timeout (5 minutes for large video uploads)
                xhr.timeout = 300000;
                
                // Send the request to the separate handler
                xhr.open('POST', 'create_lesson.php', true);
                xhr.send(formData);
                
            } catch (error) {
                console.error('Error:', error);
                uploadProgress.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                document.getElementById('lessonForm').classList.remove('loading');
                showNotification('Failed to create lesson: ' + error.message, 'error');
            }
        });

        // Enhanced file upload validation
        document.getElementById('video-upload').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 100 * 1024 * 1024; // 100MB
                const allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov'];
                
                // Check file size
                if (file.size > maxSize) {
                    showNotification('Video file is too large. Maximum size is 100MB', 'error');
                    this.value = '';
                    return;
                }
                
                // Check file type
                if (!allowedTypes.includes(file.type)) {
                    showNotification('Invalid video format. Please use MP4, WebM, OGG, AVI, or MOV', 'error');
                    this.value = '';
                    return;
                }
                
                // Show file info
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                showNotification(`Video file selected: ${file.name} (${fileSize} MB)`, 'success');
            }
        });

        // Enhanced notification system
        function showNotification(message, type = 'success') {
            // Remove existing notifications
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            
            const icon = type === 'success' ? 'check' : 'exclamation';
            notification.innerHTML = `
                <i class="fas fa-${icon}-circle" style="margin-right: 8px;"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove notification
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 500);
            }, type === 'error' ? 6000 : 4000);
        }

        // Add real-time validation
        document.getElementById('course-select').addEventListener('change', function() {
            if (this.value) {
                this.style.borderColor = '#2a9d8f';
            }
        });

        document.getElementById('lesson-title').addEventListener('input', function() {
            if (this.value.trim()) {
                this.style.borderColor = '#2a9d8f';
            }
        });

        document.getElementById('lesson-desc').addEventListener('input', function() {
            if (this.value.trim()) {
                this.style.borderColor = '#2a9d8f';
            }
        });

        // Prevent form submission on Enter key in text inputs (except textarea)
        document.querySelectorAll('input[type="text"], select').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = this.closest('form');
                    const inputs = Array.from(form.querySelectorAll('input, select, textarea'));
                    const currentIndex = inputs.indexOf(this);
                    const nextInput = inputs[currentIndex + 1];
                    if (nextInput) {
                        nextInput.focus();
                    }
                }
            });
        });

        // Utility function for debouncing
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    </script>
</body>
</html>