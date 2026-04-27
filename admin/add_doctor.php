<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $phone = $_POST['phone'];
    $specialization = $_POST['specialization'];
    $qualification = $_POST['qualification'];
    $experience = $_POST['experience'];
    $consultation_fee = $_POST['consultation_fee'];
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        $error = "Email already exists!";
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Insert into users table
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password, phone, role) 
                VALUES (?, ?, ?, ?, 'doctor')
            ");
            $stmt->execute([$full_name, $email, $hashed_password, $phone]);
            $user_id = $pdo->lastInsertId();
            
            // Insert into doctors table
            $stmt = $pdo->prepare("
                INSERT INTO doctors (user_id, specialization, qualification, experience_years, consultation_fee) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $specialization, $qualification, $experience, $consultation_fee]);
            
            $pdo->commit();
            $success = "Doctor added successfully!";
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Error adding doctor: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Doctor | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
</head>
<body>

<?php 
$page_title = "Add New Doctor";
include '../includes/header.php'; 
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_doctors.php">Manage Doctors</a></li>
            <li><a href="manage_patients.php">Manage Patients</a></li>
            <li><a href="manage_appointments.php">Manage Appointments</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h1>Add New Doctor</h1>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="profile-form">
            <h3>Personal Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" name="email" id="email" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" name="password" id="password" required>
                    <small>Minimum 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" name="phone" id="phone" required>
                </div>
            </div>
            
            <h3 style="margin-top: 30px;">Professional Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="specialization">Specialization *</label>
                    <select name="specialization" id="specialization" required>
                        <option value="">Select Specialization</option>
                        <option value="Cardiology">Cardiology</option>
                        <option value="Dermatology">Dermatology</option>
                        <option value="Neurology">Neurology</option>
                        <option value="Pediatrics">Pediatrics</option>
                        <option value="Orthopedics">Orthopedics</option>
                        <option value="Ophthalmology">Ophthalmology</option>
                        <option value="General Medicine">General Medicine</option>
                        <option value="Dentistry">Dentistry</option>
                        <option value="Psychiatry">Psychiatry</option>
                        <option value="Gynecology">Gynecology</option>
                        <option value="ENT">ENT (Ear, Nose, Throat)</option>
                        <option value="Urology">Urology</option>
                        <option value="Gastroenterology">Gastroenterology</option>
                        <option value="Endocrinology">Endocrinology</option>
                        <option value="Rheumatology">Rheumatology</option>
                        <option value="Nephrology">Nephrology</option>
                        <option value="Oncology">Oncology</option>
                        <option value="Pulmonology">Pulmonology</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="experience">Experience (Years) *</label>
                    <input type="number" name="experience" id="experience" min="0" max="50" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="qualification">Qualifications *</label>
                    <input type="text" name="qualification" id="qualification" 
                           placeholder="MBBS, MD, etc." required>
                </div>
                
                <div class="form-group">
                    <label for="consultation_fee">Consultation Fee (₹) *</label>
                    <input type="number" name="consultation_fee" id="consultation_fee" 
                           min="0" step="50" required>
                </div>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-primary">Add Doctor</button>
                <a href="manage_doctors.php" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>