<?php
/**
 * Authentication Functions for Smart Healthcare System
 * Handles all authentication-related operations
 */

// Prevent direct access to this file
if (!defined('ACCESS_ALLOWED')) {
    die('Direct access not permitted');
}

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user's role
 * @return string|null User role or null if not logged in
 */
function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

/**
 * Get current user's ID
 * @return int|null User ID or null if not logged in
 */
function getUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current user's full name
 * @return string|null User name or null if not logged in
 */
function getUserName() {
    return isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null;
}

/**
 * Get current user's email
 * @return string|null User email or null if not logged in
 */
function getUserEmail() {
    return isset($_SESSION['email']) ? $_SESSION['email'] : null;
}

/**
 * Check if user has specific role
 * @param string|array $roles Single role or array of roles to check
 * @return bool True if user has the specified role(s)
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = getUserRole();
    
    if (is_array($roles)) {
        return in_array($user_role, $roles);
    }
    
    return $user_role === $roles;
}

/**
 * Check if user is admin
 * @return bool True if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user is doctor
 * @return bool True if user is doctor
 */
function isDoctor() {
    return hasRole('doctor');
}

/**
 * Check if user is patient
 * @return bool True if user is patient
 */
function isPatient() {
    return hasRole('patient');
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 * @param string $redirect_url URL to redirect after login (optional)
 */
function requireLogin($redirect_url = null) {
    if (!isLoggedIn()) {
        if ($redirect_url) {
            $_SESSION['redirect_after_login'] = $redirect_url;
        }
        header("Location: ../index.php");
        exit();
    }
}

/**
 * Require specific role
 * Redirects to home page if user doesn't have required role
 * @param string|array $roles Required role(s)
 */
function requireRole($roles) {
    requireLogin();
    
    if (!hasRole($roles)) {
        header("Location: ../index.php");
        exit();
    }
}

/**
 * Require admin access
 * Redirects if user is not admin
 */
function requireAdmin() {
    requireRole('admin');
}

/**
 * Require doctor access
 * Redirects if user is not doctor
 */
function requireDoctor() {
    requireRole('doctor');
}

/**
 * Require patient access
 * Redirects if user is not patient
 */
function requirePatient() {
    requireRole('patient');
}

/**
 * Login user
 * @param object $user User object from database
 * @return bool True on success
 */
function loginUser($user) {
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    
    return true;
}

/**
 * Logout user
 * Destroys all session data
 */
function logoutUser() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Get dashboard URL based on user role
 * @return string Dashboard URL
 */
function getDashboardUrl() {
    if (!isLoggedIn()) {
        return '../index.php';
    }
    
    $role = getUserRole();
    $urls = [
        'admin' => '../admin/dashboard.php',
        'doctor' => '../doctor/dashboard.php',
        'patient' => '../patient/dashboard.php'
    ];
    
    return isset($urls[$role]) ? $urls[$role] : '../index.php';
}

/**
 * Check if session has expired
 * @param int $timeout Timeout in seconds (default 3600 = 1 hour)
 * @return bool True if session has expired
 */
function isSessionExpired($timeout = 3600) {
    if (!isset($_SESSION['login_time'])) {
        return true;
    }
    
    return (time() - $_SESSION['login_time']) > $timeout;
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool True if valid email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @return array Array with 'valid' (bool) and 'message' (string)
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    if (empty($errors)) {
        return ['valid' => true, 'message' => 'Password is strong'];
    } else {
        return ['valid' => false, 'message' => implode(', ', $errors)];
    }
}

/**
 * Sanitize input data
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if token is valid
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message content
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'time' => time()
    ];
}

/**
 * Get flash message
 * @return array|null Flash message or null if not set
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Display flash message as HTML
 * @return string HTML of flash message
 */
function displayFlashMessage() {
    $message = getFlashMessage();
    if ($message) {
        $icons = [
            'success' => '✓',
            'error' => '✗',
            'warning' => '⚠',
            'info' => 'ℹ'
        ];
        $icon = isset($icons[$message['type']]) ? $icons[$message['type']] : 'ℹ';
        
        return '<div class="alert alert-' . $message['type'] . '" style="margin-bottom: 20px;">
                    <strong>' . $icon . ' ' . ucfirst($message['type']) . ':</strong> 
                    ' . htmlspecialchars($message['message']) . '
                </div>';
    }
    return '';
}

/**
 * Rate limiting for login attempts
 * @param string $ip IP address
 * @param int $limit Number of attempts allowed
 * @param int $time Window time in seconds
 * @return bool True if allowed, false if rate limited
 */
function checkLoginAttempts($ip, $limit = 5, $time = 300) {
    $attempts = isset($_SESSION['login_attempts'][$ip]) ? $_SESSION['login_attempts'][$ip] : [];
    
    // Remove old attempts
    $attempts = array_filter($attempts, function($timestamp) use ($time) {
        return $timestamp > time() - $time;
    });
    
    if (count($attempts) >= $limit) {
        return false;
    }
    
    return true;
}

/**
 * Record login attempt
 * @param string $ip IP address
 */
function recordLoginAttempt($ip) {
    if (!isset($_SESSION['login_attempts'][$ip])) {
        $_SESSION['login_attempts'][$ip] = [];
    }
    $_SESSION['login_attempts'][$ip][] = time();
}

/**
 * Clear login attempts for an IP
 * @param string $ip IP address
 */
function clearLoginAttempts($ip) {
    unset($_SESSION['login_attempts'][$ip]);
}

/**
 * Get user by ID
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array|false User data or false if not found
 */
function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get user by email
 * @param PDO $pdo Database connection
 * @param string $email User email
 * @return array|false User data or false if not found
 */
function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update last activity timestamp
 */
function updateLastActivity() {
    $_SESSION['last_activity'] = time();
}

/**
 * Check if session is active (not timed out)
 * @param int $timeout Timeout in seconds
 * @return bool True if session is active
 */
function isSessionActive($timeout = 3600) {
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    return (time() - $_SESSION['last_activity']) < $timeout;
}

/**
 * Redirect to login page with return URL
 * @param string $return_url URL to return after login
 */
function redirectToLogin($return_url = null) {
    if ($return_url) {
        $_SESSION['return_url'] = $return_url;
    }
    header("Location: ../index.php");
    exit();
}

/**
 * Get return URL after login
 * @return string Return URL or dashboard URL
 */
function getReturnUrl() {
    if (isset($_SESSION['return_url'])) {
        $url = $_SESSION['return_url'];
        unset($_SESSION['return_url']);
        return $url;
    }
    return getDashboardUrl();
}

/**
 * Check if user has permission to access appointment
 * @param PDO $pdo Database connection
 * @param int $appointment_id Appointment ID
 * @param int $user_id User ID
 * @return bool True if user has permission
 */
function canAccessAppointment($pdo, $appointment_id, $user_id) {
    $role = getUserRole();
    
    if ($role === 'admin') {
        return true;
    }
    
    if ($role === 'doctor') {
        $stmt = $pdo->prepare("
            SELECT a.id FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ? AND d.user_id = ?
        ");
        $stmt->execute([$appointment_id, $user_id]);
        return $stmt->rowCount() > 0;
    }
    
    if ($role === 'patient') {
        $stmt = $pdo->prepare("
            SELECT a.id FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            WHERE a.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$appointment_id, $user_id]);
        return $stmt->rowCount() > 0;
    }
    
    return false;
}
?>