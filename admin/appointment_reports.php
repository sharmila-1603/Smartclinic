<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get date filters
$filter_start = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$filter_end = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_specialization = isset($_GET['specialization']) ? $_GET['specialization'] : '';

// Handle preset periods
$period = isset($_GET['period']) ? $_GET['period'] : '';
switch($period) {
    case 'today':
        $filter_start = date('Y-m-d');
        $filter_end = date('Y-m-d');
        break;
    case 'yesterday':
        $filter_start = date('Y-m-d', strtotime('-1 day'));
        $filter_end = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'this_week':
        $filter_start = date('Y-m-d', strtotime('monday this week'));
        $filter_end = date('Y-m-d');
        break;
    case 'last_week':
        $filter_start = date('Y-m-d', strtotime('-7 days'));
        $filter_end = date('Y-m-d');
        break;
    case 'this_month':
        $filter_start = date('Y-m-01');
        $filter_end = date('Y-m-d');
        break;
    case 'last_month':
        $filter_start = date('Y-m-d', strtotime('-30 days'));
        $filter_end = date('Y-m-d');
        break;
    case 'this_year':
        $filter_start = date('Y-01-01');
        $filter_end = date('Y-m-d');
        break;
}

// Get all specializations for filter
$specializations = $pdo->query("SELECT DISTINCT specialization FROM doctors ORDER BY specialization")->fetchAll(PDO::FETCH_COLUMN);

// Build WHERE conditions
$where_conditions = ["a.appointment_date BETWEEN ? AND ?"];
$params = [$filter_start, $filter_end];

