<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Find user by email AND role
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect based on role from DATABASE
        switch($user['role']) {
            case 'admin':
                header("Location: ../admin/dashboard.php");
                break;
            case 'doctor':
                header("Location: ../doctor/dashboard.php");
                break;
            case 'patient':
                header("Location: ../patient/dashboard.php");
                break;
            default:
                header("Location: ../index.php");
        }
        exit();
    } else {
        // Login failed - store error and redirect back
        $_SESSION['login_error'] = "Invalid email, password, or role";
        header("Location: ../index.php");
        exit();
    }
} else {
    // If not POST request, redirect to login page
    header("Location: ../index.php");
    exit();
}
?>