<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default page title if not set
if (!isset($page_title)) {
    $page_title = "Smart Healthcare | Clinic Management System";
}

// Determine base URL automatically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$project_folder = 'smartclinic'; // Change this to your project folder name

// Construct base URL
$base_url = $protocol . '://' . $host . '/' . $project_folder;

// Check if we're in a subdirectory (admin, patient, doctor)
$current_path = $_SERVER['PHP_SELF'];
$is_in_subfolder = (strpos($current_path, '/admin/') !== false || 
                    strpos($current_path, '/patient/') !== false || 
                    strpos($current_path, '/doctor/') !== false);

// Set CSS path based on location
if ($is_in_subfolder) {
    $css_path = '../style_project.css';
} else {
    $css_path = 'style_project.css';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo $css_path; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    
    <!-- For modals (only show on homepage) -->
    <?php if (!$is_in_subfolder): ?>
    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
        }
        .modal-content {
            background: #fff;
            width: 400px;
            max-width: 90%;
            padding: 40px 30px;
            border-radius: 15px;
            margin: 8% auto;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            animation: slideDown 0.4s ease;
            position: relative;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 35px;
            height: 35px;
            background: #f2f2f2;
            color: #333;
            font-size: 22px;
            font-weight: bold;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            border: none;
        }
        .close:hover {
            background: #ff4d4d;
            color: #fff;
            transform: scale(1.1);
        }
    </style>
    <?php endif; ?>
</head>
<body>

<header>
    <nav>
        <div class="logo">Smart<span>Clinic</span></div>
        <ul class="nav-links">
            <?php if ($is_in_subfolder): ?>
                <!-- Links when in subfolder (admin, patient, doctor) -->
                <li><a href="../index.php">Home</a></li>
                <li><a href="../doctors.php">Doctors</a></li>
                <li><a href="../services.php">Services</a></li>
                <li><a href="../feedback.php">Feedback</a></li>
            <?php else: ?>
                <!-- Links when on main pages -->
                <li><a href="index.php">Home</a></li>
                <li><a href="doctors.php">Doctors</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="feedback.php">Feedback</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="auth-buttons">
            <?php if(isset($_SESSION['user_id'])): ?>
                <!-- Show when logged in -->
                <?php 
                // Determine dashboard link based on role
                $dashboard_links = [
                    'admin' => 'admin/dashboard.php',
                    'doctor' => 'doctor/dashboard.php',
                    'patient' => 'patient/dashboard.php'
                ];
                
                $dashboard_link = isset($dashboard_links[$_SESSION['role']]) ? $dashboard_links[$_SESSION['role']] : 'index.php';
                
                // Adjust path if we're already in a subfolder
                if ($is_in_subfolder) {
                    $dashboard_link = '../' . $dashboard_link;
                }
                ?>
                
                <a href="<?php echo $dashboard_link; ?>" class="btn-login">
                    <?php 
                    $role_names = [
                        'admin' => 'Admin Panel',
                        'doctor' => 'Doctor Panel', 
                        'patient' => 'My Dashboard'
                    ];
                    echo $role_names[$_SESSION['role']] ?? 'Dashboard';
                    ?>
                </a>
                
                <?php 
                // Determine logout path
                $logout_path = $is_in_subfolder ? '../auth/logout.php' : 'auth/logout.php';
                ?>
                <a href="<?php echo $logout_path; ?>" class="btn-signup">Logout</a>
                
            <?php else: ?>
                <!-- Show when logged out -->
                <?php if (!$is_in_subfolder): ?>
                    <a href="#" class="btn-login" onclick="openLogin()">Login</a>
                    <a href="#" class="btn-signup" onclick="openRegister()">Register</a>
                <?php else: ?>
                    <!-- When in subfolder but not logged in, redirect to home -->
                    <a href="../index.php" class="btn-login">Login</a>
                    <a href="../index.php" class="btn-signup">Register</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </nav>
</header>

<?php if (!$is_in_subfolder): ?>
<!-- Login Modal (only on homepage) -->
<div id="loginModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeLogin()" title="Close">&times;</span>
    <h2>Login to Your Account</h2>
    <form action="auth/login.php" method="POST">
        <select name="role" id="role" required>
            <option value="">Select Role</option>
            <option value="admin">Admin</option>
            <option value="doctor">Doctor</option>
            <option value="patient">Patient</option>
        </select>
        <input type="email" name="email" id="email" placeholder="Email" required>
        <input type="password" name="password" id="password" placeholder="Password" required>
        <button type="submit" class="btn-primary">Login</button>
    </form>
  </div>
</div>

<!-- Register Modal (only on homepage) -->
<div id="registerModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeRegister()" title="Close">&times;</span>
    <h2>Create Patient Account</h2>
    <form action="auth/register.php" method="POST">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="tel" name="phone" placeholder="Phone Number" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="submit" class="btn-secondary">Register</button>
    </form>
  </div>
</div>

<!-- JavaScript for modals -->
<script>
// Open & Close Modals
function openLogin() { 
    document.getElementById("loginModal").style.display = "block"; 
}
function closeLogin() { 
    document.getElementById("loginModal").style.display = "none"; 
}
function openRegister() { 
    document.getElementById("registerModal").style.display = "block"; 
}
function closeRegister() { 
    document.getElementById("registerModal").style.display = "none"; 
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for(let modal of modals) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
}

// Close modal with Escape key
document.onkeydown = function(evt) {
    evt = evt || window.event;
    if (evt.keyCode == 27) {
        closeLogin();
        closeRegister();
    }
};
</script>
<?php endif; ?>