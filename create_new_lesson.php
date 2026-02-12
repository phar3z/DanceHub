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

// Get all courses for dropdown
$courses = $pdo->query("SELECT id, title FROM courses ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = $_POST['course_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $culturalNotes = $_POST['cultural_notes'] ?? '';
    
    try {
        // Handle file upload
        $videoPath = '';
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/lessons/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExt = pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('lesson_') . '.' . $fileExt;
            $targetPath = $uploadDir . $fileName;
            
            // Validate file type
            $allowedTypes = ['mp4', 'webm', 'ogg'];
            if (!in_array(strtolower($fileExt), $allowedTypes)) {
                throw new Exception("Only MP4, WebM, and OGG video formats are allowed.");
            }
            
            // Validate file size (max 100MB)
            if ($_FILES['video']['size'] > 100 * 1024 * 1024) {
                throw new Exception("Video file size must be less than 100MB.");
            }
            
            if (move_uploaded_file($_FILES['video']['tmp_name'], $targetPath)) {
                $videoPath = $targetPath;
            } else {
                throw new Exception("Failed to upload video file.");
            }
        }
        
        // Insert lesson into database
        $stmt = $pdo->prepare("
            INSERT INTO lessons (course_id, title, description, cultural_notes, video_path)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$courseId, $title, $description, $culturalNotes, $videoPath]);
        
        $success = "Traditional dance lesson created successfully!";
    } catch (Exception $e) {
        $error = "Error creating lesson: " . $e->getMessage();
        
        // Clean up if video was uploaded but there was another error
        if (!empty($videoPath) && file_exists($videoPath)) {
            unlink($videoPath);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dance Hub - Create Lesson</title>
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

        /* Upload progress */
        .upload-progress {
            width: 100%;
            height: 4px;
            background-color: #e2e8f0;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
            display: none;
        }

        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: 0%;
            transition: width 0.3s ease;
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
                    <li><a href="manage_students.php" data-icon="👥">Manage Students</a></li>
                    <li><a href="create_new_lesson.php" class="active" data-icon="➕">Create New Lesson</a></li>
                    <li><a href="admin_settings.php" data-icon="⚙️">Settings</a></li>
                    <li><a href="logout.php" data-icon="🚪">Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="main-content" id="mainContent">
            <section class="dashboard-section">
                <h1>Create New Traditional Dance Lesson</h1>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success); ?>
                    </div>
                <?php elseif (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form id="lessonForm" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="course-select">Select Dance Style *</label>
                        <select id="course-select" name="course_id" class="form-control" required>
                            <option value="">Select a dance style...</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?= htmlspecialchars($course['id']) ?>"><?= htmlspecialchars($course['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="lesson-title">Lesson Title *</label>
                        <input type="text" id="lesson-title" name="title" class="form-control" placeholder="E.g., 'Basic Footwork Patterns'" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lesson-desc">Lesson Description *</label>
                        <textarea id="lesson-desc" name="description" class="form-control" placeholder="Describe the traditional elements students will learn..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="video-upload">Upload Demonstration Video</label>
                        <input type="file" id="video-upload" name="video" class="form-control" accept="video/*">
                        <small style="color: #64748b; display: block; margin-top: 0.5rem;">Supported formats: MP4, WebM, OGG. Max file size: 100MB</small>
                        <div class="upload-progress" id="uploadProgress">
                            <div class="upload-progress-bar" id="uploadProgressBar"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cultural-notes">Cultural Context Notes</label>
                        <textarea id="cultural-notes" name="cultural_notes" class="form-control" placeholder="Add historical/cultural context for this movement..."></textarea>
                    </div>
                    
                    <button type="submit" name="create_lesson" class="btn btn-success btn-block" id="submitBtn">
                        Create Traditional Dance Lesson
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

        // Enhanced form submission with progress tracking
        document.getElementById('lessonForm').addEventListener('submit', function(e) {
            const form = e.target;
            const submitBtn = document.getElementById('submitBtn');
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('uploadProgressBar');
            const originalText = submitBtn.textContent;
            
            // Validate form
            const courseSelect = document.getElementById('course-select');
            const lessonTitle = document.getElementById('lesson-title');
            const lessonDesc = document.getElementById('lesson-desc');
            
            if (!courseSelect.value || !lessonTitle.value.trim() || !lessonDesc.value.trim()) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return;
            }
            
            // Check if file is being uploaded
            const fileInput = document.getElementById('video-upload');
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const maxSize = 100 * 1024 * 1024; // 100MB
                const allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('Video file is too large. Maximum size is 100MB');
                    return;
                }
                
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    alert('Invalid video format. Please use MP4, WebM, or OGG');
                    return;
                }
                
                // Show progress bar
                uploadProgress.style.display = 'block';
                progressBar.style.width = '0%';
            }
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Lesson...';
            
            // If no file upload, let the form submit normally
            if (fileInput.files.length === 0) {
                return;
            }
            
            // For AJAX upload with progress
            e.preventDefault();
            
            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    submitBtn.textContent = `Uploading ${percentComplete}%`;
                }
            });
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                showNotification(response.message, 'success');
                                form.reset();
                                setTimeout(() => {
                                    showNotification('Students will now see this lesson in their courses!', 'success');
                                }, 2000);
                            } else {
                                showNotification(response.message || 'Error creating lesson', 'error');
                            }
                        } catch (e) {
                            showNotification('Lesson created successfully!', 'success');
                            form.reset();
                        }
                    } else {
                        showNotification('Error creating lesson: ' + xhr.statusText, 'error');
                    }
                    
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    uploadProgress.style.display = 'none';
                }
            };
            
            xhr.open('POST', form.action, true);
            xhr.send(formData);
        });

        // File input validation
        document.getElementById('video-upload').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 100 * 1024 * 1024; // 100MB
                const allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
                
                if (file.size > maxSize) {
                    alert('Video file is too large. Maximum size is 100MB');
                    this.value = '';
                    return;
                }
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid video format. Please use MP4, WebM, or OGG');
                    this.value = '';
                    return;
                }
                
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                showNotification(`Video file selected: ${file.name} (${fileSize} MB)`, 'success');
            }
        });

        // Notification system
        function showNotification(message, type = 'success') {
            // Remove existing notifications
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle" style="margin-right: 8px;"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove notification
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, type === 'error' ? 6000 : 4000);
        }
    </script>
</body>
</html>