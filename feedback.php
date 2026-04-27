<?php
session_start();
require_once 'config/database.php';

// Initialize variables
$name = $email = $subject = $message = '';
$success = $error = '';

// Check if user is logged in as patient
$is_patient = isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient';

// If not logged in as patient, show message and prevent form submission
if (!$is_patient) {
    $error = "Only registered patients can submit feedback. Please <a href='#' onclick='openLogin(); return false;' style='color:var(--primary-color);'>login</a> to continue.";
}

// Handle form submission (only for logged-in patients)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_patient) {
    
    // Get and sanitize input
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $user_id = $_SESSION['user_id'];
    $name = $_SESSION['full_name'];
    $email = $_SESSION['email'];
    
    // Validation
    $errors = [];
    
    // Validate subject
    if (empty($subject)) {
        $errors[] = "Subject is required";
    } elseif (strlen($subject) < 3) {
        $errors[] = "Subject must be at least 3 characters";
    } elseif (strlen($subject) > 200) {
        $errors[] = "Subject must be less than 200 characters";
    }
    
    // Validate message
    if (empty($message)) {
        $errors[] = "Message is required";
    } elseif (strlen($message) < 10) {
        $errors[] = "Message must be at least 10 characters";
    } elseif (strlen($message) > 5000) {
        $errors[] = "Message must be less than 5000 characters";
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO feedback (user_id, name, email, subject, message, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $name, $email, $subject, $message]);
            
            $success = "Thank you for your feedback! We'll get back to you soon.";
            
            // Clear form fields after successful submission
            $subject = $message = '';
            
        } catch (PDOException $e) {
            $error = "Sorry, something went wrong. Please try again later.";
            error_log("Feedback save error: " . $e->getMessage());
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

$page_title = "Feedback & Contact";
include 'includes/header.php';
?>

<style>
.feedback-container {
    min-height: 70vh;
    padding: 50px 10%;
    max-width: 900px;
    margin: 0 auto;
}

.feedback-header {
    text-align: center;
    margin-bottom: 40px;
}

.feedback-header h1 {
    font-size: 2.5rem;
    color: var(--dark-color);
    margin-bottom: 15px;
}

.feedback-header p {
    color: #666;
    font-size: 1.1rem;
}

.feedback-form {
    background: white;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    font-family: 'Poppins', sans-serif;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 150px;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 30px;
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

.btn-submit {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    padding: 14px 40px;
    border: none;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    max-width: 300px;
    margin: 20px auto 0;
    display: block;
}

.btn-submit:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,123,255,0.3);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Patient Info Card */
.patient-info-card {
    background: #e3f2fd;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-left: 4px solid var(--primary-color);
}

.patient-avatar {
    width: 50px;
    height: 50px;
    background: var(--primary-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    font-weight: 600;
}

.patient-details h4 {
    margin: 0;
    color: var(--dark-color);
}

.patient-details p {
    margin: 5px 0 0;
    color: #666;
    font-size: 0.9rem;
}

.contact-info {
    margin-top: 50px;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 15px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.contact-item {
    text-align: center;
}

.contact-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 auto 15px;
}

.contact-item h3 {
    color: var(--dark-color);
    margin-bottom: 10px;
}

.contact-item p {
    color: #666;
    line-height: 1.6;
}

.restricted-message {
    text-align: center;
    padding: 60px 40px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.restricted-message .icon {
    font-size: 4rem;
    margin-bottom: 20px;
}

.restricted-message h3 {
    color: var(--dark-color);
    margin-bottom: 15px;
}

.restricted-message p {
    color: #666;
    margin-bottom: 25px;
}

.btn-login-prompt {
    background: var(--primary-color);
    color: white;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-login-prompt:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .feedback-container {
        padding: 30px 20px;
    }
    
    .feedback-form {
        padding: 25px;
    }
    
    .btn-submit {
        max-width: 100%;
    }
    
    .patient-info-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<div class="feedback-container">
    <div class="feedback-header">
        <h1>Share Your Feedback</h1>
        <p>We value your opinion. Help us improve our services.</p>
    </div>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error && !$is_patient): ?>
        <div class="restricted-message">
            <div class="icon">🔒</div>
            <h3>Feedback Access Restricted</h3>
            <p>Only registered patients can submit feedback. Please login to continue.</p>
            <a href="#" onclick="openLogin(); return false;" class="btn-login-prompt">Login to Your Account</a>
            <div style="margin-top: 20px;">
                <small>Don't have an account? <a href="#" onclick="openRegister(); return false;" style="color:var(--primary-color);">Register here</a></small>
            </div>
        </div>
    <?php elseif($error && $is_patient): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($is_patient): ?>
        
        <!-- Patient Info Card -->
        <div class="patient-info-card">
            <div class="patient-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <div class="patient-details">
                <h4>Submitting as: <?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <p><?php echo htmlspecialchars($_SESSION['email']); ?></p>
            </div>
        </div>
        
        <form method="POST" action="" class="feedback-form" id="feedbackForm">
            <div class="form-group">
                <label for="subject" class="required-field">Subject</label>
                <input type="text" 
                       id="subject" 
                       name="subject" 
                       value="<?php echo htmlspecialchars($subject); ?>" 
                       placeholder="What is this about?"
                       required>
            </div>
            
            <div class="form-group">
                <label for="message" class="required-field">Your Feedback</label>
                <textarea id="message" 
                          name="message" 
                          placeholder="Please share your experience, suggestions, or concerns..." 
                          required><?php echo htmlspecialchars($message); ?></textarea>
            </div>
            
            <button type="submit" class="btn-submit" id="submitBtn">Submit Feedback</button>
        </form>
        
    <?php endif; ?>
    
    <div class="contact-info">
        <div class="contact-item">
            <div class="contact-icon">📍</div>
            <h3>Visit Us</h3>
            <p>123 Healthcare Street<br>Medical City, MC 12345</p>
        </div>
        
        <div class="contact-item">
            <div class="contact-icon">📞</div>
            <h3>Call Us</h3>
            <p>+1 (234) 567-8900<br>Mon-Fri, 8:00 - 20:00</p>
        </div>
        
        <div class="contact-item">
            <div class="contact-icon">✉️</div>
            <h3>Email Us</h3>
            <p>support@smartclinic.com<br>feedback@smartclinic.com</p>
        </div>
    </div>
</div>

<script>
// Client-side validation for logged-in patients
<?php if($is_patient): ?>
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    let isValid = true;
    const subject = document.getElementById('subject').value.trim();
    const message = document.getElementById('message').value.trim();
    
    // Reset error styles
    document.querySelectorAll('.form-group input, .form-group textarea').forEach(field => {
        field.classList.remove('error');
    });
    
    // Validate subject
    if (subject.length < 3) {
        document.getElementById('subject').classList.add('error');
        isValid = false;
    }
    
    // Validate message
    if (message.length < 10) {
        document.getElementById('message').classList.add('error');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill all fields correctly. Subject must be at least 3 characters and message at least 10 characters.');
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>