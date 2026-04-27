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

// Handle cancel appointment (patient self-cancel)
if (isset($_GET['cancel_id'])) {
    $appointment_id = $_GET['cancel_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND patient_id = ?");
    $stmt->execute([$appointment_id, $patient_id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled', cancelled_by = 'patient', 
                cancellation_reason = 'Cancelled by patient', cancellation_notification_sent = 1
            WHERE id = ?
        ");
        $stmt->execute([$appointment_id]);
        
        $_SESSION['cancellation_msg'] = "Your appointment has been cancelled successfully.";
        header("Location: appointments.php");
        exit();
    } else {
        $error = "Appointment not found or you don't have permission to cancel it.";
    }
}

// Handle cancel rebooked appointment (cancel the new appointment created after rebooking)
if (isset($_GET['cancel_rebooked_id'])) {
    $appointment_id = $_GET['cancel_rebooked_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND patient_id = ?");
    $stmt->execute([$appointment_id, $patient_id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled', cancelled_by = 'patient', 
                cancellation_reason = 'Cancelled by patient', cancellation_notification_sent = 1
            WHERE id = ?
        ");
        $stmt->execute([$appointment_id]);
        
        $_SESSION['cancellation_msg'] = "Your rebooked appointment has been cancelled successfully.";
        header("Location: appointments.php");
        exit();
    } else {
        $error = "Appointment not found or you don't have permission to cancel it.";
    }
}

// Mark notifications as read when viewed
if (isset($_GET['mark_read'])) {
    $stmt = $pdo->prepare("UPDATE appointments SET cancellation_notification_sent = 1 WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
}

// Get all appointments with cancellation details AND rebooked appointment status
$stmt = $pdo->prepare("
    SELECT a.*, d.specialization, u.full_name as doctor_name,
           a.rebooked_as, a.cancelled_by, a.cancellation_reason,
           r.status as rebooked_status,
           r.appointment_date as rebooked_date,
           r.appointment_time as rebooked_time,
           r.symptoms as rebooked_symptoms,
           ru.full_name as rebooked_doctor_name,
           rd.specialization as rebooked_specialization
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN users u ON d.user_id = u.id
    LEFT JOIN appointments r ON a.rebooked_as = r.id
    LEFT JOIN doctors rd ON r.doctor_id = rd.id
    LEFT JOIN users ru ON rd.user_id = ru.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for any unread cancellation notifications (only from doctor/admin)
$has_unread = false;
foreach ($appointments as $apt) {
    if ($apt['status'] == 'cancelled' && $apt['cancellation_notification_sent'] == 0 && $apt['cancelled_by'] != 'patient') {
        $has_unread = true;
        break;
    }
}

include '../includes/header.php';
?>

<style>
/* Page Layout */
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
    min-height: 500px;
}

/* Status Badges */
.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-completed { background: #d1ecf1; color: #0c5460; }
.status-cancelled { background: #f8d7da; color: #721c24; }

/* Rebooked Info */
.rebooked-info {
    background: #e8f5e9;
    border-left: 3px solid #28a745;
    padding: 8px 12px;
    margin-top: 8px;
    border-radius: 6px;
    font-size: 12px;
}

.rebooked-details {
    margin-top: 5px;
}

.new-appointment {
    color: #28a745;
    font-weight: 600;
}

.rebooked-status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 8px;
}

.rebooked-status-pending { background: #fff3cd; color: #856404; }
.rebooked-status-confirmed { background: #d4edda; color: #155724; }
.rebooked-status-completed { background: #d1ecf1; color: #0c5460; }
.rebooked-status-cancelled { background: #f8d7da; color: #721c24; }

/* Cancellation Alert */
.cancellation-alert {
    background: #fff3cd;
    border-left: 5px solid #ffc107;
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 10px;
}

.cancellation-detail {
    background: #f8f9fa;
    border-left: 4px solid #dc3545;
    padding: 12px;
    margin-top: 12px;
    border-radius: 8px;
    font-size: 13px;
}

/* Action Buttons */
.btn-rebook {
    background: #28a745;
    color: white;
    padding: 6px 12px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
    display: inline-block;
    transition: all 0.3s ease;
    text-align: center;
}

.btn-rebook:hover {
    background: #1e7e34;
    transform: translateY(-2px);
}

.btn-cancel-action {
    background: #dc3545;
    color: white;
    padding: 6px 12px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
    display: inline-block;
    transition: all 0.3s ease;
    text-align: center;
    border: none;
    cursor: pointer;
}

.btn-cancel-action:hover {
    background: #c82333;
    transform: translateY(-2px);
}

.btn-cancel-action:disabled {
    background: #cccccc;
    color: #666666;
    cursor: not-allowed;
    opacity: 0.7;
}

.btn-cancel-action:disabled:hover {
    transform: none;
    box-shadow: none;
}

.btn-view-rebooked {
    background: #17a2b8;
    color: white;
    padding: 6px 12px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
    display: inline-block;
    transition: all 0.3s ease;
    cursor: pointer;
    text-align: center;
    border: none;
}

.btn-view-rebooked:hover {
    background: #138496;
    transform: translateY(-2px);
}

.action-buttons {
    min-width: 140px;
    vertical-align: top;
}

.button-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.button-group .btn-view-rebooked,
.button-group .btn-cancel-action {
    width: 100%;
    text-align: center;
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.notification-badge {
    background: #dc3545;
    color: white;
    border-radius: 50%;
    padding: 2px 8px;
    font-size: 12px;
    margin-left: 10px;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.admin-table th {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    font-size: 0.85rem;
}

.admin-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    color: #555;
    font-size: 0.9rem;
    vertical-align: middle;
}

.admin-table tr:hover {
    background: #f8f9fa;
}

.alert {
    padding: 12px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
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

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 15px;
}

.quick-actions {
    margin-top: 30px;
    text-align: center;
}

/* Modal Styles */
.rebooked-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.rebooked-modal-content {
    background: white;
    border-radius: 20px;
    max-width: 500px;
    width: 90%;
    position: relative;
    animation: slideUp 0.3s ease;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    overflow: hidden;
}

.rebooked-modal-header {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.rebooked-modal-header h3 {
    margin: 0;
    font-size: 1.3rem;
}

.rebooked-modal-close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.rebooked-modal-close:hover {
    transform: scale(1.1);
}

.rebooked-modal-body {
    padding: 25px;
}

.rebooked-detail-item {
    display: flex;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.rebooked-detail-label {
    width: 200px;
    font-weight: 600;
    color: #555;
}

.rebooked-detail-value {
    flex: 1;
    color: #333;
}

.rebooked-status {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.rebooked-modal-footer {
    padding: 15px 25px 25px;
    text-align: center;
    border-top: 1px solid #f0f0f0;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
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
    
    .admin-table {
        display: block;
        overflow-x: auto;
    }
    
    .rebooked-detail-item {
        flex-direction: column;
    }
    
    .rebooked-detail-label {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .action-buttons {
        min-width: 120px;
    }
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
        <h1>
            My Appointments
            <?php if($has_unread): ?>
                <span class="notification-badge">New</span>
            <?php endif; ?>
        </h1>
        
        <?php if(isset($_SESSION['cancellation_msg'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['cancellation_msg']; unset($_SESSION['cancellation_msg']); ?></div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['rebook_success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['rebook_success']; unset($_SESSION['rebook_success']); ?></div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['rebook_error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['rebook_error']; unset($_SESSION['rebook_error']); ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Display Cancellation Notifications (Only from Doctor/Admin) -->
        <?php foreach($appointments as $appointment): ?>
            <?php if($appointment['status'] == 'cancelled' && $appointment['cancellation_notification_sent'] == 0 && $appointment['cancelled_by'] != 'patient'): ?>
                <div class="cancellation-alert">
                    <h4>⚠️ Appointment Cancelled</h4>
                    <p>Your appointment with <strong>Dr. <?php echo $appointment['doctor_name']; ?></strong> 
                       on <strong><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></strong> 
                       at <strong><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></strong> 
                       has been cancelled.</p>
                    
                    <div class="cancellation-detail">
                        <strong>Cancelled by:</strong> 
                        <?php 
                        if($appointment['cancelled_by'] == 'doctor') echo 'Doctor';
                        elseif($appointment['cancelled_by'] == 'admin') echo 'Clinic Administrator';
                        ?><br>
                        
                        <?php if($appointment['cancellation_reason'] && $appointment['cancelled_by'] != 'patient'): ?>
                            <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['cancellation_reason']); ?><br>
                        <?php endif; ?>
                        
                        <br>
                        <a href="rebook_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn-rebook">🔄 Rebook Appointment</a>
                    </div>
                </div>
                <?php 
                // Mark as read after displaying
                $update = $pdo->prepare("UPDATE appointments SET cancellation_notification_sent = 1, notified_at = NOW() WHERE id = ?");
                $update->execute([$appointment['id']]);
                ?>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if(empty($appointments)): ?>
            <div class="empty-state">
                <h3>📅 No Appointments Found</h3>
                <p>You haven't booked any appointments yet.</p>
                <a href="book_appointment.php" class="btn-primary">Book Your First Appointment</a>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date & Time</th>
                        <th>Symptoms</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php foreach($appointments as $appointment): ?>
                     <tr>
                        <td class="appointment-id">#AP<?php echo str_pad($appointment['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td class="doctor-name"><strong>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></strong></td>
                        <td class="specialization"><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                        <td class="datetime">
                            <?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?><br>
                            <small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                        </td>
                        <td class="symptoms">
                            <?php 
                            if (!empty($appointment['symptoms'])) {
                                echo htmlspecialchars(substr($appointment['symptoms'], 0, 40));
                                if (strlen($appointment['symptoms']) > 40) echo '...';
                            } else {
                                echo '<span style="color:#999;">Not specified</span>';
                            }
                            ?>
                        </td>
                        <td class="status-cell">
                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                            
                            <!-- Show Rebooked Appointment Details with Status -->
                            <?php if($appointment['status'] == 'cancelled' && $appointment['rebooked_as']): ?>
                                <div class="rebooked-info">
                                    <strong>🔄 Rebooked:</strong>
                                    <div class="rebooked-details">
                                        <span class="new-appointment">
                                            #AP<?php echo str_pad($appointment['rebooked_as'], 4, '0', STR_PAD_LEFT); ?>
                                        </span>
                                        <?php if($appointment['rebooked_doctor_name']): ?>
                                            with Dr. <?php echo $appointment['rebooked_doctor_name']; ?>
                                        <?php endif; ?>
                                        <span class="rebooked-status-badge rebooked-status-<?php echo $appointment['rebooked_status']; ?>">
                                            <?php echo ucfirst($appointment['rebooked_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            <?php if($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed'): ?>
                                <!-- Cancel button for active appointments -->
                                <a href="appointments.php?cancel_id=<?php echo $appointment['id']; ?>" 
                                   class="btn-cancel-action"
                                   onclick="return confirm('Cancel this appointment?\n\nDoctor: Dr. <?php echo $appointment['doctor_name']; ?>\nDate: <?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?>\nTime: <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>')">
                                    ❌ Cancel
                                </a>
                            
                            <?php elseif($appointment['status'] == 'cancelled' && !$appointment['rebooked_as']): ?>
                                <!-- Rebook button for cancelled appointments that are NOT rebooked -->
                                <a href="rebook_appointment.php?id=<?php echo $appointment['id']; ?>" 
                                   class="btn-rebook">
                                    🔄 Rebook
                                </a>
                            
                            <?php elseif($appointment['status'] == 'cancelled' && $appointment['rebooked_as']): ?>
                                <!-- For cancelled appointments that ARE rebooked - Stacked buttons -->
                                <div class="button-group">
                                    <!-- View Rebooked Button -->
                                    <button onclick="showRebookedDetails(<?php echo htmlspecialchars(json_encode($appointment)); ?>)" 
                                            class="btn-view-rebooked">
                                        👁️ View Rebooked
                                    </button>
                                    
                                    <!-- Cancel Rebooked Button - Always shows as button -->
                                    <?php if($appointment['rebooked_status'] == 'pending' || $appointment['rebooked_status'] == 'confirmed'): ?>
                                        <!-- Active cancel button for rebooked appointment that is pending/confirmed -->
                                        <a href="appointments.php?cancel_rebooked_id=<?php echo $appointment['rebooked_as']; ?>" 
                                           class="btn-cancel-action"
                                           onclick="return confirm('Cancel your rebooked appointment?\n\nDoctor: Dr. <?php echo $appointment['rebooked_doctor_name']; ?>\nDate: <?php echo date('d M Y', strtotime($appointment['rebooked_date'])); ?>\nTime: <?php echo date('h:i A', strtotime($appointment['rebooked_time'])); ?>')">
                                            ❌ Cancel Rebooked
                                        </a>
                                    <?php else: ?>
                                        <!-- Disabled button for rebooked appointment that is already cancelled or completed -->
                                        <button class="btn-cancel-action" disabled>
                                            ❌ Cancel Rebooked (<?php echo ucfirst($appointment['rebooked_status']); ?>)
                                        </button>
                                    <?php endif; ?>
                                </div>
                            
                            <?php elseif($appointment['status'] == 'completed'): ?>
                                <span style="color:#28a745; font-size:12px;">✓ Completed</span>
                            
                            <?php else: ?>
                                <span style="color:#999; font-size:12px;">No actions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
             </table>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="book_appointment.php" class="btn-primary">+ Book New Appointment</a>
            <?php if($has_unread): ?>
                <a href="?mark_read=1" class="btn-secondary" style="margin-left: 10px;">✓ Mark All Notifications Read</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for Viewing Rebooked Appointment Details -->
<div id="rebookedModal" class="rebooked-modal">
    <div class="rebooked-modal-content">
        <div class="rebooked-modal-header">
            <h3>📋 Rebooked Appointment Details</h3>
            <span class="rebooked-modal-close" onclick="closeRebookedModal()">&times;</span>
        </div>
        <div class="rebooked-modal-body" id="rebookedModalBody">
            <!-- Dynamic content will be inserted here -->
        </div>
        <div class="rebooked-modal-footer">
            <button class="btn-primary" onclick="closeRebookedModal()">Close</button>
        </div>
    </div>
</div>

<script>
// Show Rebooked Appointment Details Modal
function showRebookedDetails(appointment) {
    const modal = document.getElementById('rebookedModal');
    const modalBody = document.getElementById('rebookedModalBody');
    
    // Format status with appropriate color
    const statusClass = `rebooked-status rebooked-status-${appointment.rebooked_status}`;
    const statusText = appointment.rebooked_status ? appointment.rebooked_status.toUpperCase() : 'N/A';
    
    // Format date and time
    const formatDate = (dateStr) => {
        if (!dateStr) return 'N/A';
        return new Date(dateStr).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
    };
    
    const formatTime = (timeStr) => {
        if (!timeStr) return 'N/A';
        return new Date('2000-01-01T' + timeStr).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
    };
    
    // Create modal content
    modalBody.innerHTML = `
        <div class="rebooked-detail-item">
            <div class="rebooked-detail-label">Appointment ID:</div>
            <div class="rebooked-detail-value">
                <strong>#AP${String(appointment.rebooked_as).padStart(4, '0')}</strong>
            </div>
        </div>
        <div class="rebooked-detail-item">
            <div class="rebooked-detail-label">Doctor:</div>
            <div class="rebooked-detail-value">
                <strong>Dr. ${appointment.rebooked_doctor_name || 'N/A'}</strong>
            </div>
        </div>
        <div class="rebooked-detail-item">
            <div class="rebooked-detail-label">Specialization:</div>
            <div class="rebooked-detail-value">
                ${appointment.rebooked_specialization || 'N/A'}
            </div>
        </div>
        <div class="rebooked-detail-item">
            <div class="rebooked-detail-label">Date & Time:</div>
            <div class="rebooked-detail-value">
                ${formatDate(appointment.rebooked_date)}<br>
                <small>${formatTime(appointment.rebooked_time)}</small>
            </div>
        </div>
        <div class="rebooked-detail-item">
            <div class="rebooked-detail-label">Status:</div>
            <div class="rebooked-detail-value">
                <span class="${statusClass}">${statusText}</span>
            </div>
        </div>
        <div class="rebooked-detail-item">
            <div class="rebooked-detail-label">Symptoms:</div>
            <div class="rebooked-detail-value">
                ${appointment.rebooked_symptoms || 'Not specified'}
            </div>
        </div>
    `;
    
    modal.style.display = 'flex';
}

// Close Modal
function closeRebookedModal() {
    document.getElementById('rebookedModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('rebookedModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>