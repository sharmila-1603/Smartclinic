<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../index.php");
    exit();
}

// Get doctor ID
$stmt = $pdo->prepare("
    SELECT d.*, u.full_name 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

// Get filter parameters
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_patient = isset($_GET['patient']) ? $_GET['patient'] : '';

// Build query with filters
$sql = "
    SELECT a.*, u.full_name as patient_name, u.phone as patient_phone,
           p.dob, p.gender, p.address
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ?
";

$params = [$doctor['id']];

// Add date filter
if ($filter_date) {
    $sql .= " AND a.appointment_date = ?";
    $params[] = $filter_date;
}

// Add status filter
if ($filter_status) {
    $sql .= " AND a.status = ?";
    $params[] = $filter_status;
}

// Add patient search filter
if ($filter_patient) {
    $sql .= " AND u.full_name LIKE ?";
    $params[] = "%$filter_patient%";
}

$sql .= " ORDER BY a.appointment_time";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get upcoming appointments summary for the week
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($filter_date)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($filter_date)));

$week_stmt = $pdo->prepare("
    SELECT 
        DATE(appointment_date) as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments
    WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ?
    GROUP BY DATE(appointment_date)
    ORDER BY date
");
$week_stmt->execute([$doctor['id'], $week_start, $week_end]);
$weekly_summary = $week_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status counts for current date
$status_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments
    WHERE doctor_id = ? AND appointment_date = ?
");
$status_stmt->execute([$doctor['id'], $filter_date]);
$status_counts = $status_stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
/* Calendar Navigation */
.calendar-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendar-nav .nav-buttons {
    display: flex;
    gap: 10px;
}

.calendar-nav .current-date {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--dark-color);
}

/* Weekly Summary Cards */
.week-summary {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 10px;
    margin-bottom: 25px;
}

.week-day-card {
    min-width: 120px;
    background: white;
    border-radius: 10px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid #e0e0e0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.week-day-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: var(--primary-color);
}

