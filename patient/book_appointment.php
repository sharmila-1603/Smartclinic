<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    // Store error message and redirect
    if (isset($_SESSION['user_id']) && ($_SESSION['role'] == 'doctor' || $_SESSION['role'] == 'admin')) {
        $_SESSION['booking_error'] = "Only patients can book appointments. Please logout and login with a patient account.";
    } else {
        $_SESSION['booking_error'] = "Please login as a patient to book appointments.";
    }
    header("Location: ../index.php");
    exit();
}

// Get patient ID
$stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    $_SESSION['booking_error'] = "Patient record not found. Please contact support.";
    header("Location: ../index.php");
    exit();
}

$patient_id = $patient['id'];

// Get all doctors grouped by specialization
$stmt = $pdo->prepare("
    SELECT d.id, u.full_name, d.specialization, d.qualification, d.experience_years, d.consultation_fee 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    ORDER BY d.specialization, u.full_name
");
$stmt->execute();
$all_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group doctors by specialization for dropdown
$doctors_by_category = [];
foreach ($all_doctors as $doctor) {
    $doctors_by_category[$doctor['specialization']][] = $doctor;
}

// Get selected doctor details for display
$selected_doctor_id = isset($_POST['doctor_id']) ? $_POST['doctor_id'] : (isset($_GET['doctor_id']) ? $_GET['doctor_id'] : '');
$selected_doctor = null;
if ($selected_doctor_id) {
    foreach ($all_doctors as $doctor) {
        if ($doctor['id'] == $selected_doctor_id) {
            $selected_doctor = $doctor;
            break;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $symptoms = $_POST['symptoms'];
    
    // Check if slot is available
    $stmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND appointment_time = ?
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
    
    if ($stmt->rowCount() > 0) {
        $error = "This time slot is already booked. Please choose another.";
    } else {
        // Insert appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments 
            (patient_id, doctor_id, appointment_date, appointment_time, symptoms) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $symptoms]);
        
        $success = "Appointment booked successfully! Waiting for doctor confirmation.";
    }
}

include '../includes/header.php';
?>

<style>
/* Dropdown Styles */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
}

