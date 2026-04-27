<?php
session_start();
require_once 'config/database.php';

// Fetch all doctors grouped by specialization
$stmt = $pdo->query("
    SELECT 
        d.*,
        u.full_name,
        u.email,
        u.phone,
        d.specialization,
        d.qualification,
        d.experience_years,
        d.consultation_fee
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.specialization, u.full_name
");
$all_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group doctors by specialization
$doctors_by_category = [];
foreach ($all_doctors as $doctor) {
    $doctors_by_category[$doctor['specialization']][] = $doctor;
}

// Get unique specializations
$specializations = array_keys($doctors_by_category);

// Get selected specialization from URL parameter
$selected_specialization = isset($_GET['specialization']) ? $_GET['specialization'] : '';
$display_doctors = [];

if ($selected_specialization && isset($doctors_by_category[$selected_specialization])) {
    // Show doctors from selected specialization
    $display_doctors = $doctors_by_category[$selected_specialization];
    $page_title = $selected_specialization . " Specialists";
} else {
    // Show all doctors
    $display_doctors = $all_doctors;
    $page_title = "All Doctors";
}

$page_title = "Our Doctors";
include 'includes/header.php';
?>

<style>
/* ===== DOCTORS PAGE CUSTOM COLORS ===== */
:root {
    --doc-primary: #23a5bc;
    --doc-primary-dark: #1c49b9;
}

/* Doctors Header */
.doctors-header {
    background: linear-gradient(135deg, var(--doc-primary), var(--doc-primary-dark)) !important;
}

/* Filter Dropdown */
.specialization-dropdown {
    border-color: var(--doc-primary) !important;
}

.specialization-dropdown:hover,
.specialization-dropdown:focus {
    border-color: var(--doc-primary-dark) !important;
    box-shadow: 0 5px 15px rgba(111, 66, 193, 0.2) !important;
}

/* Reset Button */
.reset-btn {
    background: var(--doc-primary) !important;
    border-color: var(--doc-primary) !important;
}

.reset-btn:hover {
    background: var(--doc-primary-dark) !important;
    box-shadow: 0 5px 15px rgba(111, 66, 193, 0.3) !important;
}

/* Doctor Count Badge */
.doctor-count-badge {
    background: var(--doc-primary) !important;
}

/* Doctor Cards */
.doctor-card:hover {
    box-shadow: 0 20px 40px rgba(111, 66, 193, 0.15) !important;
}

.doctor-card-header {
    background: linear-gradient(135deg, var(--doc-primary), var(--doc-primary-dark)) !important;
}

.doctor-avatar {
    color: var(--doc-primary) !important;
}

.info-icon {
    color: var(--doc-primary) !important;
}

/* Book Button */
.btn-book {
    background: linear-gradient(135deg, var(--doc-primary), var(--doc-primary-dark)) !important;
}

.btn-book:hover {
    box-shadow: 0 5px 15px rgba(111, 66, 193, 0.3) !important;
}

/* Disabled button styles */
.btn-disabled {
    background: #f0f0f0;
    color: #999;
    padding: 10px 25px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    cursor: not-allowed;
    border: 1px solid #ddd;
}

/* Warning Message */
.warning-message {
    background: #fff3cd;
    color: #856404;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #ffc107;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}

.warning-message .icon {
    font-size: 20px;
}
</style>

<!-- Warning Message for Doctors or Admins -->
<?php if(isset($_SESSION['user_id']) && ($_SESSION['role'] == 'doctor' || $_SESSION['role'] == 'admin')): ?>
<div class="warning-message">
    <span class="icon">⚠️</span>
    <span><strong>Note:</strong> You are logged in as <?php echo ucfirst($_SESSION['role']); ?>. Only patients can book appointments. If you need to book an appointment, please use a patient account.</span>
</div>
<?php endif; ?>

<!-- Doctors Header -->
<section class="doctors-header">
    <div class="container">
        <h1>Our Expert Doctors</h1>
        <p>Consult with India's best medical specialists across various fields</p>
    </div>
</section>

<!-- Filter Section -->
<section class="doctor-filter-section">
    <div class="filter-container">
        <span class="filter-label">Filter by Specialization:</span>
        
        <form method="GET" action="" id="filter-form" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <select name="specialization" class="specialization-dropdown" onchange="this.form.submit()">
                <option value="">All Specializations</option>
                <?php foreach($specializations as $spec): ?>
                <option value="<?php echo htmlspecialchars($spec); ?>" 
                    <?php echo ($selected_specialization == $spec) ? 'selected' : ''; ?>>
                    <?php echo $spec; ?> (<?php echo count($doctors_by_category[$spec]); ?>)
                </option>
                <?php endforeach; ?>
            </select>
            
            <?php if($selected_specialization): ?>
            <a href="doctors.php" class="reset-btn">Reset Filter</a>
            <?php endif; ?>
        </form>
    </div>
</section>

<!-- Results Info -->
<div class="results-info">
    <h2>
        <?php 
        if($selected_specialization) {
            echo $selected_specialization . " Specialists";
        } else {
            echo "All Doctors";
        }
        ?>
    </h2>
    <span class="doctor-count-badge"><?php echo count($display_doctors); ?> doctors available</span>
</div>

<!-- Doctors Grid -->
<?php if(empty($display_doctors)): ?>
    <div class="no-doctors">
        <h3>No doctors found</h3>
        <p>Please try selecting a different specialization.</p>
    </div>
<?php else: ?>
    <div class="doctors-grid">
        <?php foreach($display_doctors as $doctor): ?>
        <div class="doctor-card">
            <div class="doctor-card-header">
                <div class="doctor-avatar">
                    <?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?>
                </div>
                <h3>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                <p><?php echo htmlspecialchars($doctor['specialization']); ?></p>
            </div>
            <div class="doctor-card-body">
                <div class="doctor-info-item">
                    <span class="info-icon">📅</span>
                    <span><?php echo $doctor['experience_years']; ?> years experience</span>
                </div>
                <div class="doctor-info-item">
                    <span class="info-icon">💰</span>
                    <span>Fee: ₹<?php echo $doctor['consultation_fee']; ?></span>
                </div>
                <div class="doctor-info-item">
                    <span class="info-icon">📋</span>
                    <span><?php echo htmlspecialchars($doctor['qualification']); ?></span>
                </div>
                <div class="doctor-fee">
                    Available Today
                </div>
            </div>
            <div class="doctor-card-footer">
                <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'patient'): ?>
                    <a href="patient/book_appointment.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn-book">Book Appointment</a>
                <?php elseif(isset($_SESSION['user_id']) && $_SESSION['role'] == 'doctor'): ?>
                    <span class="btn-disabled" onclick="showWarning('doctor')">❌ Doctors cannot book appointments</span>
                <?php elseif(isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin'): ?>
                    <span class="btn-disabled" onclick="showWarning('admin')">❌ Admins cannot book appointments</span>
                <?php else: ?>
                    <a href="#" onclick="openLogin(); return false;" class="btn-book">Login to Book</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- JavaScript for Warning -->
<script>
function showWarning(role) {
    if(role === 'doctor') {
        alert("⚠️ Only patients can book appointments.\n\nIf you need to book an appointment, please:\n1. Logout from doctor account\n2. Login with a patient account\n3. Register as a new patient if you don't have one");
    } else if(role === 'admin') {
        alert("⚠️ Administrators cannot book appointments.\n\nTo book an appointment:\n1. Logout from admin account\n2. Login with a patient account\n3. Register as a new patient if you don't have one");
    }
}
</script>

<?php include 'includes/footer.php'; ?>