.week-day-card.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.week-day-card .day-name {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.week-day-card .day-date {
    font-size: 0.75rem;
    margin-bottom: 8px;
}

.week-day-card .day-count {
    font-size: 1.2rem;
    font-weight: 700;
}

.week-day-card.active .day-count {
    color: white;
}

/* Status Filter Buttons */
.status-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.status-filter-btn {
    padding: 8px 20px;
    border-radius: 30px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    font-weight: 500;
}

.status-filter-btn:hover {
    transform: translateY(-2px);
}

.status-filter-btn.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.status-filter-btn.pending.active {
    background: #f39c12;
    border-color: #f39c12;
}

.status-filter-btn.confirmed.active {
    background: #27ae60;
    border-color: #27ae60;
}

.status-filter-btn.completed.active {
    background: #3498db;
    border-color: #3498db;
}

.status-filter-btn.cancelled.active {
    background: #e74c3c;
    border-color: #e74c3c;
}

/* Search Box */
.search-box {
    position: relative;
    margin-bottom: 20px;
}

.search-box input {
    width: 100%;
    padding: 12px 40px 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
}

.search-box .search-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

/* Stats Row */
.stats-row {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.stat-badge {
    background: #f8f9fa;
    padding: 8px 15px;
    border-radius: 30px;
    font-size: 0.85rem;
}

.stat-badge .label {
    color: #666;
}

.stat-badge .number {
    font-weight: 700;
    margin-left: 5px;
}

.stat-badge.pending .number { color: #f39c12; }
.stat-badge.confirmed .number { color: #27ae60; }
.stat-badge.completed .number { color: #3498db; }
.stat-badge.cancelled .number { color: #e74c3c; }
</style>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Doctor Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="schedule.php" class="active">My Schedule</a></li>
            <li><a href="my_reports.php">My Reports</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="admin-page-header">
            <h1>My Schedule</h1>
            <p>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?> • <?php echo $doctor['specialization']; ?></p>
        </div>
        
        <!-- Calendar Navigation -->
        <div class="calendar-nav">
            <div class="nav-buttons">
                <a href="?date=<?php echo date('Y-m-d', strtotime($filter_date . ' -7 days')); ?>&status=<?php echo $filter_status; ?>&patient=<?php echo urlencode($filter_patient); ?>" class="btn-secondary">« Prev Week</a>
                <a href="?date=<?php echo date('Y-m-d'); ?>&status=<?php echo $filter_status; ?>&patient=<?php echo urlencode($filter_patient); ?>" class="btn-secondary">Today</a>
                <a href="?date=<?php echo date('Y-m-d', strtotime($filter_date . ' +7 days')); ?>&status=<?php echo $filter_status; ?>&patient=<?php echo urlencode($filter_patient); ?>" class="btn-secondary">Next Week »</a>
            </div>
            <div class="current-date">
                <?php echo date('l, d F Y', strtotime($filter_date)); ?>
            </div>
        </div>
        
        <!-- Weekly Summary -->
        <div class="week-summary">
            <?php
            $week_dates = [];
            $start_of_week = date('Y-m-d', strtotime('monday this week', strtotime($filter_date)));
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime($start_of_week . ' + ' . $i . ' days'));
                $week_dates[] = $date;
            }
            
            $summary_map = [];
            foreach ($weekly_summary as $ws) {
                $summary_map[$ws['date']] = $ws;
            }
            
            foreach ($week_dates as $date):
                $summary = $summary_map[$date] ?? ['total' => 0];
                $is_active = ($date == $filter_date);
            ?>
            <a href="?date=<?php echo $date; ?>&status=<?php echo $filter_status; ?>&patient=<?php echo urlencode($filter_patient); ?>" class="week-day-card <?php echo $is_active ? 'active' : ''; ?>">
                <div class="day-name"><?php echo date('D', strtotime($date)); ?></div>
                <div class="day-date"><?php echo date('d M', strtotime($date)); ?></div>
                <div class="day-count"><?php echo $summary['total']; ?></div>
                <div class="day-details" style="font-size: 10px; margin-top: 5px;">
                    <?php if(isset($summary['pending']) && $summary['pending'] > 0): ?>
                        <span style="color: #f39c12;">P:<?php echo $summary['pending']; ?></span>
                    <?php endif; ?>
                    <?php if(isset($summary['confirmed']) && $summary['confirmed'] > 0): ?>
                        <span style="color: #27ae60;">C:<?php echo $summary['confirmed']; ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Status Filters -->
        <div class="status-filters">
            <a href="?date=<?php echo $filter_date; ?>&status=&patient=<?php echo urlencode($filter_patient); ?>" class="status-filter-btn <?php echo $filter_status == '' ? 'active' : ''; ?>">All</a>
            <a href="?date=<?php echo $filter_date; ?>&status=pending&patient=<?php echo urlencode($filter_patient); ?>" class="status-filter-btn pending <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?date=<?php echo $filter_date; ?>&status=confirmed&patient=<?php echo urlencode($filter_patient); ?>" class="status-filter-btn confirmed <?php echo $filter_status == 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
            <a href="?date=<?php echo $filter_date; ?>&status=completed&patient=<?php echo urlencode($filter_patient); ?>" class="status-filter-btn completed <?php echo $filter_status == 'completed' ? 'active' : ''; ?>">Completed</a>
            <a href="?date=<?php echo $filter_date; ?>&status=cancelled&patient=<?php echo urlencode($filter_patient); ?>" class="status-filter-btn cancelled <?php echo $filter_status == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
        </div>
        
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-badge"><span class="label">Total:</span> <span class="number"><?php echo $status_counts['total'] ?? 0; ?></span></div>
            <div class="stat-badge pending"><span class="label">Pending:</span> <span class="number"><?php echo $status_counts['pending'] ?? 0; ?></span></div>
            <div class="stat-badge confirmed"><span class="label">Confirmed:</span> <span class="number"><?php echo $status_counts['confirmed'] ?? 0; ?></span></div>
            <div class="stat-badge completed"><span class="label">Completed:</span> <span class="number"><?php echo $status_counts['completed'] ?? 0; ?></span></div>
            <div class="stat-badge cancelled"><span class="label">Cancelled:</span> <span class="number"><?php echo $status_counts['cancelled'] ?? 0; ?></span></div>
        </div>
        
        <!-- Search Box -->
<div class="search-box">
    <form method="GET" action="">
        <input type="hidden" name="date" value="<?php echo $filter_date; ?>">
        <input type="hidden" name="status" value="<?php echo $filter_status; ?>">
        <input type="text" name="patient" placeholder="🔍 Search patient by name..." value="<?php echo htmlspecialchars($filter_patient); ?>">
        <button type="submit" class="search-button">🔍 Search</button>
    </form>
</div>
        
        <!-- Appointments List -->
        <h2>Appointments for <?php echo date('l, d F Y', strtotime($filter_date)); ?></h2>
        
        <?php if(empty($appointments)): ?>
            <div class="admin-empty-state">
                <h3>No appointments found</h3>
                <p>
                    <?php 
                    if ($filter_patient) {
                        echo "No appointments matching patient name: <strong>" . htmlspecialchars($filter_patient) . "</strong>";
                    } elseif ($filter_status) {
                        echo "No <strong>" . ucfirst($filter_status) . "</strong> appointments on this date.";
                    } else {
                        echo "There are no appointments scheduled for " . date('d M Y', strtotime($filter_date)) . ".";
                    }
                    ?>
                </p>
                <div style="margin-top: 20px;">
                    <a href="schedule.php" class="btn-primary">Clear All Filters</a>
                    <a href="?date=<?php echo $filter_date; ?>" class="btn-secondary">Clear Status Filter</a>
                </div>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Phone</th>
                        <th>Age/Gender</th>
                        <th>Symptoms</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php foreach($appointments as $apt): 
                        $age = '';
                        if ($apt['dob']) {
                            $dob = new DateTime($apt['dob']);
                            $now = new DateTime();
                            $age = $dob->diff($now)->y . ' yrs';
                        }
                    ?>
                    <tr class="status-<?php echo $apt['status']; ?>">
                        <td><strong><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></strong>
                        <td><strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong>
                        <td><?php echo htmlspecialchars($apt['patient_phone']); ?>
                        <td><?php echo $age . ($apt['gender'] ? ', ' . ucfirst($apt['gender']) : ''); ?>
                        <td><?php echo htmlspecialchars(substr($apt['symptoms'], 0, 50)) . (strlen($apt['symptoms']) > 50 ? '...' : ''); ?>
                        <td>
                            <span class="status-badge status-<?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        <td>
                            <div class="admin-actions" style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <?php if($apt['status'] == 'pending'): ?>
                                    <a href="update_status.php?id=<?php echo $apt['id']; ?>&status=confirmed" class="btn-admin-edit" style="padding: 5px 10px; font-size: 12px;">Confirm</a>
                                    <a href="update_status.php?id=<?php echo $apt['id']; ?>&status=cancelled" class="btn-admin-delete" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Cancel this appointment?')">❌ Cancel</a>
                                <?php endif; ?>
                                <?php if($apt['status'] == 'confirmed'): ?>
                                    <a href="update_status.php?id=<?php echo $apt['id']; ?>&status=completed" class="btn-admin-view" style="padding: 5px 10px; font-size: 12px;"Complete</a>
                                    <a href="update_status.php?id=<?php echo $apt['id']; ?>&status=cancelled" class="btn-admin-delete" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Cancel this appointment?')">❌ Cancel</a>
                                <?php endif; ?>
                                <?php if($apt['status'] == 'completed'): ?>
                                    <span style="color: #27ae60; font-size: 12px;">Completed</span>
                                <?php endif; ?>
                                <?php if($apt['status'] == 'cancelled'): ?>
                                    <span style="color: #e74c3c; font-size: 12px;">Cancelled</span>
                                <?php endif; ?>
                            </div>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Quick Stats Summary -->
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; display: flex; justify-content: space-between; flex-wrap: wrap;">
                <div>Total: <strong><?php echo count($appointments); ?></strong></div>
                <div>Pending: <strong style="color: #f39c12;"><?php echo count(array_filter($appointments, function($a) { return $a['status'] == 'pending'; })); ?></strong></div>
                <div>Confirmed: <strong style="color: #27ae60;"><?php echo count(array_filter($appointments, function($a) { return $a['status'] == 'confirmed'; })); ?></strong></div>
                <div>Completed: <strong style="color: #3498db;"><?php echo count(array_filter($appointments, function($a) { return $a['status'] == 'completed'; })); ?></strong></div>
                <div>Cancelled: <strong style="color: #e74c3c;"><?php echo count(array_filter($appointments, function($a) { return $a['status'] == 'cancelled'; })); ?></strong></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>