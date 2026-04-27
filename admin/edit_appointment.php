<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Fetch current appointment data
$stmt = $pdo->prepare("
    SELECT a.*, 
           u1.full_name as doctor_name,
           u2.full_name as patient_name,
           a.created_at
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u1 ON d.user_id = u1.id
    JOIN patients p ON a.patient_id = p.id
    JOIN users u2 ON p.user_id = u2.id
    WHERE a.id = ?
");
$stmt->execute([$appointment_id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header("Location: manage_appointments.php?error=Appointment not found");
    exit();
}

// Get all doctors for dropdown
$doctors = $pdo->query("
    SELECT d.id, u.full_name, d.specialization 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    ORDER BY u.full_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get all patients for dropdown
$patients = $pdo->query("
    SELECT p.id, u.full_name 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY u.full_name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $symptoms = $_POST['symptoms'];
    $status = $_POST['status'];
    
    // Validate inputs
    $errors = [];
    if (empty($patient_id)) $errors[] = "Patient is required";
    if (empty($doctor_id)) $errors[] = "Doctor is required";
    if (empty($appointment_date)) $errors[] = "Appointment date is required";
    if (empty($appointment_time)) $errors[] = "Appointment time is required";
    
    if (empty($errors)) {
        // Check if the new time slot is available (excluding current appointment)
        $check_stmt = $pdo->prepare("
            SELECT id FROM appointments 
            WHERE doctor_id = ? 
            AND appointment_date = ? 
            AND appointment_time = ?
            AND id != ?
            AND status IN ('pending', 'confirmed')
        ");
        $check_stmt->execute([$doctor_id, $appointment_date, $appointment_time, $appointment_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "This time slot is already booked. Please choose another.";
        } else {
            try {
                // Update appointment
                $update_stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET patient_id = ?, doctor_id = ?, appointment_date = ?, 
                        appointment_time = ?, symptoms = ?, status = ?
                    WHERE id = ?
                ");
                $update_stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $symptoms, $status, $appointment_id]);
                
                $success = "Appointment updated successfully!";
                
                // Refresh data using a new statement
                $refresh_stmt = $pdo->prepare("
                    SELECT a.*, 
                           u1.full_name as doctor_name,
                           u2.full_name as patient_name,
                           a.created_at
                    FROM appointments a
                    JOIN doctors d ON a.doctor_id = d.id
                    JOIN users u1 ON d.user_id = u1.id
                    JOIN patients p ON a.patient_id = p.id
                    JOIN users u2 ON p.user_id = u2.id
                    WHERE a.id = ?
                ");
                $refresh_stmt->execute([$appointment_id]);
                $appointment = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
</head>
<body>

<?php 
$page_title = "Edit Appointment";
include '../includes/header.php'; 
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_doctors.php">Manage Doctors</a></li>
            <li><a href="manage_patients.php">Manage Patients</a></li>
            <li><a href="manage_appointments.php" class="active">Manage Appointments</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="admin-page-header">
            <h1>Edit Appointment #AP<?php echo str_pad($appointment['id'], 4, '0', STR_PAD_LEFT); ?></h1>
            <div>
                <a href="view_appointment.php?id=<?php echo $appointment_id; ?>" class="btn-secondary">← Back to Appointment</a>
                <a href="manage_appointments.php" class="btn-secondary">Manage Appointments</a>
            </div>
        </div>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="profile-form" style="max-width: 900px;">
            <div class="form-row">
                <div class="form-group">
                    <label for="patient_id">Patient *</label>
                    <select name="patient_id" id="patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" 
                                <?php echo ($patient['id'] == $appointment['patient_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="doctor_id">Doctor *</label>
                    <select name="doctor_id" id="doctor_id" required>
                        <option value="">Select Doctor</option>
                        <?php foreach($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" 
                                <?php echo ($doctor['id'] == $appointment['doctor_id']) ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doctor['full_name']); ?> (<?php echo $doctor['specialization']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="appointment_date">Appointment Date *</label>
                    <input type="date" name="appointment_date" id="appointment_date" 
                           value="<?php echo $appointment['appointment_date']; ?>" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="appointment_time">Appointment Time *</label>
                    <select name="appointment_time" id="appointment_time" required>
                        <option value="">Select Time</option>
                        <option value="09:00:00" <?php echo ($appointment['appointment_time'] == '09:00:00') ? 'selected' : ''; ?>>09:00 AM</option>
                        <option value="10:00:00" <?php echo ($appointment['appointment_time'] == '10:00:00') ? 'selected' : ''; ?>>10:00 AM</option>
                        <option value="11:00:00" <?php echo ($appointment['appointment_time'] == '11:00:00') ? 'selected' : ''; ?>>11:00 AM</option>
                        <option value="12:00:00" <?php echo ($appointment['appointment_time'] == '12:00:00') ? 'selected' : ''; ?>>12:00 PM</option>
                        <option value="14:00:00" <?php echo ($appointment['appointment_time'] == '14:00:00') ? 'selected' : ''; ?>>02:00 PM</option>
                        <option value="15:00:00" <?php echo ($appointment['appointment_time'] == '15:00:00') ? 'selected' : ''; ?>>03:00 PM</option>
                        <option value="16:00:00" <?php echo ($appointment['appointment_time'] == '16:00:00') ? 'selected' : ''; ?>>04:00 PM</option>
                        <option value="17:00:00" <?php echo ($appointment['appointment_time'] == '17:00:00') ? 'selected' : ''; ?>>05:00 PM</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select name="status" id="status" required>
                        <option value="pending" <?php echo ($appointment['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo ($appointment['status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo ($appointment['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($appointment['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="symptoms">Symptoms / Reason for Visit</label>
                <textarea name="symptoms" id="symptoms" rows="4" placeholder="Describe symptoms or reason for visit"><?php echo htmlspecialchars($appointment['symptoms']); ?></textarea>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-primary">Update Appointment</button>
                <a href="view_appointment.php?id=<?php echo $appointment_id; ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
        
        <!-- Appointment Info -->
        <div style="margin-top: 30px; background: #f8f9fa; padding: 20px; border-radius: 10px;">
            <h3 style="margin-bottom: 15px;">Appointment Information</h3>
            <p><strong>Created:</strong> <?php echo date('d M Y, h:i A', strtotime($appointment['created_at'])); ?></p>
            <p><strong>Patient:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?></p>
            <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
            <p><strong>Current Status:</strong> 
                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                    <?php echo ucfirst($appointment['status']); ?>
                </span>
            </p>
        </div>
    </div>
</div>

<script>
// Set minimum date to today
document.getElementById('appointment_date').min = new Date().toISOString().split('T')[0];

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    let patient = document.getElementById('patient_id').value;
    let doctor = document.getElementById('doctor_id').value;
    let date = document.getElementById('appointment_date').value;
    let time = document.getElementById('appointment_time').value;
    
    if (!patient || !doctor || !date || !time) {
        e.preventDefault();
        alert('Please fill in all required fields');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
?>
</body>
</html>