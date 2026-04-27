<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Fetch appointment details with all related information
$stmt = $pdo->prepare("
    SELECT a.*, 
           a.doctor_id, a.patient_id,
           d.specialization, d.qualification as doctor_qualification, d.experience_years, d.consultation_fee,
           u1.full_name as doctor_name, u1.email as doctor_email, u1.phone as doctor_phone,
           u2.full_name as patient_name, u2.email as patient_email, u2.phone as patient_phone,
           p.dob as patient_dob, p.gender as patient_gender, p.address as patient_address
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

// Handle status update from this page
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    
    $update_stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $appointment_id]);
    
    $success = "Appointment status updated to " . ucfirst($new_status) . "!";
    
    // Refresh data - use a new statement
    $refresh_stmt = $pdo->prepare("
        SELECT a.*, 
               a.doctor_id, a.patient_id,
               d.specialization, d.qualification as doctor_qualification, d.experience_years, d.consultation_fee,
               u1.full_name as doctor_name, u1.email as doctor_email, u1.phone as doctor_phone,
               u2.full_name as patient_name, u2.email as patient_email, u2.phone as patient_phone,
               p.dob as patient_dob, p.gender as patient_gender, p.address as patient_address
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u1 ON d.user_id = u1.id
        JOIN patients p ON a.patient_id = p.id
        JOIN users u2 ON p.user_id = u2.id
        WHERE a.id = ?
    ");
    $refresh_stmt->execute([$appointment_id]);
    $appointment = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
    <style>
        .detail-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .detail-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            font-size: 1.3rem;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .detail-item {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-item strong {
            color: #555;
            display: inline-block;
            width: 120px;
        }
        .status-selector {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .badge-large {
            font-size: 1.2rem;
            padding: 10px 20px;
        }
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php 
$page_title = "View Appointment";
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
            <h1>Appointment Details</h1>
            <div>
                <a href="manage_appointments.php" class="btn-secondary">← Back to Appointments</a>
                <a href="edit_appointment.php?id=<?php echo $appointment_id; ?>" class="btn-admin-edit">Edit Appointment</a>
            </div>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Appointment Status & ID -->
        <div class="detail-section">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <h2 style="border-bottom: none; margin-bottom: 0;">Appointment #AP<?php echo str_pad($appointment['id'], 4, '0', STR_PAD_LEFT); ?></h2>
                    <p style="color: #7f8c8d;">Created on: <?php echo date('d M Y, h:i A', strtotime($appointment['created_at'])); ?></p>
                </div>
                <div>
                    <span class="status-badge status-<?php echo $appointment['status']; ?> badge-large">
                        <?php echo ucfirst($appointment['status']); ?>
                    </span>
                </div>
            </div>
            
            <!-- Status Update Form -->
            <div class="status-selector">
                <form method="POST" action="" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Update Status</label>
                        <select name="status" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ddd;">
                            <option value="pending" <?php echo ($appointment['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo ($appointment['status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo ($appointment['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($appointment['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" name="update_status" class="btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Appointment Details -->
        <div class="detail-section">
            <h2>Appointment Information</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <strong>Date:</strong> <?php echo date('l, d F Y', strtotime($appointment['appointment_date'])); ?>
                </div>
                <div class="detail-item">
                    <strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                </div>
                <div class="detail-item">
                    <strong>Duration:</strong> 30 minutes
                </div>
                <div class="detail-item">
                    <strong>Fee:</strong> ₹<?php echo $appointment['consultation_fee']; ?>
                </div>
                <div class="detail-item" style="grid-column: span 2;">
                    <strong>Symptoms/Reason:</strong><br>
                    <?php echo nl2br(htmlspecialchars($appointment['symptoms'] ?: 'No symptoms specified')); ?>
                </div>
            </div>
        </div>
        
        <!-- Patient Information -->
        <div class="detail-section">
            <h2>Patient Information</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <strong>Name:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?>
                </div>
                <div class="detail-item">
                    <strong>Email:</strong> <?php echo htmlspecialchars($appointment['patient_email']); ?>
                </div>
                <div class="detail-item">
                    <strong>Phone:</strong> <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                </div>
                <div class="detail-item">
                    <strong>Gender:</strong> <?php echo $appointment['patient_gender'] ? ucfirst($appointment['patient_gender']) : 'Not specified'; ?>
                </div>
                <div class="detail-item">
                    <strong>Date of Birth:</strong> 
                    <?php 
                    if ($appointment['patient_dob']) {
                        echo date('d M Y', strtotime($appointment['patient_dob']));
                        // Calculate age
                        $dob = new DateTime($appointment['patient_dob']);
                        $now = new DateTime();
                        $age = $dob->diff($now)->y;
                        echo " ($age years)";
                    } else {
                        echo 'Not specified';
                    }
                    ?>
                </div>
                <div class="detail-item" style="grid-column: span 2;">
                    <strong>Address:</strong> <?php echo htmlspecialchars($appointment['patient_address'] ?: 'Not specified'); ?>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <a href="view_patient.php?id=<?php echo $appointment['patient_id']; ?>" class="btn-admin-view">View Full Patient Profile</a>
            </div>
        </div>
        
        <!-- Doctor Information -->
        <div class="detail-section">
            <h2>Doctor Information</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <strong>Name:</strong> Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                </div>
                <div class="detail-item">
                    <strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization']); ?>
                </div>
                <div class="detail-item">
                    <strong>Email:</strong> <?php echo htmlspecialchars($appointment['doctor_email']); ?>
                </div>
                <div class="detail-item">
                    <strong>Phone:</strong> <?php echo htmlspecialchars($appointment['doctor_phone']); ?>
                </div>
                <div class="detail-item">
                    <strong>Qualification:</strong> <?php echo htmlspecialchars($appointment['doctor_qualification']); ?>
                </div>
                <div class="detail-item">
                    <strong>Experience:</strong> <?php echo $appointment['experience_years']; ?> years
                </div>
            </div>
            <div style="margin-top: 15px;">
                <a href="edit_doctor.php?id=<?php echo $appointment['doctor_id']; ?>" class="btn-admin-view">View Doctor Profile</a>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <a href="edit_appointment.php?id=<?php echo $appointment_id; ?>" class="btn-admin-edit">Edit Appointment</a>
            <a href="manage_appointments.php" class="btn-secondary">Back to Appointments</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>