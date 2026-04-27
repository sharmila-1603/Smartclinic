<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Check if user is logged in as doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../index.php");
    exit();
}

// Get doctor ID
$stmt = $pdo->prepare("SELECT id, specialization FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header("Location: ../index.php?error=Doctor not found");
    exit();
}

// Handle preset date filters
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'this_month';

switch($filter_period) {
    case 'last_week':
        $filter_start = date('Y-m-d', strtotime('-7 days'));
        $filter_end = date('Y-m-d');
        break;
    case 'last_month':
        $filter_start = date('Y-m-d', strtotime('-30 days'));
        $filter_end = date('Y-m-d');
        break;
    case 'this_month':
    default:
        $filter_start = date('Y-m-01');
        $filter_end = date('Y-m-d');
        break;
}

// Allow custom dates to override preset
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $filter_start = $_GET['start_date'];
    $filter_end = $_GET['end_date'];
    $filter_period = 'custom';
}

// Get doctor's appointment statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        COUNT(DISTINCT patient_id) as unique_patients
    FROM appointments
    WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ?
");
$stmt->execute([$doctor['id'], $filter_start, $filter_end]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get daily breakdown with patient details
$stmt = $pdo->prepare("
    SELECT 
        DATE(a.appointment_date) as date,
        COUNT(*) as daily_total,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        GROUP_CONCAT(CONCAT(u.full_name, ' (', TIME_FORMAT(a.appointment_time, '%h:%i %p'), ') - ', a.status) SEPARATOR '; ') as patient_details
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date BETWEEN ? AND ?
    GROUP BY DATE(a.appointment_date)
    ORDER BY date DESC
");
$stmt->execute([$doctor['id'], $filter_start, $filter_end]);
$daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly trend for last 6 months
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(appointment_date, '%M %Y') as month,
        DATE_FORMAT(appointment_date, '%Y-%m') as month_sort,
        COUNT(*) as monthly_total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM appointments
    WHERE doctor_id = ? AND appointment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month_sort DESC
");
$stmt->execute([$doctor['id']]);
$monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
/* Additional styles for doctor reports */
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
    border-top: 4px solid var(--info-color);
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
    color: #1979d9;
    margin: 0;
}

.admin-filters {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
    border-left: 5px solid var(--primary-color);
}

.filter-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
    background: white;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 25px;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.admin-table th {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
}

.admin-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    color: #555;
    font-size: 0.95rem;
}

