<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../index.php");
    exit();
}

// Get patient data
$stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
$patient_id = $patient['id'];

// Handle dismiss notification (when patient clicks "Okay, Got it")
if (isset($_GET['dismiss_notification'])) {
    $notification_id = $_GET['dismiss_notification'];
    $stmt = $pdo->prepare("UPDATE appointments SET cancellation_notification_sent = 1 WHERE id = ? AND patient_id = ?");
    $stmt->execute([$notification_id, $patient_id]);
    header("Location: dashboard.php");
    exit();
}

// Handle dismiss all notifications
if (isset($_GET['dismiss_all'])) {
    $stmt = $pdo->prepare("UPDATE appointments SET cancellation_notification_sent = 1 WHERE patient_id = ? AND status = 'cancelled' AND cancellation_notification_sent = 0");
    $stmt->execute([$patient_id]);
    header("Location: dashboard.php");
    exit();
}

// Get upcoming appointments (pending and confirmed)
$stmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.full_name as doctor_name
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN users u ON d.user_id = u.id 
    WHERE a.patient_id = ? AND a.status IN ('pending', 'confirmed')
    ORDER BY a.appointment_date, a.appointment_time
");
$stmt->execute([$patient_id]);
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get UNREAD cancellation notifications (not dismissed yet)
// THESE STAY ON DASHBOARD UNTIL ACTION
$stmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.full_name as doctor_name,
           a.cancelled_by, a.cancellation_reason
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN users u ON d.user_id = u.id 
    WHERE a.patient_id = ? AND a.status = 'cancelled' 
    AND a.cancellation_notification_sent = 0
    AND a.cancelled_by != 'patient'
    ORDER BY a.appointment_date DESC
");
$stmt->execute([$patient_id]);
$pending_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get dismissed cancellations (already seen - shown in separate table)
$stmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.full_name as doctor_name,
           a.cancelled_by, a.cancellation_reason
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN users u ON d.user_id = u.id 
    WHERE a.patient_id = ? AND a.status = 'cancelled' 
    AND a.cancellation_notification_sent = 1
    AND a.rebooked_as IS NULL
    ORDER BY a.appointment_date DESC
    LIMIT 5
");
$stmt->execute([$patient_id]);
$dismissed_cancelled = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed appointments
$stmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.full_name as doctor_name
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN users u ON d.user_id = u.id 
    WHERE a.patient_id = ? AND a.status = 'completed'
    ORDER BY a.appointment_date DESC
    LIMIT 5
");
$stmt->execute([$patient_id]);
$completed_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$has_pending_notifications = count($pending_notifications) > 0;
$has_dismissed_cancelled = count($dismissed_cancelled) > 0;

include '../includes/header.php';
?>

<style>
/* Dashboard Layout */
.dashboard-container {
    display: flex;
    min-height: calc(100vh - 200px);
    margin: 20px;
    gap: 20px;
}

.main-content {
    flex: 1;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Stats Cards */
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border-top: 4px solid #3498db;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.stat-card h3 {
    font-size: 1rem;
    color: #7f8c8d;
    margin-bottom: 10px;
    font-weight: 500;
}

