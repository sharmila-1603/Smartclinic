<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $feedback_id = $_POST['feedback_id'];
    $new_status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $feedback_id]);
    
    $success = "Feedback status updated!";
}

// Handle delete
if (isset($_GET['delete'])) {
    $feedback_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->execute([$feedback_id]);
    $success = "Feedback deleted successfully!";
}

// Get filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$sql = "SELECT f.*, u.full_name as user_name FROM feedback f 
        LEFT JOIN users u ON f.user_id = u.id";
if ($status_filter) {
    $sql .= " WHERE f.status = '$status_filter'";
}
$sql .= " ORDER BY f.created_at DESC";

$feedbacks = $pdo->query($sql)->fetchAll();

include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_doctors.php">Manage Doctors</a></li>
            <li><a href="manage_patients.php">Manage Patients</a></li>
            <li><a href="manage_appointments.php">Manage Appointments</a></li>
            <li><a href="appointment_reports.php">Appointment Reports</a></li>
            <li><a href="manage_feedback.php" class="active">Manage Feedback</a></li>
            <li><a href="../auth/logout.php">Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="admin-page-header">
            <h1>Manage Feedback</h1>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filter Tabs -->
        <div style="margin-bottom: 20px;">
            <a href="?status=pending" class="btn <?php echo $status_filter == 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">Pending</a>
            <a href="?status=read" class="btn <?php echo $status_filter == 'read' ? 'btn-primary' : 'btn-secondary'; ?>">Read</a>
            <a href="?status=replied" class="btn <?php echo $status_filter == 'replied' ? 'btn-primary' : 'btn-secondary'; ?>">Replied</a>
            <a href="manage_feedback.php" class="btn <?php echo !$status_filter ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
        </div>
        
        <?php if(empty($feedbacks)): ?>
            <div class="admin-empty-state">
                <h3>No feedback found</h3>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($feedbacks as $fb): ?>
                    <tr>
                        <td>#FB<?php echo str_pad($fb['id'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($fb['name']); ?></td>
                        <td><?php echo htmlspecialchars($fb['email']); ?></td>
                        <td><?php echo htmlspecialchars($fb['subject']); ?></td>
                        <td><?php echo date('d M Y', strtotime($fb['created_at'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $fb['status']; ?>">
                                <?php echo ucfirst($fb['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="admin-actions">
                                <a href="#" class="btn-admin-view" onclick="viewFeedback(<?php echo $fb['id']; ?>)">View</a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="feedback_id" value="<?php echo $fb['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" style="padding:5px;">
                                        <option value="pending" <?php echo $fb['status']=='pending'?'selected':''; ?>>Pending</option>
                                        <option value="read" <?php echo $fb['status']=='read'?'selected':''; ?>>Read</option>
                                        <option value="replied" <?php echo $fb['status']=='replied'?'selected':''; ?>>Replied</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                                <a href="?delete=<?php echo $fb['id']; ?>" class="btn-admin-delete" onclick="return confirm('Delete this feedback?')">Delete</a>
                            </div>
                            
                            <!-- Hidden message div -->
                            <div id="msg-<?php echo $fb['id']; ?>" style="display:none;">
                                <strong>Message:</strong><br>
                                <?php echo nl2br(htmlspecialchars($fb['message'])); ?>
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
function viewFeedback(id) {
    var msg = document.getElementById('msg-' + id).innerHTML;
    alert(msg.replace(/<[^>]*>/g, '')); // Simple alert, you can create a modal instead
}
</script>

<?php include '../includes/footer.php'; ?>