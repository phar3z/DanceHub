<?php
// Database configuration
$host = 'localhost';
$dbname = 'dance_hub';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set up your admin credentials
    $adminEmail = 'test2@email.com'; // Change to your admin email
    $adminPassword = password_hash('1234', PASSWORD_DEFAULT); // Change to a secure password
    $adminName = 'Admin User';

    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    
    if ($stmt->rowCount() == 0) {
        // Insert admin user
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, is_admin, created_at) 
            VALUES (?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$adminName, $adminEmail, $adminPassword]);
        
        echo "Admin user created successfully!<br>";
        echo "Email: $adminEmail<br>";
        echo "Password: admin123 (change this immediately after login)";
    } else {
        echo "Admin user already exists";
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}