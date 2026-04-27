<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../index.php");
    exit();
}

// Get patient ID
$stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
$patient_id = $patient['id'];

// Get cancelled appointment ID
$appointment_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get cancelled appointment details
$stmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.full_name as doctor_name, d.consultation_fee,
           d.id as doctor_id, a.cancelled_by, a.cancellation_reason
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.id = ? AND a.patient_id = ? AND a.status = 'cancelled'
");
$stmt->execute([$appointment_id, $patient_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    $_SESSION['rebook_error'] = "Appointment not found or not cancelled.";
    header("Location: appointments.php");
    exit();
}

// Handle rebooking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $symptoms = $_POST['symptoms'];
    
    // Check if slot is available
    $check = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
        AND status IN ('pending', 'confirmed')
    ");
    $check->execute([$doctor_id, $appointment_date, $appointment_time]);
    
    if ($check->rowCount() > 0) {
        $error = "This time slot is already booked. Please choose another.";
    } else {
        // Create new appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $symptoms]);
        
        $new_appointment_id = $pdo->lastInsertId();
        
        // Update the cancelled appointment to show it was rebooked
        $stmt = $pdo->prepare("
            UPDATE appointments SET rebooked_as = ? WHERE id = ?
        ");
        $stmt->execute([$new_appointment_id, $appointment_id]);
        
        $_SESSION['rebook_success'] = "Appointment rebooked successfully! New appointment ID: #AP" . str_pad($new_appointment_id, 4, '0', STR_PAD_LEFT);
        header("Location: appointments.php");
        exit();
    }
}

// Get alternative doctors in same specialization
$stmt = $pdo->prepare("
    SELECT d.id, u.full_name, d.specialization, d.qualification, d.experience_years, d.consultation_fee
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.specialization = ?
    ORDER BY d.experience_years DESC
");
$stmt->execute([$appointment['specialization']]);
$doctors = $stmt->fetchAll();

// Get available time slots for next 30 days
$available_slots = [];
$time_slots = ['09:00:00', '10:00:00', '11:00:00', '12:00:00', '14:00:00', '15:00:00', '16:00:00', '17:00:00'];

for($i = 0; $i <= 30; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    foreach($time_slots as $slot) {
        $available_slots[] = [
            'date' => $date,
            'time' => $slot,
            'display' => date('d M Y', strtotime($date)) . ' at ' . date('h:i A', strtotime($slot))
        ];
    }
}

include '../includes/header.php';
?>

<style>
.info-box {
    background: #fff3cd;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #ffc107;
}
.info-box strong {
    color: #856404;
}
</style>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Patient Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="book_appointment.php">Book Appointment</a></li>
            <li><a href="appointments.php" class="active">My Appointments</a></li>
            <li><a href="profile.php">My Profile</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="admin-page-header">
            <h1>Rebook Cancelled Appointment</h1>
            <a href="appointments.php" class="btn-secondary">← Back to Appointments</a>
        </div>
        
        <!-- Cancellation Reason Info Box -->
        <?php if($appointment['cancelled_by'] != 'patient' && $appointment['cancellation_reason']): ?>
        <div class="info-box">
            <strong>ℹ️ Why your previous appointment was cancelled:</strong><br>
            <?php echo htmlspecialchars($appointment['cancellation_reason']); ?>
            <br><br>
            <small>Cancelled by: <?php echo ucfirst($appointment['cancelled_by']); ?></small>
        </div>
        <?php endif; ?>
        
        <!-- Cancelled Appointment Info -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; border-left: 4px solid #ffc107;">
            <h3 style="margin: 0 0 10px 0;">Previously Cancelled Appointment</h3>
            <p><strong>Doctor:</strong> Dr. <?php echo $appointment['doctor_name']; ?></p>
            <p><strong>Original Date:</strong> <?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?> at <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></p>
            <p><strong>Specialization:</strong> <?php echo $appointment['specialization']; ?></p>
        </div>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="appointment-form">
            <div class="form-group">
                <label for="doctor_id">Select Doctor *</label>
                <select name="doctor_id" id="doctor_id" required>
                    <option value="">Choose a doctor</option>
                    <?php foreach($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>" 
                            <?php echo ($doctor['id'] == $appointment['doctor_id']) ? 'selected' : ''; ?>>
                            Dr. <?php echo $doctor['full_name']; ?> - 
                            <?php echo $doctor['specialization']; ?> 
                            (<?php echo $doctor['experience_years']; ?> yrs exp • ₹<?php echo $doctor['consultation_fee']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="appointment_date">Appointment Date *</label>
                    <input type="date" name="appointment_date" id="appointment_date" 
                           min="<?php echo date('Y-m-d'); ?>" 
                           max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="appointment_time">Preferred Time *</label>
                    <select name="appointment_time" id="appointment_time" required>
                        <option value="">Select time slot</option>
                        <option value="09:00:00">09:00 AM</option>
                        <option value="10:00:00">10:00 AM</option>
                        <option value="11:00:00">11:00 AM</option>
                        <option value="12:00:00">12:00 PM</option>
                        <option value="14:00:00">02:00 PM</option>
                        <option value="15:00:00">03:00 PM</option>
                        <option value="16:00:00">04:00 PM</option>
                        <option value="17:00:00">05:00 PM</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="symptoms">Symptoms / Reason for Visit</label>
                <textarea name="symptoms" id="symptoms" rows="3" 
                          placeholder="Briefly describe your symptoms"><?php echo $appointment['symptoms']; ?></textarea>
            </div>
            
            <button type="submit" class="btn-primary">Confirm Rebooking</button>
        </form>
        
        <div style="margin-top: 30px; background: #f0f0f0; padding: 20px; border-radius: 10px;">
            <h4>💡 Tips for Rebooking:</h4>
            <ul style="margin: 10px 0 0 20px; color: #666;">
                <li>You can choose the same doctor or a different doctor in the same specialization</li>
                <li>Select a date within the next 30 days</li>
                <li>Choose any available time slot (9 AM - 5 PM)</li>
                <li>Your original appointment has been cancelled and will not be charged</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.getElementById('appointment_date').min = new Date().toISOString().split('T')[0];
const maxDate = new Date();
maxDate.setDate(maxDate.getDate() + 30);
document.getElementById('appointment_date').max = maxDate.toISOString().split('T')[0];
</script>

<?php include '../includes/footer.php'; ?>