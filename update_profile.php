<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;

    // Handle regular profile update
    if (isset($_POST['update_profile'])) {
        $name = $conn->real_escape_string($_POST['name'] ?? '');
        $email = $conn->real_escape_string($_POST['email'] ?? '');

        // Validate inputs
        if (empty($name) || empty($email)) {
            $_SESSION['profile_error'] = 'All fields are required';
            header("Location: my_profile.php");
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['profile_error'] = 'Invalid email format';
            header("Location: my_profile.php");
            exit();
        }

        // Check if email is being changed to one that already exists
        if ($email !== $_SESSION['email']) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $user_id);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['profile_error'] = 'Email already in use by another account';
                header("Location: my_profile.php");
                exit();
            }
        }

        // Update profile
        try {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $email, $user_id);
            
            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $_SESSION['profile_success'] = 'Profile updated successfully';
            } else {
                $_SESSION['profile_error'] = 'Error updating profile';
            }
        } catch (Exception $e) {
            $_SESSION['profile_error'] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Handle avatar update
    elseif (isset($_POST['update_avatar'])) {
        $avatar_id = intval($_POST['avatar_id'] ?? 1);
        
        // Validate avatar ID (1-4)
        if ($avatar_id < 1 || $avatar_id > 4) {
            $_SESSION['profile_error'] = 'Invalid avatar selection';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE users SET avatar_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $avatar_id, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['profile_success'] = 'Profile picture updated successfully';
                } else {
                    $_SESSION['profile_error'] = 'Error updating avatar';
                }
            } catch (Exception $e) {
                $_SESSION['profile_error'] = 'Database error: ' . $e->getMessage();
            }
        }
    }
    
    // Handle password change
    elseif (isset($_POST['change_password'])) {
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
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                try {
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['password_success'] = 'Password changed successfully';
                    } else {
                        $_SESSION['password_error'] = 'Error changing password';
                    }
                } catch (Exception $e) {
                    $_SESSION['password_error'] = 'Database error: ' . $e->getMessage();
                }
            } else {
                $_SESSION['password_error'] = 'Current password is incorrect';
            }
        }
    }

    header("Location: my_profile.php");
    exit();
}
?>