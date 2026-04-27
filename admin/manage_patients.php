<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$age_filter = isset($_GET['age']) ? $_GET['age'] : '';
$registration_filter = isset($_GET['registration']) ? $_GET['registration'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// Build query with filters
$sql = "
    SELECT p.*, u.full_name, u.email, u.phone, u.created_at,
           TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as age
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($gender_filter)) {
    $sql .= " AND p.gender = ?";
    $params[] = $gender_filter;
}

if (!empty($age_filter)) {
    switch($age_filter) {
        case '0-18':
            $sql .= " AND TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) BETWEEN 0 AND 18";
            break;
        case '19-30':
            $sql .= " AND TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) BETWEEN 19 AND 30";
            break;
        case '31-50':
            $sql .= " AND TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) BETWEEN 31 AND 50";
            break;
        case '51+':
            $sql .= " AND TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) > 50";
            break;
    }
}

if (!empty($registration_filter)) {
    switch($registration_filter) {
        case 'today':
            $sql .= " AND DATE(u.created_at) = CURDATE()";
            break;
        case 'week':
            $sql .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $sql .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $sql .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}

// Sorting
switch($sort_by) {
    case 'name':
        $sql .= " ORDER BY u.full_name $sort_order";
        break;
    case 'email':
        $sql .= " ORDER BY u.email $sort_order";
        break;
    case 'registered':
        $sql .= " ORDER BY u.created_at $sort_order";
        break;
    case 'age':
        $sql .= " ORDER BY age $sort_order";
        break;
    default:
        $sql .= " ORDER BY u.full_name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients | SmartClinic</title>
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
            background: #047ae1;
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
        }
    </style>
</head>
<body>

<?php 
$page_title = "Manage Patients";
include '../includes/header.php'; 
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_doctors.php">Manage Doctors</a></li>
            <li><a href="manage_patients.php" class="active">Manage Patients</a></li>
            <li><a href="manage_appointments.php">Manage Appointments</a></li>
            <li><a href="appointment_reports.php">Appointment Reports</a></li>
            <li><a href="manage_feedback.php">Manage Feedback</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="admin-page-header">
            <h1>Manage Patients</h1>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">All Genders</option>
                            <option value="male" <?php echo $gender_filter == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $gender_filter == 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $gender_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Age Group</label>
                        <select name="age">
                            <option value="">All Ages</option>
                            <option value="0-18" <?php echo $age_filter == '0-18' ? 'selected' : ''; ?>>0-18 years</option>
                            <option value="19-30" <?php echo $age_filter == '19-30' ? 'selected' : ''; ?>>19-30 years</option>
                            <option value="31-50" <?php echo $age_filter == '31-50' ? 'selected' : ''; ?>>31-50 years</option>
                            <option value="51+" <?php echo $age_filter == '51+' ? 'selected' : ''; ?>>51+ years</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Registration</label>
                        <select name="registration">
                            <option value="">Any Time</option>
                            <option value="today" <?php echo $registration_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $registration_filter == 'week' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="month" <?php echo $registration_filter == 'month' ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="year" <?php echo $registration_filter == 'year' ? 'selected' : ''; ?>>Last year</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Sort By</label>
                        <select name="sort_by">
                            <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="email" <?php echo $sort_by == 'email' ? 'selected' : ''; ?>>Email</option>
                            <option value="registered" <?php echo $sort_by == 'registered' ? 'selected' : ''; ?>>Registration Date</option>
                            <option value="age" <?php echo $sort_by == 'age' ? 'selected' : ''; ?>>Age</option>
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
                        <a href="manage_patients.php" class="btn-reset">reset</a>
                    </div>
                </div>
            </form>
            
            <!-- Active Filters Display -->
            <?php if($search || $gender_filter || $age_filter || $registration_filter): ?>
            <div class="active-filters">
                <strong>Active Filters:</strong>
                <?php if($search): ?>
                    <span class="filter-badge">Search: "<?php echo $search; ?>"</span>
                <?php endif; ?>
                <?php if($gender_filter): ?>
                    <span class="filter-badge">Gender: <?php echo ucfirst($gender_filter); ?></span>
                <?php endif; ?>
                <?php if($age_filter): ?>
                    <span class="filter-badge">Age: <?php echo $age_filter; ?></span>
                <?php endif; ?>
                <?php if($registration_filter): ?>
                    <span class="filter-badge">Registered: <?php echo $registration_filter; ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Results Summary -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <p>Total Patients: <strong><?php echo count($patients); ?></strong></p>
            <p>Showing <?php echo count($patients); ?> of <?php echo $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(); ?> total</p>
        </div>
        
        <?php if(empty($patients)): ?>
            <div class="admin-empty-state">
                <h3>No patients found</h3>
                <p>Try adjusting your filter criteria.</p>
                <a href="manage_patients.php" class="btn-primary">Clear Filters</a>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($patients as $patient): 
                        $age = $patient['dob'] ? date_diff(date_create($patient['dob']), date_create('today'))->y : 'N/A';
                    ?>
                    <tr>
                        <td>#P<?php echo str_pad($patient['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['email']); ?></td>
                        <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                        <td><?php echo $patient['gender'] ? ucfirst($patient['gender']) : '<span style="color:#999;">Not set</span>'; ?></td>
                        <td><?php echo $age; ?></td>
                        <td><?php echo date('d M Y', strtotime($patient['created_at'])); ?></td>
                        <td>
                            <div class="admin-actions">
                                <a href="view_patient.php?id=<?php echo $patient['id']; ?>" class="btn-admin-view">View</a>
                                <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="btn-admin-edit">Edit</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>