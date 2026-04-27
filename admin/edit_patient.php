<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get patient ID from URL
$patient_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    
    // Get user_id from patients table
    $stmt = $pdo->prepare("SELECT user_id FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $patient['user_id'];
    
    // Update users table
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $user_id]);
    
    // Update patients table
    $stmt = $pdo->prepare("UPDATE patients SET dob = ?, gender = ?, address = ? WHERE id = ?");
    $stmt->execute([$dob, $gender, $address, $patient_id]);
    
    $success = "Patient information updated successfully!";
}

// Fetch current patient data
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name, u.email, u.phone 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header("Location: manage_patients.php?error=Patient not found");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
</head>
<body>

<?php 
$page_title = "Edit Patient";
include '../includes/header.php'; 
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_doctors.php">Manage Doctors</a></li>
            <li><a href="manage_patients.php" class="active">Manage Patients</a></li>
            <li><a href="manage_appointments.php">Manage Appointments</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="admin-page-header">
            <h1>Edit Patient: <?php echo htmlspecialchars($patient['full_name']); ?></h1>
            <a href="view_patient.php?id=<?php echo $patient_id; ?>" class="btn-secondary">← Back to Patient</a>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="profile-form" style="max-width: 800px;">
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" 
                           value="<?php echo htmlspecialchars($patient['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($patient['email']); ?>" disabled>
                    <small>Email cannot be changed</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" name="phone" id="phone" 
                           value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" name="dob" id="dob" 
                           value="<?php echo $patient['dob']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select name="gender" id="gender">
                        <option value="">Select Gender</option>
                        <option value="male" <?php echo ($patient['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($patient['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($patient['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <textarea name="address" id="address" rows="4"><?php echo htmlspecialchars($patient['address']); ?></textarea>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-primary">Update Patient</button>
                <a href="view_patient.php?id=<?php echo $patient_id; ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>