.stat-card .count {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

/* PERSISTENT ALERT MESSAGE - STAYS UNTIL ACTION */
.persistent-alert {
    background: linear-gradient(135deg, #fff3cd, #ffe69e);
    border-left: 5px solid #ffc107;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    animation: slideDown 0.5s ease;
}

.persistent-alert h3 {
    margin: 0 0 10px 0;
    color: #856404;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.persistent-alert p {
    margin: 0;
    color: #856404;
    font-size: 0.9rem;
}

.alert-details {
    background: rgba(0,0,0,0.02);
    padding: 12px;
    margin-top: 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    border-left: 3px solid #dc3545;
}

.alert-details strong {
    color: #dc3545;
}

.alert-actions {
    display: flex;
    gap: 15px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.btn-rebook-alert {
    background: #28a745;
    color: white;
    padding: 10px 25px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-rebook-alert:hover {
    background: #1e7e34;
    transform: translateY(-2px);
}

.btn-okay-alert {
    background: #6c757d;
    color: white;
    padding: 10px 25px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-okay-alert:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.btn-dismiss-all {
    background: #17a2b8;
    color: white;
    padding: 6px 16px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-block;
    margin-left: 10px;
}

.btn-dismiss-all:hover {
    background: #138496;
}

/* Section Headers */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 30px 0 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e0e0e0;
}

.section-header h2 {
    margin: 0;
    font-size: 1.3rem;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-header .badge {
    background: #e74c3c;
    color: white;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
}

/* Tables */
.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.dashboard-table th {
    background: #f8f9fa;
    color: #2c3e50;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    font-size: 0.85rem;
    border-bottom: 2px solid #e0e0e0;
}

.dashboard-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #f0f0f0;
    color: #555;
    font-size: 0.9rem;
}

.dashboard-table tr:hover {
    background: #f8f9fa;
}

/* Status Badges */
.status-pending {
    background: #fff3cd;
    color: #856404;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.status-confirmed {
    background: #d4edda;
    color: #155724;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.status-completed {
    background: #d1ecf1;
    color: #0c5460;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

/* Action Buttons */
.btn-rebook-small {
    background: #28a745;
    color: white;
    padding: 5px 12px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-rebook-small:hover {
    background: #1e7e34;
    transform: scale(1.05);
}

.btn-view-all {
    background: transparent;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
    padding: 5px 12px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.btn-view-all:hover {
    background: var(--primary-color);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
}

.empty-state p {
    margin: 0;
}

.cancellation-reason {
    font-size: 0.7rem;
    color: #e74c3c;
    margin-top: 5px;
    display: block;
    cursor: pointer;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        margin-bottom: 20px;
    }
    
    .dashboard-table {
        display: block;
        overflow-x: auto;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .alert-actions {
        flex-direction: column;
    }
    
    .btn-rebook-alert,
    .btn-okay-alert {
        text-align: center;
        justify-content: center;
    }
}
</style>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Patient Panel</h3>
        <ul>
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="book_appointment.php">Book Appointment</a></li>
            <li><a href="appointments.php">My Appointments</a></li>
            <li><a href="profile.php">My Profile</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h1>Welcome, <?php echo $_SESSION['full_name']; ?>!</h1>
        
        <!-- ============================================ -->
        <!-- PERSISTENT ALERT - STAYS UNTIL ACTION -->
        <!-- ============================================ -->
        <?php foreach($pending_notifications as $notification): ?>
            <div class="persistent-alert">
                <h3>
                    ⚠️ Appointment Cancelled
                    <?php if(count($pending_notifications) > 1): ?>
                        <span class="badge">New</span>
                    <?php endif; ?>
                </h3>
                <p>
                    Your appointment with <strong>Dr. <?php echo $notification['doctor_name']; ?></strong> 
                    on <strong><?php echo date('d M Y', strtotime($notification['appointment_date'])); ?></strong> 
                    at <strong><?php echo date('h:i A', strtotime($notification['appointment_time'])); ?></strong> 
                    has been cancelled.
                </p>
                
                <div class="alert-details">
                    <strong>Cancelled by:</strong> 
                    <?php 
                    if($notification['cancelled_by'] == 'doctor') echo 'Doctor 👨‍⚕️';
                    elseif($notification['cancelled_by'] == 'admin') echo 'Clinic Administrator 👨‍💼';
                    ?>
                    <br>
                    <?php if($notification['cancellation_reason']): ?>
                        <strong>Reason:</strong> <?php echo htmlspecialchars($notification['cancellation_reason']); ?>
                    <?php endif; ?>
                </div>
                
                <div class="alert-actions">
                    <a href="rebook_appointment.php?id=<?php echo $notification['id']; ?>" class="btn-rebook-alert">
                        🔄 Rebook Appointment
                    </a>
                    <a href="dashboard.php?dismiss_notification=<?php echo $notification['id']; ?>" class="btn-okay-alert">
                        ✓ Okay, Got it
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3>📅 Upcoming</h3>
                <p class="count"><?php echo count($upcoming_appointments); ?></p>
            </div>
            <div class="stat-card">
                <h3>❌ Cancelled</h3>
                <p class="count"><?php echo count($pending_notifications) + count($dismissed_cancelled); ?></p>
            </div>
            <div class="stat-card">
                <h3>✅ Completed</h3>
                <p class="count"><?php echo count($completed_appointments); ?></p>
            </div>
            <div class="stat-card">
                <h3>⏳ Pending</h3>
                <p class="count">
                    <?php 
                    $pending = array_filter($upcoming_appointments, function($a) {
                        return $a['status'] == 'pending';
                    });
                    echo count($pending);
                    ?>
                </p>
            </div>
        </div>
        
        <!-- UPCOMING APPOINTMENTS SECTION -->
        <div class="section-header">
            <h2>📋 Upcoming Appointments</h2>
            <a href="appointments.php" class="btn-view-all">View All →</a>
        </div>
        
        <?php if(empty($upcoming_appointments)): ?>
            <div class="empty-state">
                <p>No upcoming appointments. <a href="book_appointment.php">Book one now!</a></p>
            </div>
        <?php else: ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($upcoming_appointments as $appointment): ?>
                    <tr>
                        <td><strong>Dr. <?php echo $appointment['doctor_name']; ?></strong></td>
                        <td><?php echo $appointment['specialization']; ?></td>
                        <td><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                        <td>
                            <span class="status-<?php echo $appointment['status']; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
                <!-- CANCELLED APPOINTMENTS SECTION (DISMISSED - ALREADY SEEN) -->
        <?php if($has_dismissed_cancelled): ?>
        <div class="section-header">
            <h2>
                ❌ Cancelled Appointments
                <?php if($has_dismissed_cancelled): ?>
                    <span class="badge"><?php echo count($dismissed_cancelled); ?> appointments</span>
                <?php endif; ?>
            </h2>
            <a href="appointments.php?status=cancelled" class="btn-view-all">View All →</a>
        </div>
        
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Original Date</th>
                    <th>Time</th>
                    <th>Cancelled By</th>
                    <th>Reason</th>
                    <th>Action</th>
                </thead>
            <tbody>
                <?php foreach($dismissed_cancelled as $cancelled): ?>
                <tr>
                    <td><strong>Dr. <?php echo $cancelled['doctor_name']; ?></strong></td>
                    <td><?php echo $cancelled['specialization']; ?></td>
                    <td><?php echo date('d M Y', strtotime($cancelled['appointment_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($cancelled['appointment_time'])); ?></td>
                    <td>
                        <?php 
                        if($cancelled['cancelled_by'] == 'doctor') echo '👨‍⚕️ Doctor';
                        elseif($cancelled['cancelled_by'] == 'admin') echo '👨‍💼 Clinic Administrator';
                        else echo '👤 You';
                        ?>
                    </td>
                    <td>
                        <?php if($cancelled['cancellation_reason']): ?>
                            <span class="reason-tooltip" 
                                  data-reason="<?php echo htmlspecialchars($cancelled['cancellation_reason']); ?>"
                                  onclick="showReason(this)" style="color:#e74c3c; cursor:pointer;">
                                📝 View Reason
                            </span>
                        <?php else: ?>
                            <span style="color: #999;">No reason</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="rebook_appointment.php?id=<?php echo $cancelled['id']; ?>" class="btn-rebook-small">
                            🔄 Rebook
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        
        <!-- RECENT COMPLETED APPOINTMENTS -->
        <div class="section-header">
            <h2>✅ Recent Completed Appointments</h2>
            <a href="appointments.php?status=completed" class="btn-view-all">View All →</a>
        </div>
        
        <?php if(empty($completed_appointments)): ?>
            <div class="empty-state">
                <p>No completed appointments yet.</p>
            </div>
        <?php else: ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($completed_appointments as $completed): ?>
                    <tr>
                        <td><strong>Dr. <?php echo $completed['doctor_name']; ?></strong></td>
                        <td><?php echo $completed['specialization']; ?></td>
                        <td><?php echo date('d M Y', strtotime($completed['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($completed['appointment_time'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div style="margin-top: 30px; text-align: center;">
            <a href="book_appointment.php" class="btn-primary">+ Book New Appointment</a>
            <?php if($has_pending_notifications): ?>
                <a href="dashboard.php?dismiss_all=1" class="btn-dismiss-all" style="margin-left: 10px;">✓ Dismiss All Notifications</a>
            <?php endif; ?>
            <a href="appointments.php" class="btn-secondary" style="margin-left: 10px;">📋 View All Appointments</a>
        </div>
    </div>
</div>
<script>
// Show cancellation reason in modal
function showReason(element) {
    const reason = element.getAttribute('data-reason');
    
    // Create modal if it doesn't exist
    let modal = document.getElementById('reasonModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'reasonModal';
        modal.className = 'reason-modal';
        modal.innerHTML = `
            <div class="reason-modal-content">
                <span class="reason-modal-close-btn">&times;</span>
                <h3>📋 Cancellation Reason</h3>
                <p id="reasonText"></p>
                <button class="reason-modal-close">Close</button>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Add event listeners
        modal.querySelector('.reason-modal-close-btn').onclick = function() {
            modal.style.display = 'none';
        };
        modal.querySelector('.reason-modal-close').onclick = function() {
            modal.style.display = 'none';
        };
        modal.onclick = function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };
    }
    
    // Set reason text and show modal
    document.getElementById('reasonText').innerHTML = reason;
    modal.style.display = 'flex';
}
</script>

<?php include '../includes/footer.php'; ?>