.admin-table tr:hover {
    background: #f8f9fa;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
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

.btn {
    padding: 8px 20px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.admin-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
    flex-wrap: wrap;
    gap: 15px;
}

.admin-page-header h1 {
    color: #2c3e50;
    font-size: 1.8rem;
    margin: 0;
}
</style>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Doctor Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="schedule.php">My Schedule</a></li>
            <li><a href="my_reports.php" class="active">My Reports</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="admin-page-header">
            <h1>My Appointment Reports</h1>
            <p>Specialization: <strong><?php echo htmlspecialchars($doctor['specialization']); ?></strong></p>
        </div>
        
        <!-- Date Filter with Preset Options -->
        <div class="admin-filters">
            <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                <a href="?period=this_month" class="btn <?php echo $filter_period == 'this_month' ? 'btn-primary' : 'btn-secondary'; ?>">📅 This Month</a>
                <a href="?period=last_week" class="btn <?php echo $filter_period == 'last_week' ? 'btn-primary' : 'btn-secondary'; ?>">📆 Last 7 Days</a>
                <a href="?period=last_month" class="btn <?php echo $filter_period == 'last_month' ? 'btn-primary' : 'btn-secondary'; ?>">📅 Last 30 Days</a>
            </div>
            
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>📅 From Date</label>
                        <input type="date" name="start_date" value="<?php echo $filter_period == 'custom' ? $filter_start : ''; ?>">
                    </div>
                    <div class="filter-group">
                        <label>📅 To Date</label>
                        <input type="date" name="end_date" value="<?php echo $filter_period == 'custom' ? $filter_end : ''; ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn-primary">Apply Custom Range</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Summary Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3>Total Appointments</h3>
                <p class="count"><?php echo $stats['total_appointments'] ?? 0; ?></p>
                <small><?php echo date('d M', strtotime($filter_start)); ?> - <?php echo date('d M', strtotime($filter_end)); ?></small>
            </div>
            <div class="stat-card" style="border-top-color: #27ae60;">
                <h3>✅ Completed</h3>
                <p class="count"><?php echo $stats['completed_count'] ?? 0; ?></p>
                <small>
                    <?php 
                    $rate = $stats['total_appointments'] > 0 
                        ? round(($stats['completed_count'] / $stats['total_appointments']) * 100, 1) 
                        : 0;
                    echo $rate; ?>% success rate
                </small>
            </div>
            <div class="stat-card" style="border-top-color: #f39c12;">
                <h3>⏳ Pending</h3>
                <p class="count"><?php echo $stats['pending_count'] ?? 0; ?></p>
            </div>
            <div class="stat-card" style="border-top-color: #e74c3c;">
                <h3>❌ Cancelled</h3>
                <p class="count"><?php echo $stats['cancelled_count'] ?? 0; ?></p>
            </div>
        </div>
        
        <!-- Performance Summary -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0;">
            <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h3>📈 Completion Rate</h3>
                <div style="font-size: 48px; font-weight: 700; color: #27ae60; margin: 10px 0;">
                    <?php echo $rate; ?>%
                </div>
                <div style="background: #ecf0f1; border-radius: 10px; height: 20px; width: 100%;">
                    <div style="background: #27ae60; width: <?php echo $rate; ?>%; height: 20px; border-radius: 10px;"></div>
                </div>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">of appointments successfully completed</p>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h3>⚠️ Cancellation Rate</h3>
                <?php $cancel_rate = $stats['total_appointments'] > 0 ? round(($stats['cancelled_count'] / $stats['total_appointments']) * 100, 1) : 0; ?>
                <div style="font-size: 48px; font-weight: 700; color: #e74c3c; margin: 10px 0;">
                    <?php echo $cancel_rate; ?>%
                </div>
                <div style="background: #ecf0f1; border-radius: 10px; height: 20px; width: 100%;">
                    <div style="background: #e74c3c; width: <?php echo $cancel_rate; ?>%; height: 20px; border-radius: 10px;"></div>
                </div>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">of appointments cancelled</p>
            </div>
        </div>
        
        <!-- Monthly Trend (Last 6 Months) -->
        <h3>📈 Monthly Trend (Last 6 Months)</h3>
        <div style="background: white; padding: 20px; border-radius: 10px; margin: 20px 0;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total Appointments</th>
                        <th>Completed</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($monthly_trend)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No appointment data available</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($monthly_trend as $month): ?>
                        <tr>
                            <td><strong><?php echo $month['month']; ?></strong></td>
                            <td><?php echo $month['monthly_total']; ?></td>
                            <td style="color: #27ae60;"><?php echo $month['completed']; ?></td>
                            <td>
                                <?php if($month['monthly_total'] > 0): ?>
                                    <?php $month_rate = round(($month['completed'] / $month['monthly_total']) * 100, 1); ?>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="background: #ecf0f1; border-radius: 10px; height: 15px; width: 200px;">
                                            <div style="background: #27ae60; width: <?php echo $month_rate; ?>%; height: 15px; border-radius: 10px;"></div>
                                        </div>
                                        <span><?php echo $month_rate; ?>%</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Daily Breakdown -->
        <h3>Daily Breakdown</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Completed</th>
                    <th>Cancelled</th>
                    <th>Performance</th>
                    <th>Patients</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($daily_stats)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No appointments in this period</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($daily_stats as $day): ?>
                    <tr>
                        <td><strong><?php echo date('d M Y', strtotime($day['date'])); ?></strong></td>
                        <td><strong><?php echo $day['daily_total']; ?></strong></td>
                        <td style="color: #27ae60;"><?php echo $day['completed']; ?></td>
                        <td style="color: #e74c3c;"><?php echo $day['cancelled']; ?></td>
                        <td>
                            <?php if($day['daily_total'] > 0): ?>
                                <?php $day_rate = round(($day['completed'] / $day['daily_total']) * 100, 1); ?>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="background: #ecf0f1; border-radius: 10px; height: 15px; width: 100px;">
                                        <div style="background: #27ae60; width: <?php echo $day_rate; ?>%; height: 15px; border-radius: 10px;"></div>
                                    </div>
                                    <span><?php echo $day_rate; ?>%</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12px; max-width: 300px; word-break: break-all;">
                            <?php echo $day['patient_details'] ?? 'No patients'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Export Options -->
        <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
            <a href="export_my_report.php?start_date=<?php echo $filter_start; ?>&end_date=<?php echo $filter_end; ?>" class="btn-primary" style="padding: 10px 25px;">
                📥 Export to CSV
            </a>
            <a href="print_my_report.php?start_date=<?php echo $filter_start; ?>&end_date=<?php echo $filter_end; ?>" target="_blank" class="btn-secondary" style="padding: 10px 25px;">
                🖨️ Print Report
            </a>
        </div>
        
        <!-- Note -->
        <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; font-size: 13px; text-align: center;">
            <strong>ℹ️ Note:</strong> Reports can be exported as CSV for analysis or printed for records. Data includes all appointments within the selected date range.
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>