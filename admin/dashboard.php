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

// Get statistics with error handling
try {
    $total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $total_doctors = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
    $total_appointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $pending_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
    $today_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();

    // Get recent appointments
    $stmt = $pdo->query("
        SELECT a.*, d.specialization, 
               u1.full_name as doctor_name,
               u2.full_name as patient_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u1 ON d.user_id = u1.id
        JOIN patients p ON a.patient_id = p.id
        JOIN users u2 ON p.user_id = u2.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle database errors gracefully
    $total_patients = $total_doctors = $total_appointments = $pending_appointments = $today_appointments = 0;
    $recent_appointments = [];
    $db_error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
</head>
<body>

<?php 
$page_title = "Admin Dashboard";
include '../includes/header.php'; 
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="manage_doctors.php">Manage Doctors</a></li>
            <li><a href="manage_patients.php">Manage Patients</a></li>
            <li><a href="manage_appointments.php">Manage Appointments</a></li>
            <li><a href="appointment_reports.php">Appointment Reports</a></li>
            <li><a href="manage_feedback.php">Manage Feedback</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h1>Admin Dashboard</h1>
        <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></strong>!</p>
        
        <?php if (isset($db_error)): ?>
            <div class="alert alert-error"><?php echo $db_error; ?></div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <h3>Total Patients</h3>
                <p class="admin-count"><?php echo $total_patients; ?></p>
            </div>
            <div class="admin-stat-card">
                <h3>Total Doctors</h3>
                <p class="admin-count"><?php echo $total_doctors; ?></p>
            </div>
            <div class="admin-stat-card">
                <h3>Total Appointments</h3>
                <p class="admin-count"><?php echo $total_appointments; ?></p>
            </div>
            <div class="admin-stat-card">
                <h3>Pending Appointments</h3>
                <p class="admin-count"><?php echo $pending_appointments; ?></p>
            </div>
            <div class="admin-stat-card">
                <h3>Today's Appointments</h3>
                <p class="admin-count"><?php echo $today_appointments; ?></p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions-section">
            <h2>Quick Actions</h2><br>
            <div class="admin-actions-grid">
                <a href="add_doctor.php" class="btn-admin-add"> Add New Doctor</a>
                <a href="manage_doctors.php" class="btn-admin-view"> View All Doctors</a>
                <a href="manage_patients.php" class="btn-admin-view"> View All Patients</a>
                <a href="manage_appointments.php" class="btn-admin-view"> Manage Appointments</a>
            </div>
        </div>
        <br><br>
        <!-- Recent Appointments -->
        <div class="recent-appointments">
            <h2>Recent Appointments</h2>
            <?php if(empty($recent_appointments)): ?>
                <div class="admin-empty-state">
                    <h3>No appointments found</h3>
                    <p>There are no recent appointments to display.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_appointments as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                            <td><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="admin-actions">
                                    <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn-admin-view">View</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>