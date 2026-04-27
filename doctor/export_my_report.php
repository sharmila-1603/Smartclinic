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
        GROUP_CONCAT(CONCAT(patient_name, ' (', appointment_time, ')') SEPARATOR '; ') as patient_details
    FROM (
        SELECT a.*, u.full_name as patient_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE a.doctor_id = ? AND a.appointment_date BETWEEN ? AND ?
    ) as daily_appointments
    GROUP BY DATE(appointment_date)
    ORDER BY date DESC
");
$stmt->execute([$doctor['id'], $start_date, $end_date]);
$daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="doctor_report_' . $doctor['specialization'] . '_' . $start_date . '_to_' . $end_date . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add report header
fputcsv($output, ['SMART CLINIC - DOCTOR APPOINTMENT REPORT']);
fputcsv($output, ['Doctor Name: Dr. ' . $_SESSION['full_name']]);
fputcsv($output, ['Specialization: ' . $doctor['specialization']]);
fputcsv($output, ['Report Period: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date))]);
fputcsv($output, ['Generated on: ' . date('d M Y H:i:s')]);
fputcsv($output, []);
fputcsv($output, ['APPOINTMENT SUMMARY']);
fputcsv($output, []);

// Add summary
fputcsv($output, ['Total Appointments', $stats['total_appointments'] ?? 0]);
fputcsv($output, ['Unique Patients', $stats['unique_patients'] ?? 0]);
fputcsv($output, ['Pending', $stats['pending_count'] ?? 0]);
fputcsv($output, ['Confirmed', $stats['confirmed_count'] ?? 0]);
fputcsv($output, ['Completed', $stats['completed_count'] ?? 0]);
fputcsv($output, ['Cancelled', $stats['cancelled_count'] ?? 0]);

if ($stats['total_appointments'] > 0) {
    $success_rate = round(($stats['completed_count'] / $stats['total_appointments']) * 100, 1);
    fputcsv($output, ['Success Rate', $success_rate . '%']);
}

fputcsv($output, []);
fputcsv($output, []);
fputcsv($output, ['DAILY BREAKDOWN']);
fputcsv($output, []);

// Add daily breakdown headers
fputcsv($output, ['Date', 'Total Appointments', 'Completed', 'Cancelled', 'Patient Details']);

// Add daily data
foreach ($daily_stats as $day) {
    fputcsv($output, [
        date('d M Y', strtotime($day['date'])),
        $day['daily_total'],
        $day['completed'],
        $day['cancelled'],
        $day['patient_details']
    ]);
}

fclose($output);
exit();
?>