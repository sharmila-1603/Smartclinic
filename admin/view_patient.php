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

// Fetch patient details
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name, u.email, u.phone, u.created_at 
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

// Get patient's appointment history
$stmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.full_name as doctor_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
</head>
<body>

<?php 
$page_title = "View Patient";
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
            <h1>Patient Details</h1>
            <a href="manage_patients.php" class="btn-secondary">← Back to Patients</a>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Patient Information -->
            <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                <h2 style="color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #3498db;">Personal Information</h2>
                
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="padding: 10px; font-weight: 600; width: 40%;">Patient ID:</td>
                        <td style="padding: 10px;">#P<?php echo str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: 600;">Full Name:</td>
                        <td style="padding: 10px;"><?php echo htmlspecialchars($patient['full_name']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: 600;">Email:</td>
                        <td style="padding: 10px;"><?php echo htmlspecialchars($patient['email']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: 600;">Phone:</td>
                        <td style="padding: 10px;"><?php echo htmlspecialchars($patient['phone']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: 600;">Gender:</td>
                        <td style="padding: 10px;"><?php echo $patient['gender'] ? ucfirst($patient['gender']) : 'Not specified'; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: 600;">Date of Birth:</td>
                        <td style="padding: 10px;"><?php echo $patient['dob'] ? date('d M Y', strtotime($patient['dob'])) : 'Not specified'; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: 600;">Address:</td>
                        <td style="padding: 10px;"><?php echo $patient['address'] ? htmlspecialchars($patient['address']) : 'Not specified'; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: 600;">Registered On:</td>
                        <td style="padding: 10px;"><?php echo date('d M Y, h:i A', strtotime($patient['created_at'])); ?></td>
                    </tr>
                </table>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="btn-admin-edit">Edit Patient</a>
                </div>
            </div>
            
            <!-- Appointment History -->
            <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                <h2 style="color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #3498db;">Recent Appointments</h2>
                
                <?php if(empty($appointments)): ?>
                    <p style="text-align: center; padding: 40px; color: #7f8c8d;">No appointment history found.</p>
                <?php else: ?>
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($appointments as $apt): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($apt['appointment_date'])); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                <td><span class="status-badge status-<?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>