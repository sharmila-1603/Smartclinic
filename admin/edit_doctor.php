<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get doctor ID from URL
$doctor_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Fetch doctor details
$stmt = $pdo->prepare("
    SELECT d.*, u.full_name, u.email, u.phone 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header("Location: manage_doctors.php?error=Doctor not found");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $specialization = $_POST['specialization'];
    $qualification = $_POST['qualification'];
    $experience = $_POST['experience'];
    $consultation_fee = $_POST['consultation_fee'];
    
    try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Update users table
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $doctor['user_id']]);
    
    // Update doctors table
    $stmt2 = $pdo->prepare("
        UPDATE doctors 
        SET specialization = ?, qualification = ?, experience_years = ?, consultation_fee = ? 
        WHERE id = ?
    ");
    $stmt2->execute([$specialization, $qualification, $experience, $consultation_fee, $doctor_id]);
    
    $pdo->commit();
    $success = "Doctor updated successfully!";
    
    // Refresh doctor data - using NEW statement variable
    $stmt3 = $pdo->prepare("
        SELECT d.*, u.full_name, u.email, u.phone 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.id = ?
    ");
    $stmt3->execute([$doctor_id]);
    $doctor = $stmt3->fetch();
    
} catch (Exception $e) {
    $pdo->rollBack();
    $error = "Error updating doctor: " . $e->getMessage();
}
}

include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_doctors.php" class="active">Manage Doctors</a></li>
            <li><a href="manage_patients.php">Manage Patients</a></li>
            <li><a href="manage_appointments.php">Manage Appointments</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="admin-page-header">
            <h1>Edit Doctor</h1>
            <a href="manage_doctors.php" class="btn-secondary">← Back to Doctors</a>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="profile-form">
            <h3>Personal Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" 
                           value="<?php echo htmlspecialchars($doctor['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($doctor['email']); ?>" disabled>
                    <small>Email cannot be changed</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" name="phone" id="phone" 
                           value="<?php echo htmlspecialchars($doctor['phone']); ?>" required>
                </div>
            </div>
            
            <h3 style="margin-top: 30px;">Professional Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="specialization">Specialization *</label>
                    <select name="specialization" id="specialization" required>
                        <option value="">Select Specialization</option>
                        <option value="Cardiology" <?php echo ($doctor['specialization'] == 'Cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                        <option value="Neurology" <?php echo ($doctor['specialization'] == 'Neurology') ? 'selected' : ''; ?>>Neurology</option>
                        <option value="Pediatrics" <?php echo ($doctor['specialization'] == 'Pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                        <option value="Orthopedics" <?php echo ($doctor['specialization'] == 'Orthopedics') ? 'selected' : ''; ?>>Orthopedics</option>
                        <option value="Dermatology" <?php echo ($doctor['specialization'] == 'Dermatology') ? 'selected' : ''; ?>>Dermatology</option>
                        <option value="Ophthalmology" <?php echo ($doctor['specialization'] == 'Ophthalmology') ? 'selected' : ''; ?>>Ophthalmology</option>
                        <option value="Psychiatry" <?php echo ($doctor['specialization'] == 'Psychiatry') ? 'selected' : ''; ?>>Psychiatry</option>
                        <option value="Gynecology" <?php echo ($doctor['specialization'] == 'Gynecology') ? 'selected' : ''; ?>>Gynecology</option>
                        <option value="Dentistry" <?php echo ($doctor['specialization'] == 'Dentistry') ? 'selected' : ''; ?>>Dentistry</option>
                        <option value="General Medicine" <?php echo ($doctor['specialization'] == 'General Medicine') ? 'selected' : ''; ?>>General Medicine</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="experience">Experience (Years) *</label>
                    <input type="number" name="experience" id="experience" 
                           value="<?php echo $doctor['experience_years']; ?>" min="0" max="50" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="qualification">Qualifications *</label>
                    <input type="text" name="qualification" id="qualification" 
                           value="<?php echo htmlspecialchars($doctor['qualification']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="consultation_fee">Consultation Fee (₹) *</label>
                    <input type="number" name="consultation_fee" id="consultation_fee" 
                           value="<?php echo $doctor['consultation_fee']; ?>" min="0" step="50" required>
                </div>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-primary">Update Doctor</button>
                <a href="manage_doctors.php" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>