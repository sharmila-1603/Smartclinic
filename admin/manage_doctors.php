<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle delete doctor
if (isset($_GET['delete_id'])) {
    $doctor_id = $_GET['delete_id'];
    
    // First get user_id from doctors table
    $stmt = $pdo->prepare("SELECT user_id FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // First, delete all appointments linked to this doctor
            $stmt = $pdo->prepare("DELETE FROM appointments WHERE doctor_id = ?");
            $stmt->execute([$doctor_id]);
            
            // Then delete from doctors table
            $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = ?");
            $stmt->execute([$doctor_id]);
            
            // Finally delete from users table
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$doctor['user_id']]);
            
            $pdo->commit();
            $success = "Doctor deleted successfully! All associated appointments have also been removed.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error deleting doctor: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$specialization_filter = isset($_GET['specialization']) ? $_GET['specialization'] : '';
$experience_filter = isset($_GET['experience']) ? $_GET['experience'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// Build query with filters
$sql = "
    SELECT d.*, u.full_name, u.email, u.phone, u.created_at 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR d.specialization LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($specialization_filter)) {
    $sql .= " AND d.specialization = ?";
    $params[] = $specialization_filter;
}

if (!empty($experience_filter)) {
    switch($experience_filter) {
        case '0-5':
            $sql .= " AND d.experience_years BETWEEN 0 AND 5";
            break;
        case '5-10':
            $sql .= " AND d.experience_years BETWEEN 5 AND 10";
            break;
        case '10-15':
            $sql .= " AND d.experience_years BETWEEN 10 AND 15";
            break;
        case '15+':
            $sql .= " AND d.experience_years > 15";
            break;
    }
}

// Sorting
switch($sort_by) {
    case 'name':
        $sql .= " ORDER BY u.full_name $sort_order";
        break;
    case 'experience':
        $sql .= " ORDER BY d.experience_years $sort_order";
        break;
    case 'specialization':
        $sql .= " ORDER BY d.specialization $sort_order";
        break;
    default:
        $sql .= " ORDER BY u.full_name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique specializations for filter dropdown
$specs = $pdo->query("SELECT DISTINCT specialization FROM doctors ORDER BY specialization")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors | SmartClinic</title>
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
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
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
        
        .btn-filter:hover {
            background: var(--primary-dark);
        }
        
        .btn-reset {
            background: #1080e2;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
        }
        
        .btn-reset:hover {
            background: #074a7d;
        }
        
        .active-filters {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .filter-badge {
            background: var(--primary-color);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>

<?php 
$page_title = "Manage Doctors";
include '../includes/header.php'; 
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_doctors.php" class="active">Manage Doctors</a></li>
            <li><a href="manage_patients.php">Manage Patients</a></li>
            <li><a href="manage_appointments.php">Manage Appointments</a></li>
            <li><a href="appointment_reports.php">Appointment Reports</a></li>
            <li><a href="manage_feedback.php">Manage Feedback</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="admin-page-header">
            <h1>Manage Doctors</h1>
            <a href="add_doctor.php" class="btn-admin-add">Add New Doctor</a>
        </div>
        
        <!-- Enhanced Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, email or specialization..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Specialization</label>
                        <select name="specialization">
                            <option value="">All Specializations</option>
                            <?php foreach($specs as $spec): ?>
                                <option value="<?php echo $spec; ?>" <?php echo $specialization_filter == $spec ? 'selected' : ''; ?>>
                                    <?php echo $spec; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Experience</label>
                        <select name="experience">
                            <option value="">Any Experience</option>
                            <option value="0-5" <?php echo $experience_filter == '0-5' ? 'selected' : ''; ?>>0-5 years</option>
                            <option value="5-10" <?php echo $experience_filter == '5-10' ? 'selected' : ''; ?>>5-10 years</option>
                            <option value="10-15" <?php echo $experience_filter == '10-15' ? 'selected' : ''; ?>>10-15 years</option>
                            <option value="15+" <?php echo $experience_filter == '15+' ? 'selected' : ''; ?>>15+ years</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Sort By</label>
                        <select name="sort_by">
                            <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="specialization" <?php echo $sort_by == 'specialization' ? 'selected' : ''; ?>>Specialization</option>
                            <option value="experience" <?php echo $sort_by == 'experience' ? 'selected' : ''; ?>>Experience</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Order</label>
                        <select name="sort_order">
                            <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">Apply Filters</button>
                        <a href="manage_doctors.php" class="btn-reset">Reset</a>
                    </div>
                </div>
            </form>
            
            <!-- Active Filters Display -->
            <?php if($search || $specialization_filter || $experience_filter): ?>
            <div class="active-filters">
                <strong>Active Filters:</strong>
                <?php if($search): ?>
                    <span class="filter-badge">Search: "<?php echo $search; ?>"</span>
                <?php endif; ?>
                <?php if($specialization_filter): ?>
                    <span class="filter-badge">Specialization: <?php echo $specialization_filter; ?></span>
                <?php endif; ?>
                <?php if($experience_filter): ?>
                    <span class="filter-badge">Experience: <?php echo $experience_filter; ?> years</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Results Summary -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <p>Total Doctors: <strong><?php echo count($doctors); ?></strong></p>
            <p>Showing <?php echo count($doctors); ?> of <?php echo $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn(); ?> total</p>
        </div>
        
        <?php if(empty($doctors)): ?>
            <div class="admin-empty-state">
                <h3>No doctors found</h3>
                <p>Try adjusting your filter criteria.</p>
                <a href="manage_doctors.php" class="btn-primary">Clear Filters</a>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Specialization</th>
                        <th>Experience</th>
                        <th>Fee</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </thead>
                <tbody>
                    <?php foreach($doctors as $doctor): ?>
                    <tr>
                        <td>#D<?php echo str_pad($doctor['id'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                        <td><?php echo $doctor['experience_years']; ?> years</td>
                        <td>₹<?php echo $doctor['consultation_fee']; ?></td>
                        <td><?php echo date('d M Y', strtotime($doctor['created_at'])); ?></td>
                        <td>
                            <div class="admin-actions">
                                <a href="edit_doctor.php?id=<?php echo $doctor['id']; ?>" class="btn-admin-edit">Edit</a>
                                <a href="manage_doctors.php?delete_id=<?php echo $doctor['id']; ?>" 
                                   class="btn-admin-delete" 
                                   onclick="return confirm('⚠️ WARNING: This will delete the doctor AND all their appointments. This action cannot be undone.\n\nAre you sure you want to delete Dr. <?php echo $doctor['full_name']; ?>?')">
                                    Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Info Box -->
        <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; font-size: 13px;">
            <strong>ℹ️ Note:</strong> Deleting a doctor will also delete all their associated appointments. Please ensure this action is necessary.
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>