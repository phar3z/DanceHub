<?php
// create_lesson.php - Separate handler for lesson creation
session_start();

// Disable all output and error display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(0); // Completely disable error reporting for this script

// Check if user is logged in and is admin
if (!isset($_SESSION['email'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if user is admin
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$user = $stmt->fetch();

if (!$user || !$user['is_admin']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Function to ensure lessons table exists
function ensureLessonsTableExists($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'lessons'");
        if ($stmt->rowCount() == 0) {
            $createTable = "
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
            ";
            $pdo->exec($createTable);
        }
    } catch(PDOException $e) {
        // Log error but don't output anything
        error_log("Error creating lessons table: " . $e->getMessage());
    }
}

// Ensure lessons table exists
ensureLessonsTableExists($pdo);

$response = ['success' => false, 'message' => ''];

try {
    // Validate required fields
    if (empty($_POST['course_id']) || empty($_POST['lesson_title']) || empty($_POST['lesson_desc'])) {
        throw new Exception('Please fill in all required fields');
    }

    // Sanitize input
    $course_id = trim($_POST['course_id']);
    $lesson_title = trim($_POST['lesson_title']);
    $lesson_desc = trim($_POST['lesson_desc']);
    $cultural_notes = isset($_POST['cultural_notes']) ? trim($_POST['cultural_notes']) : null;

    // Handle file upload
    $videoPath = null;
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/lessons/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        // Generate unique filename
        $fileExt = pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('lesson_') . '.' . $fileExt;
        $destination = $uploadDir . $filename;

        // Validate file type and size
        $maxSize = 500 * 1024 * 1024; // 500MB
        $allowedExtensions = ['mp4', 'webm', 'ogg', 'avi', 'mov'];
        
        // Check file extension
        if (!in_array(strtolower($fileExt), $allowedExtensions)) {
            throw new Exception('Invalid video file type. Please use MP4, WebM, OGG, AVI, or MOV');
        }

        // Check file size
        if ($_FILES['video']['size'] > $maxSize) {
            throw new Exception('Video file is too large. Maximum size is 500MB');
        }

        // Additional MIME type check if available
        if (function_exists('mime_content_type')) {
            $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov'];
            $mimeType = mime_content_type($_FILES['video']['tmp_name']);
            if ($mimeType && !in_array($mimeType, $allowedTypes)) {
                throw new Exception('Invalid video file format detected');
            }
        }

        if (!move_uploaded_file($_FILES['video']['tmp_name'], $destination)) {
            throw new Exception('Failed to upload video file');
        }

        $videoPath = $destination;
    }

    // Insert lesson into database
    $stmt = $pdo->prepare("
        INSERT INTO lessons (course_id, title, description, cultural_notes, video_path)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $course_id,
        $lesson_title,
        $lesson_desc,
        $cultural_notes,
        $videoPath
    ]);

    if ($success) {
        $response = [
            'success' => true,
            'message' => 'Traditional dance lesson created successfully!',
            'lesson_id' => $pdo->lastInsertId()
        ];
    } else {
        throw new Exception('Failed to save lesson to database');
    }
    
} catch(Exception $e) {
    $response['message'] = $e->getMessage();
    // Clean up uploaded file if database insert failed
    if (isset($destination) && file_exists($destination)) {
        unlink($destination);
    }
} catch(PDOException $e) {
    $response['message'] = 'Database error occurred';
    error_log("Database error: " . $e->getMessage());
    // Clean up uploaded file if database insert failed
    if (isset($destination) && file_exists($destination)) {
        unlink($destination);
    }
}

// Send JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
echo json_encode($response);
exit();
?>