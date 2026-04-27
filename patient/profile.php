<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../index.php");
    exit();
}

// Get patient data
$stmt = $pdo->prepare("
    SELECT u.*, p.dob, p.gender, p.address 
    FROM users u 
    JOIN patients p ON u.id = p.user_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    
    // Update users table
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $_SESSION['user_id']]);
    
    // Update patients table
    $stmt = $pdo->prepare("UPDATE patients SET dob = ?, gender = ?, address = ? WHERE user_id = ?");
    $stmt->execute([$dob, $gender, $address, $_SESSION['user_id']]);
    
    // Update session
    $_SESSION['full_name'] = $full_name;
    
    $success = "Profile updated successfully!";
}
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Patient Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="book_appointment.php">Book Appointment</a></li>
            <li><a href="appointments.php">My Appointments</a></li>
            <li><a href="profile.php" class="active">My Profile</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h1>My Profile</h1>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="profile-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" 
                           value="<?php echo htmlspecialchars($patient['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" value="<?php echo $patient['email']; ?>" disabled>
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
                <textarea name="address" id="address" rows="3"><?php echo htmlspecialchars($patient['address']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Account Created</label>
                <input type="text" value="<?php echo date('d M Y', strtotime($patient['created_at'])); ?>" disabled>
            </div>
            
            <button type="submit" class="btn-primary">Update Profile</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>