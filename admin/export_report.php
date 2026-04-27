<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_specialization = isset($_GET['specialization']) ? $_GET['specialization'] : '';

// Build WHERE conditions
$where_conditions = ["a.appointment_date BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if ($filter_status) {
    $where_conditions[] = "a.status = ?";
    $params[] = $filter_status;
}
if ($filter_specialization) {
    $where_conditions[] = "d.specialization = ?";
    $params[] = $filter_specialization;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get data
$stmt = $pdo->prepare("
    SELECT 
        d.specialization,
        u2.full_name as patient_name,
        u2.email as patient_email,
        u2.phone as patient_phone,
        p.dob as patient_dob,
        p.gender as patient_gender,
        u1.full_name as doctor_name,
        d.consultation_fee,
        COUNT(a.id) as appointment_count,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
        GROUP_CONCAT(
            CONCAT(
                DATE_FORMAT(a.appointment_date, '%d-%m-%Y'), 
                ' at ', 
                TIME_FORMAT(a.appointment_time, '%h:%i %p'),
                ' (', a.status, ')'
            ) 
            ORDER BY a.appointment_date DESC 
            SEPARATOR '; '
        ) as appointment_dates
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u1 ON d.user_id = u1.id
    JOIN patients p ON a.patient_id = p.id
    JOIN users u2 ON p.user_id = u2.id
    $where_clause
    GROUP BY d.specialization, u2.id, u1.id
    ORDER BY d.specialization, u2.full_name
");
$stmt->execute($params);
$data = $stmt->fetchAll();

// Set CSV headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="appointment_report_' . $start_date . '_to_' . $end_date . '.csv"');

$output = fopen('php://output', 'w');

// Header information
fputcsv($output, ['SMART CLINIC - APPOINTMENT REPORT']);
fputcsv($output, ['Report Period: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date))]);
fputcsv($output, ['Generated on: ' . date('d M Y, h:i A')]);
fputcsv($output, []);

// Column headers
fputcsv($output, [
    'Specialization',
    'Patient Name',
    'Patient Email',
    'Patient Phone',
    'Patient Age/Gender',
    'Doctor Name',
    'Consultation Fee',
    'Total Appointments',
    'Completed',
    'Cancelled',
    'Pending',
    'Confirmed',
    'Appointment Details'
]);

// Data rows
foreach ($data as $row) {
    // Calculate age
    $age_gender = '';
    if ($row['patient_dob']) {
        $dob = new DateTime($row['patient_dob']);
        $now = new DateTime();
        $age = $dob->diff($now)->y;
        $age_gender = $age . ' yrs, ' . ucfirst($row['patient_gender'] ?? 'N/A');
    }
    
    fputcsv($output, [
        $row['specialization'],
        $row['patient_name'],
        $row['patient_email'],
        $row['patient_phone'],
        $age_gender,
        'Dr. ' . $row['doctor_name'],
        '₹' . $row['consultation_fee'],
        $row['appointment_count'],
        $row['completed_count'],
        $row['cancelled_count'],
        $row['pending_count'],
        $row['confirmed_count'],
        $row['appointment_dates']
    ]);
}

fclose($output);
exit();
?>