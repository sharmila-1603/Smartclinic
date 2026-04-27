<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../index.php");
    exit();
}

// Get parameters
$appointment_id = $_GET['id'] ?? 0;
$new_status = $_GET['status'] ?? '';

// Valid statuses
$valid_statuses = ['confirmed', 'completed', 'cancelled'];

if (!in_array($new_status, $valid_statuses)) {
    header("Location: dashboard.php?error=Invalid status");
    exit();
}

// Get doctor ID
$stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header("Location: dashboard.php?error=Doctor not found");
    exit();
}

// Get appointment details
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as patient_name, u.email as patient_email 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.id = ? AND a.doctor_id = ?
");
$stmt->execute([$appointment_id, $doctor['id']]);
$appointment = $stmt->fetch();

if (!$appointment) {
    header("Location: dashboard.php?error=Appointment not found");
    exit();
}

// If cancelling, ask for reason
if ($new_status == 'cancelled' && !isset($_POST['cancellation_reason'])) {
    // Show cancellation reason form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Cancel Appointment</title>
        <link rel="stylesheet" href="../style_project.css">
        <style>
            .reason-form {
                max-width: 500px;
                margin: 50px auto;
                padding: 30px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            }
            .reason-form h2 {
                color: #dc3545;
                margin-bottom: 20px;
            }
            .reason-form textarea {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 5px;
                margin: 15px 0;
                font-family: 'Poppins', sans-serif;
            }
            .reason-form button {
                padding: 10px 25px;
                margin-right: 10px;
            }
        </style>
    </head>
    <body>
        <?php include '../includes/header.php'; ?>
        <div class="reason-form">
            <h2>⚠️ Cancel Appointment</h2>
            <p><strong>Patient:</strong> <?php echo $appointment['patient_name']; ?></p>
            <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></p>
            <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></p>
            <form method="POST">
                <label>Reason for Cancellation *</label>
                <textarea name="cancellation_reason" rows="4" required 
                          placeholder="Please provide a reason for cancellation..."></textarea>
                <button type="submit" class="btn-danger" style="background:#dc3545; color:white; border:none; padding:10px 25px; border-radius:5px;">Confirm Cancellation</button>
                <a href="dashboard.php" class="btn-secondary">Go Back</a>
            </form>
        </div>
        <?php include '../includes/footer.php'; ?>
    </body>
    </html>
    <?php
    exit();
}

// Handle cancellation with reason
if ($new_status == 'cancelled' && isset($_POST['cancellation_reason'])) {
    $reason = $_POST['cancellation_reason'];
    
    // Update appointment with cancellation details
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = ?, cancelled_by = 'doctor', cancellation_reason = ?, 
            cancellation_notification_sent = 0
        WHERE id = ?
    ");
    $stmt->execute([$new_status, $reason, $appointment_id]);
    
    $_SESSION['success'] = "Appointment cancelled successfully. Patient will be notified.";
    header("Location: dashboard.php");
    exit();
}

// For confirm/complete (no reason needed)
if ($new_status != 'cancelled') {
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $appointment_id]);
    
    header("Location: dashboard.php?success=Appointment " . $new_status);
    exit();
}
?>