<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../index.php");
    exit();
}

// Get doctor data
$stmt = $pdo->prepare("
    SELECT d.*, u.full_name, u.email, u.phone 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $qualification = $_POST['qualification'];
    $experience = $_POST['experience'];
    $consultation_fee = $_POST['consultation_fee'];
    
    // Update users table
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $_SESSION['user_id']]);
    
    // Update doctors table
    $stmt = $pdo->prepare("UPDATE doctors SET qualification = ?, experience_years = ?, consultation_fee = ? WHERE id = ?");
    $stmt->execute([$qualification, $experience, $consultation_fee, $doctor['id']]);
    
    $_SESSION['full_name'] = $full_name;
    $success = "Profile updated successfully!";
}

include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Doctor Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="schedule.php">My Schedule</a></li>
            <li><a href="my_reports.php">My Reports</a></li>
            <li><a href="profile.php" class="active">My Profile</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h1>My Profile</h1>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" class="profile-form">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo $doctor['full_name']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?php echo $doctor['email']; ?>" disabled>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?php echo $doctor['phone']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" value="<?php echo $doctor['specialization']; ?>" disabled>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Qualifications</label>
                    <input type="text" name="qualification" value="<?php echo $doctor['qualification']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Experience (Years)</label>
                    <input type="number" name="experience" value="<?php echo $doctor['experience_years']; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Consultation Fee (₹)</label>
                <input type="number" name="consultation_fee" value="<?php echo $doctor['consultation_fee']; ?>" required>
            </div>
            
            <button type="submit" class="btn-primary">Update Profile</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>