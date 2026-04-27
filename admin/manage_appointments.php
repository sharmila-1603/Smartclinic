<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle status update with cancellation reason
if (isset($_GET['update_status'])) {
    $appointment_id = $_GET['id'];
    $new_status = $_GET['status'];
    
    if ($new_status == 'cancelled') {
        // Show modal or form for cancellation reason
        if (!isset($_POST['cancellation_reason'])) {
            ?>
            <div id="cancelModal" class="modal" style="display:block;">
                <div class="modal-content" style="max-width:500px;">
                    <span class="close" onclick="document.getElementById('cancelModal').style.display='none'">&times;</span>
                    <h3 style="color:#dc3545;">⚠️ Cancel Appointment</h3>
                    <p><strong>Appointment ID:</strong> #AP<?php echo str_pad($appointment_id, 4, '0', STR_PAD_LEFT); ?></p>
                    <form method="POST" action="manage_appointments.php?update_status&id=<?php echo $appointment_id; ?>&status=cancelled">
                        <label>Reason for Cancellation *</label>
                        <textarea name="cancellation_reason" rows="4" required style="width:100%; margin:10px 0; padding:10px; border:1px solid #ddd; border-radius:5px;"></textarea>
                        <button type="submit" class="btn-danger" style="background:#dc3545; color:white; border:none; padding:10px 20px; border-radius:5px; cursor:pointer;">Confirm Cancellation</button>
                        <a href="manage_appointments.php" class="btn-secondary" style="padding:10px 20px;">Cancel</a>
                    </form>
                </div>
            </div>
            <?php
            // Continue with the rest of the page
        } else {
            $reason = $_POST['cancellation_reason'];
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = ?, cancelled_by = 'admin', cancellation_reason = ?, 
                    cancellation_notification_sent = 0
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $reason, $appointment_id]);
            $success = "Appointment cancelled with reason provided.";
        }
    } else {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $appointment_id]);
        $success = "Appointment status updated to " . $new_status . "!";
    }
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_doctor = isset($_GET['doctor']) ? $_GET['doctor'] : '';
$filter_patient = isset($_GET['patient']) ? $_GET['patient'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_specialization = isset($_GET['specialization']) ? $_GET['specialization'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$sql = "
    SELECT a.*, d.specialization, 
           u1.full_name as doctor_name,
           u2.full_name as patient_name,
           a.cancelled_by, a.cancellation_reason
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u1 ON d.user_id = u1.id
    JOIN patients p ON a.patient_id = p.id
    JOIN users u2 ON p.user_id = u2.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u2.full_name LIKE ? OR u1.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_status) {
    $sql .= " AND a.status = ?";
    $params[] = $filter_status;
}

if ($filter_doctor) {
    $sql .= " AND a.doctor_id = ?";
    $params[] = $filter_doctor;
}

if ($filter_patient) {
    $sql .= " AND a.patient_id = ?";
    $params[] = $filter_patient;
}

if ($filter_specialization) {
    $sql .= " AND d.specialization = ?";
    $params[] = $filter_specialization;
}

if ($filter_date_from) {
    $sql .= " AND a.appointment_date >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $sql .= " AND a.appointment_date <= ?";
    $params[] = $filter_date_to;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctors for filter dropdown
$doctors = $pdo->query("SELECT d.id, u.full_name FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.full_name")->fetchAll();

// Get patients for filter dropdown
$patients = $pdo->query("SELECT p.id, u.full_name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.full_name")->fetchAll();

// Get specializations for filter dropdown
$specializations = $pdo->query("SELECT DISTINCT specialization FROM doctors ORDER BY specialization")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
    <style>
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-filter {
            background: var(--primary-color);
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-reset {
            background: #1788ec;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .active-filters {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .filter-badge {
            background: var(--primary-color);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .quick-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .quick-filter-btn {
            padding: 8px 20px;
            border: 1px solid var(--primary-color);
            border-radius: 30px;
            background: white;
            color: var(--primary-color);
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .quick-filter-btn:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .quick-filter-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        .reason-tooltip {
            cursor: pointer;
            border-bottom: 1px dashed #dc3545;
        }
        
        .reason-tooltip:hover {
            background: #fff3cd;
        }
    </style>
</head>
<body>

<?php 
$page_title = "Manage Appointments";
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
            <li><a href="appointment_reports.php">Appointment Reports</a></li>
            <li><a href="manage_feedback.php">Manage Feedback</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h1>Manage Appointments</h1>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Quick Filters -->
        <div class="quick-filters">
            <a href="?status=pending" class="quick-filter-btn <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">⏳ Pending</a>
            <a href="?status=confirmed" class="quick-filter-btn <?php echo $filter_status == 'confirmed' ? 'active' : ''; ?>">✅ Confirmed</a>
            <a href="?status=completed" class="quick-filter-btn <?php echo $filter_status == 'completed' ? 'active' : ''; ?>">✔️ Completed</a>
            <a href="?status=cancelled" class="quick-filter-btn <?php echo $filter_status == 'cancelled' ? 'active' : ''; ?>">❌ Cancelled</a>
            <a href="?date_from=<?php echo date('Y-m-d'); ?>" class="quick-filter-btn <?php echo $filter_date_from == date('Y-m-d') ? 'active' : ''; ?>">📅 Today</a>
            <a href="?date_from=<?php echo date('Y-m-d', strtotime('tomorrow')); ?>" class="quick-filter-btn">📅 Tomorrow</a>
            <a href="manage_appointments.php" class="quick-filter-btn">🔄 All</a>
        </div>
        
        <!-- Enhanced Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>🔍 Search</label>
                        <input type="text" name="search" placeholder="Patient or doctor name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Specialization</label>
                        <select name="specialization">
                            <option value="">All Specializations</option>
                            <?php foreach($specializations as $spec): ?>
                                <option value="<?php echo $spec; ?>" <?php echo $filter_specialization == $spec ? 'selected' : ''; ?>>
                                    <?php echo $spec; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Doctor</label>
                        <select name="doctor">
                            <option value="">All Doctors</option>
                            <?php foreach($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" <?php echo $filter_doctor == $doctor['id'] ? 'selected' : ''; ?>>
                                    Dr. <?php echo $doctor['full_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Patient</label>
                        <select name="patient">
                            <option value="">All Patients</option>
                            <?php foreach($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" <?php echo $filter_patient == $patient['id'] ? 'selected' : ''; ?>>
                                    <?php echo $patient['full_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo $filter_date_from; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo $filter_date_to; ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">Apply Filters</button>
                        <a href="manage_appointments.php" class="btn-reset">Reset</a>
                    </div>
                </div>
            </form>
            
            <!-- Active Filters Display -->
            <?php if($search || $filter_status || $filter_doctor || $filter_patient || $filter_specialization || $filter_date_from || $filter_date_to): ?>
            <div class="active-filters">
                <strong>Active Filters:</strong>
                <?php if($search): ?>
                    <span class="filter-badge">Search: "<?php echo $search; ?>"</span>
                <?php endif; ?>
                <?php if($filter_status): ?>
                    <span class="filter-badge">Status: <?php echo ucfirst($filter_status); ?></span>
                <?php endif; ?>
                <?php if($filter_specialization): ?>
                    <span class="filter-badge">Specialization: <?php echo $filter_specialization; ?></span>
                <?php endif; ?>
                <?php if($filter_doctor): ?>
                    <span class="filter-badge">Doctor ID: <?php echo $filter_doctor; ?></span>
                <?php endif; ?>
                <?php if($filter_patient): ?>
                    <span class="filter-badge">Patient ID: <?php echo $filter_patient; ?></span>
                <?php endif; ?>
                <?php if($filter_date_from): ?>
                    <span class="filter-badge">From: <?php echo $filter_date_from; ?></span>
                <?php endif; ?>
                <?php if($filter_date_to): ?>
                    <span class="filter-badge">To: <?php echo $filter_date_to; ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Results Summary -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <p>Total Appointments: <strong><?php echo count($appointments); ?></strong></p>
            <p>Showing <?php echo count($appointments); ?> of <?php echo $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(); ?> total</p>
        </div>
        
        <?php if(empty($appointments)): ?>
            <div style="text-align: center; padding: 40px;">
                <h3>No appointments found</h3>
                <p>Try changing your filter criteria.</p>
                <a href="manage_appointments.php" class="btn-primary">Clear Filters</a>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Cancelled By</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php foreach($appointments as $appointment): ?>
                    <tr class="<?php echo $appointment['status'] == 'cancelled' ? 'cancelled-row' : ''; ?>">
                        <td>#AP<?php echo str_pad($appointment['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                        <td><?php echo $appointment['specialization']; ?></td>
                        <td>
                            <?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?><br>
                            <small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        
                        </td>
                        <td>
                            <?php if($appointment['status'] == 'cancelled' && $appointment['cancelled_by']): ?>
                                <span style="font-size:12px; color:#dc3545;">
                                    <?php echo ucfirst($appointment['cancelled_by']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" 
                                   class="btn-admin-view">View</a>
                                <div class="dropdown" style="position: relative;">
                                    <button class="btn-admin-edit" style="padding: 5px 10px; font-size: 12px;">
                                        Status ▼
                                    </button>
                                    <div class="dropdown-content">
                                        <a href="manage_appointments.php?update_status&id=<?php echo $appointment['id']; ?>&status=pending">Pending</a>
                                        <a href="manage_appointments.php?update_status&id=<?php echo $appointment['id']; ?>&status=confirmed">Confirm</a>
                                        <a href="manage_appointments.php?update_status&id=<?php echo $appointment['id']; ?>&status=completed">Complete</a>
                                        <a href="manage_appointments.php?update_status&id=<?php echo $appointment['id']; ?>&status=cancelled">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
// Dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const button = dropdown.querySelector('button');
        const content = dropdown.querySelector('.dropdown-content');
        
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            content.style.display = content.style.display === 'block' ? 'none' : 'block';
        });
        
        document.addEventListener('click', function() {
            content.style.display = 'none';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>