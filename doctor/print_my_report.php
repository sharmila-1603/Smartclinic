<?php
session_start();
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
    header("Location: dashboard.php?error=Doctor not found");
    exit();
}

// Get date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

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
$stmt->execute([$doctor['id'], $start_date, $end_date]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get daily breakdown
$stmt = $pdo->prepare("
    SELECT 
        DATE(appointment_date) as date,
        COUNT(*) as daily_total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        GROUP_CONCAT(CONCAT(u.full_name, ' (', a.appointment_time, ') - ', a.status) SEPARATOR '; ') as patient_details
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date BETWEEN ? AND ?
    GROUP BY DATE(appointment_date)
    ORDER BY date DESC
");
$stmt->execute([$doctor['id'], $start_date, $end_date]);
$daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print My Report | SmartClinic</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
            padding: 30px;
            background: white;
        }
        
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #28a745;
        }
        
        .header h1 {
            color: #28a745;
            margin-bottom: 10px;
        }
        
        .doctor-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #28a745;
        }
        
        .doctor-info h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .doctor-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .report-period {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-top: 3px solid #28a745;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th {
            background: #28a745;
            color: white;
            padding: 12px;
            text-align: left;
        }
        
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        
        .status-pending {
            color: #f39c12;
            font-weight: bold;
        }
        
        .status-confirmed {
            color: #27ae60;
            font-weight: bold;
        }
        
        .status-completed {
            color: #3498db;
            font-weight: bold;
        }
        
        .status-cancelled {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .success-rate {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 15px;
            font-size: 12px;
            display: inline-block;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #999;
            font-size: 12px;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }
        
        .print-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .print-btn:hover {
            background: #1e7e34;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <button class="print-btn no-print" onclick="window.print()">🖨️ Print Report</button>
        <button class="print-btn no-print" onclick="window.close()">✖️ Close</button>
        
        <div class="header">
            <h1>📊 My Appointment Report</h1>
            <p>Personal appointment performance analysis</p>
        </div>
        
        <div class="doctor-info">
            <h2>Dr. <?php echo $_SESSION['full_name']; ?></h2>
            <p><strong>Specialization:</strong> <?php echo $doctor['specialization']; ?></p>
            <p><strong>Email:</strong> <?php echo $_SESSION['email']; ?></p>
        </div>
        
        <div class="report-period">
            <div><strong>📅 Report Period:</strong> <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></div>
            <div><strong>🕒 Generated On:</strong> <?php echo date('d M Y, h:i A'); ?></div>
        </div>
        
        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Appointments</h3>
                <div class="number"><?php echo $stats['total_appointments'] ?? 0; ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #f39c12;">
                <h3>Pending</h3>
                <div class="number"><?php echo $stats['pending_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #27ae60;">
                <h3>Confirmed</h3>
                <div class="number"><?php echo $stats['confirmed_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #3498db;">
                <h3>Completed</h3>
                <div class="number"><?php echo $stats['completed_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #e74c3c;">
                <h3>Cancelled</h3>
                <div class="number"><?php echo $stats['cancelled_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Unique Patients</h3>
                <div class="number"><?php echo $stats['unique_patients'] ?? 0; ?></div>
            </div>
        </div>
        
        <!-- Success Rate -->
        <div style="background: #e8f5e9; padding: 20px; border-radius: 10px; margin-bottom: 30px; text-align: center;">
            <h3>Success Rate</h3>
            <?php 
            $success_rate = $stats['total_appointments'] > 0 
                ? round(($stats['completed_count'] / $stats['total_appointments']) * 100, 1) 
                : 0;
            ?>
            <div style="font-size: 48px; font-weight: bold; color: #28a745;"><?php echo $success_rate; ?>%</div>
            <div style="background: #ecf0f1; border-radius: 10px; height: 10px; width: 80%; margin: 10px auto;">
                <div style="background: #28a745; width: <?php echo $success_rate; ?>%; height: 10px; border-radius: 10px;"></div>
            </div>
        </div>
        
        <!-- Daily Breakdown -->
        <h2>📅 Daily Breakdown</h2>
        <?php if(empty($daily_stats)): ?>
            <p>No appointments in this period.</p>
        <?php else: ?>
            <table>
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
                    <?php foreach($daily_stats as $day): 
                        $day_rate = $day['daily_total'] > 0 
                            ? round(($day['completed'] / $day['daily_total']) * 100, 1) 
                            : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo date('d M Y', strtotime($day['date'])); ?></strong></td>
                        <td><?php echo $day['daily_total']; ?></td>
                        <td style="color: #27ae60;"><?php echo $day['completed']; ?></td>
                        <td style="color: #e74c3c;"><?php echo $day['cancelled']; ?></td>
                        <td>
                            <div style="background: #ecf0f1; border-radius: 10px; height: 15px; width: 100px;">
                                <div style="background: #27ae60; width: <?php echo $day_rate; ?>%; height: 15px; border-radius: 10px;"></div>
                            </div>
                            <span style="font-size: 12px;"><?php echo $day_rate; ?>%</span>
                        </td>
                        <td style="font-size: 12px;"><?php echo $day['patient_details']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="footer">
            <p>SmartClinic Healthcare System - This is a computer generated report. No signature required.</p>
            <p>© <?php echo date('Y'); ?> SmartClinic. All rights reserved.</p>
        </div>
    </div>
</body>
</html>