.styled-select {
    width: 100%;
    padding: 14px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    font-family: 'Poppins', sans-serif;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.styled-select:hover {
    border-color: var(--primary-color);
}

.styled-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

/* Selected Doctor Info Card */
.selected-doctor-card {
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    border-left: 4px solid var(--success-color);
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    animation: slideDown 0.3s ease;
}

.selected-doctor-card h4 {
    margin: 0 0 5px 0;
    color: #2e7d32;
    font-size: 1rem;
}

.selected-doctor-card p {
    margin: 0;
    color: #1b5e20;
    font-weight: 500;
}

.selected-doctor-details {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 8px;
    font-size: 0.85rem;
    color: #2e7d32;
}

.selected-doctor-details span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Time Slots Grid */
.time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.time-slot {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.time-slot:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.time-slot.selected {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

/* Hidden Radio */
.hidden-radio {
    display: none;
}

/* Booking Tips */
.booking-tips {
    margin-top: 30px;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    border-left: 4px solid var(--info-color);
}

.booking-tips h4 {
    margin: 0 0 10px 0;
    color: var(--dark-color);
}

.booking-tips ul {
    margin: 0;
    padding-left: 20px;
    color: #666;
}

.booking-tips li {
    margin: 5px 0;
}

/* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}
</style>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Patient Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="book_appointment.php" class="active">Book Appointment</a></li>
            <li><a href="appointments.php">My Appointments</a></li>
            <li><a href="profile.php">My Profile</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h1>Book Appointment</h1>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="appointmentForm">
            <!-- Doctor Selection - Dropdown with Categories -->
            <div class="form-group">
                <label for="doctor_id">Select Doctor *</label>
                <select name="doctor_id" id="doctor_id" class="styled-select" required onchange="updateDoctorInfo(this.value)">
                    <option value="">-- Select a Doctor --</option>
                    <?php foreach($doctors_by_category as $specialization => $doctors): ?>
                        <optgroup label="🏥 <?php echo $specialization; ?> (<?php echo count($doctors); ?> doctors)">
                            <?php foreach($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" 
                                    data-exp="<?php echo $doctor['experience_years']; ?>"
                                    data-fee="<?php echo $doctor['consultation_fee']; ?>"
                                    data-qual="<?php echo htmlspecialchars($doctor['qualification']); ?>"
                                    <?php echo ($selected_doctor_id == $doctor['id']) ? 'selected' : ''; ?>>
                                    Dr. <?php echo $doctor['full_name']; ?> - <?php echo $doctor['experience_years']; ?> yrs exp • ₹<?php echo $doctor['consultation_fee']; ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Selected Doctor Info Card -->
            <div id="selectedDoctorInfo" class="selected-doctor-card" style="display: none;">
                <h4>✓ Selected Doctor</h4>
                <p id="selectedDoctorName"></p>
                <div id="selectedDoctorDetails" class="selected-doctor-details"></div>
            </div>
            
            <!-- Appointment Date -->
            <div class="form-group">
                <label for="appointment_date">Appointment Date *</label>
                <input type="date" name="appointment_date" id="appointment_date" 
                       class="styled-select"
                       min="<?php echo date('Y-m-d'); ?>" 
                       max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                       required>
            </div>
            
            <!-- Appointment Time -->
            <div class="form-group">
                <label for="appointment_time">Preferred Time *</label>
                <select name="appointment_time" id="appointment_time" class="styled-select" required>
                    <option value="">-- Select Time Slot --</option>
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
            
            <!-- Symptoms -->
            <div class="form-group">
                <label for="symptoms">Symptoms / Reason for Visit</label>
                <textarea name="symptoms" id="symptoms" rows="3" 
                          class="styled-select"
                          placeholder="Briefly describe your symptoms (optional)"></textarea>
            </div>
            
            <button type="submit" class="btn-primary">Book Appointment</button>
        </form>
        
        <!-- Booking Tips -->
        <div class="booking-tips">
            <h4>💡 Booking Tips</h4>
            <ul>
                <li>Select a doctor from any specialization using the dropdown above</li>
                <li>Choose a date within the next 30 days</li>
                <li>Available time slots: 9:00 AM to 5:00 PM (Monday to Friday)</li>
                <li>You'll receive confirmation once the doctor approves your appointment</li>
                <li>You can view and manage your appointments in "My Appointments" section</li>
            </ul>
        </div>
    </div>
</div>

<script>
// Set date restrictions
document.getElementById('appointment_date').min = new Date().toISOString().split('T')[0];
const maxDate = new Date();
maxDate.setDate(maxDate.getDate() + 30);
document.getElementById('appointment_date').max = maxDate.toISOString().split('T')[0];

// Doctor data storage
const doctorData = {};
<?php foreach($all_doctors as $doctor): ?>
doctorData[<?php echo $doctor['id']; ?>] = {
    name: "Dr. <?php echo addslashes($doctor['full_name']); ?>",
    specialization: "<?php echo $doctor['specialization']; ?>",
    experience: <?php echo $doctor['experience_years']; ?>,
    fee: <?php echo $doctor['consultation_fee']; ?>,
    qualification: "<?php echo addslashes($doctor['qualification']); ?>"
};
<?php endforeach; ?>

// Update doctor info when selection changes
function updateDoctorInfo(doctorId) {
    const infoDiv = document.getElementById('selectedDoctorInfo');
    const nameSpan = document.getElementById('selectedDoctorName');
    const detailsDiv = document.getElementById('selectedDoctorDetails');
    
    if (doctorId && doctorData[doctorId]) {
        const doctor = doctorData[doctorId];
        nameSpan.innerHTML = `<strong>${doctor.name}</strong> (${doctor.specialization})`;
        detailsDiv.innerHTML = `
            <span>📅 ${doctor.experience} years experience</span>
            <span>💰 ₹${doctor.fee} consultation fee</span>
            <span>📋 ${doctor.qualification}</span>
        `;
        infoDiv.style.display = 'block';
        
        // Smooth scroll to info
        infoDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        infoDiv.style.display = 'none';
    }
}

// Check if doctor is pre-selected (from URL parameter)
<?php if($selected_doctor_id): ?>
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('doctor_id');
    if (select) {
        select.value = '<?php echo $selected_doctor_id; ?>';
        updateDoctorInfo('<?php echo $selected_doctor_id; ?>');
    }
});
<?php endif; ?>

// Form validation
document.getElementById('appointmentForm').addEventListener('submit', function(e) {
    const doctor = document.getElementById('doctor_id').value;
    const date = document.getElementById('appointment_date').value;
    const time = document.getElementById('appointment_time').value;
    
    if (!doctor) {
        e.preventDefault();
        alert('Please select a doctor');
        return false;
    }
    
    if (!date) {
        e.preventDefault();
        alert('Please select an appointment date');
        return false;
    }
    
    if (!time) {
        e.preventDefault();
        alert('Please select an appointment time');
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>