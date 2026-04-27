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

// Get appointment details from URL
$appointment_id = isset($_GET['appointment_id']) ? $_GET['appointment_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'cancel';

// Get appointment details
$stmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.full_name as doctor_name, d.consultation_fee
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.id = ? AND a.patient_id = ?
");
$stmt->execute([$appointment_id, $patient_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    header("Location: appointments.php");
    exit();
}

// If user chooses to cancel only
if ($action == 'cancel_only') {
    // Cancel the appointment
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$appointment_id]);
    
    $_SESSION['cancellation_msg'] = "Your appointment has been cancelled successfully.";
    header("Location: appointments.php");
    exit();
}

// If user chooses to rebook with alternative
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $alternative_type = $_POST['alternative_type']; // 'doctor' or 'timeslot'
    $new_value = $_POST['new_value'];
    
    // Cancel the old appointment
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$appointment_id]);
    
    if ($alternative_type == 'doctor') {
        // Book with alternative doctor
        $doctor_id = $new_value;
        $appointment_date = $appointment['appointment_date'];
        $appointment_time = $appointment['appointment_time'];
        
        // Check if slot available with new doctor
        $check = $pdo->prepare("
            SELECT id FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
            AND status IN ('pending', 'confirmed')
        ");
        $check->execute([$doctor_id, $appointment_date, $appointment_time]);
        
        if ($check->rowCount() == 0) {
            // Create new appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $appointment['symptoms']]);
            
            $_SESSION['booking_success'] = "Your appointment has been rebooked with the new doctor successfully!";
        } else {
            $_SESSION['booking_error'] = "The selected time slot is not available. Please try a different option.";
        }
        
    } elseif ($alternative_type == 'timeslot') {
        // Book with same doctor but different time
        $doctor_id = $appointment['doctor_id'];
        $new_date = explode('|', $new_value)[0];
        $new_time = explode('|', $new_value)[1];
        
        // Check if slot available
        $check = $pdo->prepare("
            SELECT id FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
            AND status IN ('pending', 'confirmed')
        ");
        $check->execute([$doctor_id, $new_date, $new_time]);
        
        if ($check->rowCount() == 0) {
            // Create new appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$patient_id, $doctor_id, $new_date, $new_time, $appointment['symptoms']]);
            
            $_SESSION['booking_success'] = "Your appointment has been rebooked for " . 
                                           date('d M Y', strtotime($new_date)) . " at " . 
                                           date('h:i A', strtotime($new_time)) . " successfully!";
        } else {
            $_SESSION['booking_error'] = "The selected time slot is not available. Please try a different option.";
        }
    }
    
    header("Location: appointments.php");
    exit();
}

// Find alternative doctors in same specialization
$stmt = $pdo->prepare("
    SELECT d.id, u.full_name, d.specialization, d.qualification, d.experience_years, d.consultation_fee
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.specialization = ? AND d.id != ?
    ORDER BY d.experience_years DESC
");
$stmt->execute([$appointment['specialization'], $appointment['doctor_id']]);
$alternative_doctors = $stmt->fetchAll();

// Find alternative time slots for the same doctor (next 7 days)
$alternative_slots = [];
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+7 days'));
$time_slots = ['09:00:00', '10:00:00', '11:00:00', '12:00:00', '14:00:00', '15:00:00', '16:00:00', '17:00:00'];

// Get booked slots
$stmt = $pdo->prepare("
    SELECT appointment_date, appointment_time 
    FROM appointments 
    WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ?
    AND status IN ('pending', 'confirmed')
");
$stmt->execute([$appointment['doctor_id'], $start_date, $end_date]);
$booked_slots = $stmt->fetchAll();

$booked = [];
foreach($booked_slots as $bs) {
    $booked[$bs['appointment_date']][$bs['appointment_time']] = true;
}

// Generate available slots
for($i = 0; $i <= 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    foreach($time_slots as $slot) {
        if(!isset($booked[$date][$slot])) {
            $alternative_slots[] = [
                'date' => $date,
                'time' => $slot,
                'display' => date('d M Y', strtotime($date)) . ' at ' . date('h:i A', strtotime($slot))
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel & Rebook | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
    <style>
        .suggestion-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .appointment-details {
            background: linear-gradient(135deg, #f8f9fa, #fff);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 5px solid #dc3545;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .appointment-details h3 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .detail-item {
            flex: 1;
            background: white;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .detail-item strong {
            display: block;
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .detail-item span {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .options-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .option-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #eee;
        }
        
        .option-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .option-body {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .doctor-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }
        
        .doctor-item:hover {
            background: #f8f9fa;
        }
        
        .doctor-info h4 {
            margin: 0 0 5px;
            color: #333;
        }
        
        .doctor-info p {
            margin: 0;
            font-size: 0.85rem;
            color: #666;
        }
        
        .select-btn {
            background: var(--primary-color);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .select-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .slot-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .slot-time {
            font-weight: 600;
            color: #333;
        }
        
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn-cancel-only {
            background: #dc3545;
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-cancel-only:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        @media (max-width: 768px) {
            .options-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="suggestion-container">
    <div class="appointment-details">
        <h3>⚠️ Cancelling Appointment</h3>
        <p>You are about to cancel your appointment with <strong>Dr. <?php echo $appointment['doctor_name']; ?></strong></p>
        <div class="detail-row">
            <div class="detail-item">
                <strong>📅 Date</strong>
                <span><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></span>
            </div>
            <div class="detail-item">
                <strong>⏰ Time</strong>
                <span><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
            </div>
            <div class="detail-item">
                <strong>🏥 Specialization</strong>
                <span><?php echo $appointment['specialization']; ?></span>
            </div>
            <div class="detail-item">
                <strong>💰 Fee</strong>
                <span>₹<?php echo $appointment['consultation_fee']; ?></span>
            </div>
        </div>
    </div>
    
    <h3 style="margin-bottom: 20px;">💡 Would you like to rebook with an alternative?</h3>
    
    <div class="options-container">
        <!-- Alternative Doctors in Same Specialization -->
        <div class="option-card">
            <div class="option-header">
                👨‍⚕️ Same Specialization - Different Doctor
            </div>
            <div class="option-body">
                <?php if(empty($alternative_doctors)): ?>
                    <div class="empty-message">
                        No other doctors available in <?php echo $appointment['specialization']; ?>
                    </div>
                <?php else: ?>
                    <?php foreach($alternative_doctors as $doctor): ?>
                    <div class="doctor-item">
                        <div class="doctor-info">
                            <h4>Dr. <?php echo $doctor['full_name']; ?></h4>
                            <p><?php echo $doctor['experience_years']; ?> years exp • ₹<?php echo $doctor['consultation_fee']; ?></p>
                        </div>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="alternative_type" value="doctor">
                            <input type="hidden" name="new_value" value="<?php echo $doctor['id']; ?>">
                            <button type="submit" class="select-btn">Select</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Alternative Time Slots -->
        <div class="option-card">
            <div class="option-header">
                ⏰ Same Doctor - Different Time
            </div>
            <div class="option-body">
                <?php if(empty($alternative_slots)): ?>
                    <div class="empty-message">
                        No available time slots in the next 7 days
                    </div>
                <?php else: ?>
                    <?php foreach(array_slice($alternative_slots, 0, 10) as $slot): ?>
                    <div class="slot-item">
                        <span class="slot-time">📅 <?php echo $slot['display']; ?></span>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="alternative_type" value="timeslot">
                            <input type="hidden" name="new_value" value="<?php echo $slot['date'] . '|' . $slot['time']; ?>">
                            <button type="submit" class="select-btn">Select</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="action-buttons">
        <a href="appointments.php?cancel_only=<?php echo $appointment_id; ?>" class="btn-cancel-only" 
           onclick="return confirm('Are you sure you want to cancel without rebooking?')">
            ❌ Cancel Only
        </a>
        <a href="appointments.php" class="btn-back">
            ← Go Back
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>