if ($filter_status) {
    $where_conditions[] = "a.status = ?";
    $params[] = $filter_status;
}
if ($filter_specialization) {
    $where_conditions[] = "d.specialization = ?";
    $params[] = $filter_specialization;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get appointments grouped by specialization and status
$stmt = $pdo->prepare("
    SELECT 
        d.specialization,
        a.status,
        u2.full_name as patient_name,
        u2.email as patient_email,
        u2.phone as patient_phone,
        p.dob as patient_dob,
        p.gender as patient_gender,
        u1.full_name as doctor_name,
        d.consultation_fee,
        a.id as appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.symptoms,
        a.cancelled_by,
        a.cancellation_reason,
        a.created_at as booked_on
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u1 ON d.user_id = u1.id
    JOIN patients p ON a.patient_id = p.id
    JOIN users u2 ON p.user_id = u2.id
    $where_clause
    ORDER BY d.specialization, 
             FIELD(a.status, 'pending', 'confirmed', 'completed', 'cancelled'),
             a.appointment_date DESC, 
             a.appointment_time DESC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$summary_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        COUNT(DISTINCT patient_id) as unique_patients,
        COUNT(DISTINCT doctor_id) as active_doctors
    FROM appointments a
    $where_clause
");
$summary_stmt->execute($params);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Group data by specialization, then by status
$grouped_data = [];
$status_order = ['pending', 'confirmed', 'completed', 'cancelled'];
$status_labels = ['pending' => '⏳ Pending', 'confirmed' => '✅ Confirmed', 'completed' => '✔️ Completed', 'cancelled' => '❌ Cancelled'];
$status_colors = ['pending' => '#f39c12', 'confirmed' => '#27ae60', 'completed' => '#3498db', 'cancelled' => '#e74c3c'];

foreach ($appointments as $row) {
    $specialization = $row['specialization'];
    $status = $row['status'];
    
    if (!isset($grouped_data[$specialization])) {
        $grouped_data[$specialization] = [
            'pending' => [],
            'confirmed' => [],
            'completed' => [],
            'cancelled' => [],
            'totals' => [
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'total' => 0
            ]
        ];
    }
    
    $grouped_data[$specialization][$status][] = $row;
    $grouped_data[$specialization]['totals'][$status]++;
    $grouped_data[$specialization]['totals']['total']++;
}

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Reports | SmartClinic</title>
    <link rel="stylesheet" href="../style_project.css">
    <style>
        .report-container {
            padding: 20px;
        }
        
        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-left: 5px solid var(--primary-color);
        }
        
        .quick-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .quick-filter {
            padding: 8px 20px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 30px;
            text-decoration: none;
            color: #555;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .quick-filter:hover, .quick-filter.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 0.85rem;
        }
        
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .btn-filter {
            background: var(--primary-color);
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        /* Specialization Card */
        .specialization-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .specialization-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .specialization-header h2 {
            margin: 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .specialization-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .spec-stat {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        /* ============================================ */
        /* STATUS HEADINGS WITH COLORED BOXES */
        /* ============================================ */
        
        /* Status Section Container */
        .status-section {
            margin-bottom: 20px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        /* Status Header - Common Styles */
        .status-header {
            padding: 16px 24px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .status-header:hover {
            filter: brightness(0.95);
        }
        
        .status-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-icon {
            font-size: 1.3rem;
        }
        
        .status-badge-count {
            background: rgba(0,0,0,0.15);
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-toggle {
            font-size: 1.2rem;
            transition: transform 0.3s;
        }
        
        .status-toggle.collapsed {
            transform: rotate(-90deg);
        }
        
        .status-content {
            padding: 20px;
            background: white;
            display: block;
        }
        
        .status-content.collapsed {
            display: none;
        }
        
        /* PENDING STATUS - YELLOW BOX */
        .status-pending-box .status-header {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .status-pending-box .status-header:hover {
            background: linear-gradient(135deg, #e67e22, #d35400);
        }
        
        .status-pending-box {
            border-left: 5px solid #f39c12;
        }
        
        /* CONFIRMED STATUS - GREEN BOX */
        .status-confirmed-box .status-header {
            background: linear-gradient(135deg, #27ae60, #1e8449);
            color: white;
        }
        
        .status-confirmed-box .status-header:hover {
            background: linear-gradient(135deg, #1e8449, #196f3d);
        }
        
        .status-confirmed-box {
            border-left: 5px solid #27ae60;
        }
        
        /* COMPLETED STATUS - BLUE BOX */
        .status-completed-box .status-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .status-completed-box .status-header:hover {
            background: linear-gradient(135deg, #2980b9, #1f618d);
        }
        
        .status-completed-box {
            border-left: 5px solid #3498db;
        }
        
        /* CANCELLED STATUS - RED BOX */
        .status-cancelled-box .status-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .status-cancelled-box .status-header:hover {
            background: linear-gradient(135deg, #c0392b, #922b21);
        }
        
        .status-cancelled-box {
            border-left: 5px solid #e74c3c;
        }
        
        /* Patient Table */
        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .patient-table th {
            background: #f8f9fa;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
            font-size: 0.8rem;
        }
        
        .patient-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
            font-size: 0.8rem;
        }
        
        .patient-table tr:hover {
            background: #f8f9fa;
        }
        
        .doctor-badge {
            background: #e3f2fd;
            color: var(--primary-color);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .status-pending-bg { background: #f39c12; }
        .status-confirmed-bg { background: #27ae60; }
        .status-completed-bg { background: #3498db; }
        .status-cancelled-bg { background: #e74c3c; }
        
        .patient-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .patient-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .patient-contact {
            font-size: 0.7rem;
            color: #999;
        }
        
        .btn-export {
            background: #28a745;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-export:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .cancel-reason {
            font-size: 0.7rem;
            color: #e74c3c;
            margin-top: 3px;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .patient-table {
                display: block;
                overflow-x: auto;
            }
            .specialization-header {
                flex-direction: column;
                text-align: center;
            }
            .status-header {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_doctors.php">Manage Doctors</a></li>
            <li><a href="manage_patients.php">Manage Patients</a></li>
            <li><a href="manage_appointments.php">Manage Appointments</a></li>
            <li><a href="appointment_reports.php" class="active">Appointment Reports</a></li>
            <li><a href="manage_feedback.php">Manage Feedback</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="report-container">
            <div class="admin-page-header">
                <h1>Appointment Reports</h1>
            
            </div>
            
            <!-- Filter Section -->
            <div class="filter-card">
                <div class="quick-filters">
                    <a href="?period=today" class="quick-filter <?php echo $period == 'today' ? 'active' : ''; ?>">Today</a>
                    <a href="?period=yesterday" class="quick-filter <?php echo $period == 'yesterday' ? 'active' : ''; ?>">Yesterday</a>
                    <a href="?period=this_week" class="quick-filter <?php echo $period == 'this_week' ? 'active' : ''; ?>">This Week</a>
                    <a href="?period=last_week" class="quick-filter <?php echo $period == 'last_week' ? 'active' : ''; ?>">Last 7 Days</a>
                    <a href="?period=this_month" class="quick-filter <?php echo $period == 'this_month' ? 'active' : ''; ?>">This Month</a>
                    <a href="?period=last_month" class="quick-filter <?php echo $period == 'last_month' ? 'active' : ''; ?>">Last 30 Days</a>
                    <a href="?period=this_year" class="quick-filter <?php echo $period == 'this_year' ? 'active' : ''; ?>">This Year</a>
                    <a href="appointment_reports.php" class="quick-filter">All Time</a>
                </div>
                
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>📅 From Date</label>
                            <input type="date" name="start_date" value="<?php echo $filter_start; ?>">
                        </div>
                        <div class="filter-group">
                            <label>📅 To Date</label>
                            <input type="date" name="end_date" value="<?php echo $filter_end; ?>">
                        </div>
                        <div class="filter-group">
                            <label>🏥 Specialization</label>
                            <select name="specialization">
                                <option value="">All Specializations</option>
                                <?php foreach($specializations as $spec): ?>
                                    <option value="<?php echo $spec; ?>" <?php echo $filter_specialization == $spec ? 'selected' : ''; ?>>
                                        <?php echo $spec; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>📊 Status</label>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn-filter">Apply Filters</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Info Box -->
            <div class="info-box">
                <div>
                    <strong>📅 Report Period:</strong> <?php echo date('d M Y', strtotime($filter_start)); ?> - <?php echo date('d M Y', strtotime($filter_end)); ?>
                    <?php if($filter_specialization): ?>
                        | 🏥 <strong><?php echo $filter_specialization; ?></strong>
                    <?php endif; ?>
                    <?php if($filter_status): ?>
                        | 📊 <strong><?php echo ucfirst($filter_status); ?></strong>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="export_report.php?start_date=<?php echo $filter_start; ?>&end_date=<?php echo $filter_end; ?>&specialization=<?php echo urlencode($filter_specialization); ?>&status=<?php echo $filter_status; ?>" class="btn-export">
                        📥 Export to CSV
                    </a>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--primary-color);"><?php echo $summary['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #f39c12;"><?php echo $summary['pending'] ?? 0; ?></div>
                    <div class="stat-label">⏳ Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #27ae60;"><?php echo $summary['confirmed'] ?? 0; ?></div>
                    <div class="stat-label">✅ Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #3498db;"><?php echo $summary['completed'] ?? 0; ?></div>
                    <div class="stat-label">✔️ Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #e74c3c;"><?php echo $summary['cancelled'] ?? 0; ?></div>
                    <div class="stat-label">❌ Cancelled</div>
                </div>
            </div>
            
            <!-- Report by Specialization and Status -->
            <?php if(empty($grouped_data)): ?>
                <div class="admin-empty-state">
                    <h3>No appointments found</h3>
                    <p>No appointments were booked in this period.</p>
                </div>
            <?php else: ?>
                <?php foreach($grouped_data as $specialization => $data): ?>
                    <div class="specialization-card">
                        <div class="specialization-header">
                            <h2>
                                <?php echo $specialization; ?>
                            </h2>
                            <div class="specialization-stats">
                                <span class="spec-stat">📋 Total: <?php echo $data['totals']['total']; ?></span>
                                <span class="spec-stat">⏳ Pending: <?php echo $data['totals']['pending']; ?></span>
                                <span class="spec-stat">✅ Confirmed: <?php echo $data['totals']['confirmed']; ?></span>
                                <span class="spec-stat">✔️ Completed: <?php echo $data['totals']['completed']; ?></span>
                                <span class="spec-stat">❌ Cancelled: <?php echo $data['totals']['cancelled']; ?></span>
                            </div>
                        </div>
                        <br>
                        <!-- Pending Section - YELLOW BOX -->
                        <?php if(!empty($data['pending'])): ?>
                        <div class="status-section status-pending-box">
                            <div class="status-header" onclick="toggleStatus(this)">
                                <div class="status-title">
                                    <span class="status-icon">⏳</span>
                                    Pending Appointments
                                    <span class="status-badge-count"><?php echo count($data['pending']); ?></span>
                                </div>
                                <div class="status-toggle">▼</div>
                            </div>
                            <div class="status-content">
                                <table class="patient-table">
                                    <thead>
                                        <tr>
                                            <th>Patient Name</th>
                                            <th>Email</th>
                                            <th>Doctor</th>
                                            <th>Date & Time</th>
                                            <th>Symptoms</th>
                                            <th>Fee</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($data['pending'] as $apt): 
                                            $age = '';
                                            if ($apt['patient_dob']) {
                                                $dob = new DateTime($apt['patient_dob']);
                                                $now = new DateTime();
                                                $age = $dob->diff($now)->y . 'y';
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="patient-info">
                                                        <span class="patient-name"><?php echo $apt['patient_name']; ?></span>
                                                        <small><?php echo $age; ?>, <?php echo ucfirst($apt['patient_gender'] ?? 'N/A'); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo $apt['patient_email']; ?>
                                                </td>
                                                <td>
                                                    <span class="doctor-badge">👨‍⚕️ Dr. <?php echo $apt['doctor_name']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($apt['appointment_date'])); ?><br>
                                                    <small><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                                </td>
                                                <td><?php echo substr($apt['symptoms'], 0, 40) . (strlen($apt['symptoms']) > 40 ? '...' : ''); ?></td>
                                                <td>₹<?php echo $apt['consultation_fee']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Confirmed Section - GREEN BOX -->
                        <?php if(!empty($data['confirmed'])): ?>
                        <div class="status-section status-confirmed-box">
                            <div class="status-header" onclick="toggleStatus(this)">
                                <div class="status-title">
                                    <span class="status-icon">✅</span>
                                    Confirmed Appointments
                                    <span class="status-badge-count"><?php echo count($data['confirmed']); ?></span>
                                </div>
                                <div class="status-toggle">▼</div>
                            </div>
                            <div class="status-content">
                                <table class="patient-table">
                                    <thead>
                                        <tr>
                                            <th>Patient Name</th>
                                            <th>Email</th>
                                            <th>Doctor</th>
                                            <th>Date & Time</th>
                                            <th>Symptoms</th>
                                            <th>Fee</th>
                                        </td>
                                    </thead>
                                    <tbody>
                                        <?php foreach($data['confirmed'] as $apt): 
                                            $age = '';
                                            if ($apt['patient_dob']) {
                                                $dob = new DateTime($apt['patient_dob']);
                                                $now = new DateTime();
                                                $age = $dob->diff($now)->y . 'y';
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="patient-info">
                                                        <span class="patient-name"><?php echo $apt['patient_name']; ?></span>
                                                        <small><?php echo $age; ?>, <?php echo ucfirst($apt['patient_gender'] ?? 'N/A'); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo $apt['patient_email']; ?>
                                                </td>
                                                <td>
                                                    <span class="doctor-badge">👨‍⚕️ Dr. <?php echo $apt['doctor_name']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($apt['appointment_date'])); ?><br>
                                                    <small><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                                </td>
                                                <td><?php echo substr($apt['symptoms'], 0, 40) . (strlen($apt['symptoms']) > 40 ? '...' : ''); ?></td>
                                                <td>₹<?php echo $apt['consultation_fee']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Completed Section - BLUE BOX -->
                        <?php if(!empty($data['completed'])): ?>
                        <div class="status-section status-completed-box">
                            <div class="status-header" onclick="toggleStatus(this)">
                                <div class="status-title">
                                    <span class="status-icon">✔️</span>
                                    Completed Appointments
                                    <span class="status-badge-count"><?php echo count($data['completed']); ?></span>
                                </div>
                                <div class="status-toggle">▼</div>
                            </div>
                            <div class="status-content">
                                <table class="patient-table">
                                    <thead>
                                        <tr>
                                            <th>Patient Name</th>
                                            <th>Email</th>
                                            <th>Doctor</th>
                                            <th>Date & Time</th>
                                            <th>Symptoms</th>
                                            <th>Fee</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($data['completed'] as $apt): 
                                            $age = '';
                                            if ($apt['patient_dob']) {
                                                $dob = new DateTime($apt['patient_dob']);
                                                $now = new DateTime();
                                                $age = $dob->diff($now)->y . 'y';
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="patient-info">
                                                        <span class="patient-name"><?php echo $apt['patient_name']; ?></span>
                                                        <small><?php echo $age; ?>, <?php echo ucfirst($apt['patient_gender'] ?? 'N/A'); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo $apt['patient_email']; ?>
                                                </td>
                                                <td>
                                                    <span class="doctor-badge">👨‍⚕️ Dr. <?php echo $apt['doctor_name']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($apt['appointment_date'])); ?><br>
                                                    <small><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                                </td>
                                                <td><?php echo substr($apt['symptoms'], 0, 40) . (strlen($apt['symptoms']) > 40 ? '...' : ''); ?></td>
                                                <td>₹<?php echo $apt['consultation_fee']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Cancelled Section - RED BOX -->
                        <?php if(!empty($data['cancelled'])): ?>
                        <div class="status-section status-cancelled-box">
                            <div class="status-header" onclick="toggleStatus(this)">
                                <div class="status-title">
                                    <span class="status-icon">❌</span>
                                    Cancelled Appointments
                                    <span class="status-badge-count"><?php echo count($data['cancelled']); ?></span>
                                </div>
                                <div class="status-toggle">▼</div>
                            </div>
                            <div class="status-content">
                                <table class="patient-table">
                                    <thead>
                                        <tr>
                                            <th>Patient Name</th>
                                            <th>Email</th>
                                            <th>Doctor</th>
                                            <th>Date & Time</th>
                                            <th>Reason</th>
                                            <th>Cancelled By</th>
                                            <th>Fee</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($data['cancelled'] as $apt): 
                                            $age = '';
                                            if ($apt['patient_dob']) {
                                                $dob = new DateTime($apt['patient_dob']);
                                                $now = new DateTime();
                                                $age = $dob->diff($now)->y . 'y';
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="patient-info">
                                                        <span class="patient-name"><?php echo $apt['patient_name']; ?></span>
                                                        <small><?php echo $age; ?>, <?php echo ucfirst($apt['patient_gender'] ?? 'N/A'); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo $apt['patient_email']; ?>
                                                </td>
                                                <td>
                                                    <span class="doctor-badge">👨‍⚕️ Dr. <?php echo $apt['doctor_name']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($apt['appointment_date'])); ?><br>
                                                    <small><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo $apt['cancellation_reason'] ?: 'No reason provided'; ?>
                                                </td>
                                                <td>
                                                    <?php echo $apt['cancelled_by'] ? ucfirst($apt['cancelled_by']) : 'N/A'; ?>
                                                </td>
                                                <td>₹<?php echo $apt['consultation_fee']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Summary Footer -->
            <div style="margin-top: 30px; padding: 15px; background: #f0f7ff; border-radius: 10px; text-align: center;">
                <p style="margin: 0;">
                    📊 <strong>Report Summary:</strong> 
                    Total Appointments: <?php echo $summary['total'] ?? 0; ?> | 
                    Completed: <?php echo $summary['completed'] ?? 0; ?> 
                    (<?php echo $summary['total'] > 0 ? round(($summary['completed'] / $summary['total']) * 100, 1) : 0; ?>%) | 
                    Cancelled: <?php echo $summary['cancelled'] ?? 0; ?> 
                    (<?php echo $summary['total'] > 0 ? round(($summary['cancelled'] / $summary['total']) * 100, 1) : 0; ?>%)
                </p>
                <p style="margin: 10px 0 0; font-size: 11px; color: #666;">
                    Generated on: <?php echo date('d M Y, h:i A'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function toggleStatus(element) {
    const content = element.nextElementSibling;
    const toggle = element.querySelector('.status-toggle');
    
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        toggle.classList.remove('collapsed');
        toggle.innerHTML = '▼';
    } else {
        content.classList.add('collapsed');
        toggle.classList.add('collapsed');
        toggle.innerHTML = '▶';
    }
}
</script>

<?php include '../includes/footer.php'; ?>