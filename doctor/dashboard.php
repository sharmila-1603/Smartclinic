<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../index.php");
    exit();
}

// Get doctor ID and details
$stmt = $pdo->prepare("
    SELECT d.*, u.full_name, u.email, u.phone 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get today's appointments
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as patient_name, u.phone as patient_phone,
           p.dob, p.gender, p.address
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date = ?
    ORDER BY a.appointment_time
");
$stmt->execute([$doctor['id'], $today]);
$todays_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments (next 7 days)
$next_week = date('Y-m-d', strtotime('+7 days'));
$stmt_upcoming = $pdo->prepare("
    SELECT appointment_date, COUNT(*) as total
    FROM appointments
    WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ? 
    AND status IN ('pending', 'confirmed')
    GROUP BY appointment_date
    ORDER BY appointment_date
");
$stmt_upcoming->execute([$doctor['id'], $today, $next_week]);
$upcoming = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
</head>
<body>

<?php 
$page_title = "Doctor Dashboard";
include '../includes/header.php'; 
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Doctor Panel</h3>
        <ul>
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="schedule.php">My Schedule</a></li>
            <li><a href="my_reports.php">My Reports</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h1>Welcome, Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>!</h1>
        
        <!-- Doctor Info Cards -->
        <div class="doctor-info">
            <div class="info-card">
                <h3>Specialization</h3>
                <p><?php echo htmlspecialchars($doctor['specialization']); ?></p>
            </div>
            <div class="info-card">
                <h3>Experience</h3>
                <p><?php echo $doctor['experience_years']; ?> Years</p>
            </div>
            <div class="info-card">
                <h3>Today's Appointments</h3>
                <p><?php echo count($todays_appointments); ?></p>
            </div>
        </div>
        
        <!-- Upcoming Days Summary -->
        <div style="margin: 30px 0;">
            <h3>Next 7 Days Summary</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                <?php 
                $upcoming_dates = [];
                foreach($upcoming as $u) {
                    $upcoming_dates[$u['appointment_date']] = $u['total'];
                }
                
                for($i = 0; $i <= 7; $i++):
                    $date = date('Y-m-d', strtotime("+$i days"));
                    $display_date = date('D, d M', strtotime($date));
                    $count = isset($upcoming_dates[$date]) ? $upcoming_dates[$date] : 0;
                ?>
                <a href="schedule.php?date=<?php echo $date; ?>" 
                   style="background: <?php echo ($date == $today) ? '#3498db' : 'white'; ?>;
                          color: <?php echo ($date == $today) ? 'white' : '#333'; ?>;
                          padding: 15px; border-radius: 8px; text-decoration: none; 
                          box-shadow: 0 2px 5px rgba(0,0,0,0.1); min-width: 100px; text-align: center;">
                    <div style="font-weight: 600;"><?php echo $display_date; ?></div>
                    <div style="font-size: 20px; font-weight: bold; margin-top: 5px;"><?php echo $count; ?></div>
                    <div style="font-size: 12px;">appointments</div>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Today's Appointments -->
        <div class="todays-appointments">
            <h2>Today's Appointments (<?php echo date('d M Y'); ?>)</h2>
            
            <?php if(empty($todays_appointments)): ?>
                <div class="admin-empty-state">
                    <h3>No appointments scheduled for today</h3>
                    <p>You have no appointments today.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient Name</th>
                            <th>Phone</th>
                            <th>Age/Gender</th>
                            <th>Symptoms</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($todays_appointments as $appointment): 
                            // Calculate age from DOB
                            $age = '';
                            if ($appointment['dob']) {
                                $dob = new DateTime($appointment['dob']);
                                $now = new DateTime();
                                $age = $dob->diff($now)->y . ' yrs';
                            }
                        ?>
                        <tr>
                            <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['patient_phone']); ?></td>
                            <td>
                                <?php echo $age ? $age . ', ' : ''; ?>
                                <?php echo $appointment['gender'] ? ucfirst($appointment['gender']) : 'N/A'; ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($appointment['symptoms'], 0, 50)) . (strlen($appointment['symptoms']) > 50 ? '...' : ''); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="admin-actions">
                                    <?php if($appointment['status'] == 'pending'): ?>
                                        <a href="update_status.php?id=<?php echo $appointment['id']; ?>&status=confirmed" 
                                           class="btn-admin-edit" onclick="return confirm('Confirm this appointment?')">Confirm</a>
                                    <?php endif; ?>
                                    <?php if($appointment['status'] == 'confirmed'): ?>
                                        <a href="update_status.php?id=<?php echo $appointment['id']; ?>&status=completed" 
                                           class="btn-admin-view" onclick="return confirm('Mark as completed?')">Complete</a>
                                    <?php endif; ?>
                                    <?php if($appointment['status'] != 'completed' && $appointment['status'] != 'cancelled'): ?>
                                        <a href="update_status.php?id=<?php echo $appointment['id']; ?>&status=cancelled" 
                                           class="btn-admin-delete" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                                    <?php endif; ?>
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