<?php
session_start();
require_once 'config/database.php';

// Display booking errors if any
if (isset($_SESSION['booking_error'])) {
    echo '<div class="alert alert-error" style="position: fixed; top: 80px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 350px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">';
    echo '<strong>⚠️ Access Denied</strong><br>';
    echo htmlspecialchars($_SESSION['booking_error']);
    echo '</div>';
    unset($_SESSION['booking_error']);
    
    echo '<script>
        setTimeout(function() {
            var alert = document.querySelector(".alert-error");
            if(alert) alert.style.display = "none";
        }, 5000);
    </script>';
}

// Display registration errors if any
if (isset($_SESSION['errors'])) {
    echo '<div style="background:#f8d7da; color:#721c24; padding:15px; margin:10px auto; max-width:500px; border-radius:5px; text-align:center; border-left:5px solid #dc3545;">';
    echo '<strong style="font-size:18px;">Registration Failed:</strong>';
    echo '<ul style="text-align:left; margin-top:10px;">';
    foreach($_SESSION['errors'] as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
    unset($_SESSION['errors']);
}

// Display login errors if any
if (isset($_SESSION['login_error'])) {
    echo '<div style="background:#f8d7da; color:#721c24; padding:15px; margin:10px auto; max-width:500px; border-radius:5px; text-align:center;">';
    echo htmlspecialchars($_SESSION['login_error']);
    echo '</div>';
    unset($_SESSION['login_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Healthcare | Clinic Management System</title>
    <link rel="stylesheet" href="style_project.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
/* ============================================ */
/* ZIG-ZAG FEATURES SECTION - DIRECT IN HTML    */
/* ============================================ */

.features-zigzag {
    padding: 80px 0;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
}

.zigzag-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    flex-direction: column;
    gap: 80px;
}

.zigzag-item {
    display: flex;
    align-items: center;
    gap: 50px;
}

.zigzag-item.reverse {
    flex-direction: row-reverse;
}

.zigzag-image {
    flex: 1;
    position: relative;
}

.image-wrapper {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.image-wrapper img {
    width: 100%;
    height: 350px;
    object-fit: cover;
    display: block;
    transition: transform 0.5s ease;
}

.zigzag-item:hover .image-wrapper img {
    transform: scale(1.05);
}

.feature-number {
    position: absolute;
    bottom: -15px;
    right: -15px;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #007bff, #28a745);
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}

.zigzag-content {
    flex: 1;
}

.feature-icon {
    font-size: 3rem;
    margin-bottom: 15px;
    display: inline-block;
}

.zigzag-content h3 {
    font-size: 1.8rem;
    color: #2c3e50;
    margin-bottom: 15px;
}

.zigzag-content p {
    color: #6c757d;
    line-height: 1.8;
    margin-bottom: 20px;
}

.feature-list {
    list-style: none;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.feature-list li {
    color: #495057;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.feature-list li::before {
    content: '✓';
    color: #28a745;
    font-weight: bold;
}

@media (max-width: 768px) {
    .zigzag-item,
    .zigzag-item.reverse {
        flex-direction: column;
        gap: 30px;
        text-align: center;
    }
    
    .feature-list {
        grid-template-columns: 1fr;
        text-align: left;
        max-width: 250px;
        margin: 0 auto;
    }
    
    .image-wrapper img {
        height: 250px;
    }
    
    .feature-number {
        width: 45px;
        height: 45px;
        font-size: 1.2rem;
    }
}
</style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <h1>Healthcare at Your Fingertips</h1>
        <p>Skip the queues and schedule your care in seconds.</p>
        <div class="hero-btns">
            <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'patient'): ?>
                <a href="patient/book_appointment.php" class="btn-primary">Book Appointment</a>
            <?php elseif(isset($_SESSION['user_id']) && $_SESSION['role'] == 'doctor'): ?>
                <a href="doctor/dashboard.php" class="btn-primary">Go to Dashboard</a>
            <?php elseif(isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin'): ?>
                <a href="admin/dashboard.php" class="btn-primary">Admin Panel</a>
            <?php else: ?>
                <a href="#" class="btn-primary" onclick="openLogin(); return false;">Login to Book</a>
            <?php endif; ?>
            <a href="services.php" class="btn-secondary">View Services</a>
        </div>
    </div>
</section>

<!-- About Section -->
<section class="about">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Who We Are</span>
            <h2>About <span class="highlight">SmartClinic</span></h2>
            <div class="divider"></div>
            <p class="section-description">Transforming healthcare delivery through innovation, technology, and compassionate care</p>
        </div>
        
        <div class="about-content">
            <div class="about-text">
                <p class="lead">Revolutionizing healthcare through technology, making quality medical care accessible to everyone, everywhere.</p>
                
                <p class="about-description">SmartClinic is a cutting-edge digital healthcare platform designed to bridge the gap between patients and medical professionals. Founded with a vision to make healthcare seamless and efficient, we combine advanced technology with compassionate care to deliver the best possible experience for our patients.</p>
                
                <div class="trust-badges">
                    <div class="trust-badge">
                        <div class="badge-icon">⭐</div>
                        <div class="badge-text">4.9/5 Rating</div>
                    </div>
                    <div class="trust-badge">
                        <div class="badge-icon">🏆</div>
                        <div class="badge-text">Award Winning</div>
                    </div>
                    <div class="trust-badge">
                        <div class="badge-icon">🔒</div>
                        <div class="badge-text">HIPAA Compliant</div>
                    </div>
                    <div class="trust-badge">
                        <div class="badge-icon">💳</div>
                        <div class="badge-text">Insurance Accepted</div>
                    </div>
                </div>
                
                <div class="stats-container">
                    <div class="stat-box">
                        <div class="stat-number">5000+</div>
                        <div class="stat-label">Happy Patients</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Expert Doctors</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">15k+</div>
                        <div class="stat-label">Appointments</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Support</div>
                    </div>
                </div>
                
                <div class="about-features">
                    <div class="about-feature">
                        <div class="feature-icon">✓</div>
                        <div class="feature-content">
                            <h4>Board Certified Doctors</h4>
                            <p>Experienced professionals with verified credentials</p>
                        </div>
                    </div>
                    <div class="about-feature">
                        <div class="feature-icon">✓</div>
                        <div class="feature-content">
                            <h4>Secure Health Records</h4>
                            <p>Your medical history protected with advanced encryption</p>
                        </div>
                    </div>
                    <div class="about-feature">
                        <div class="feature-icon">✓</div>
                        <div class="feature-content">
                            <h4>Instant Appointments</h4>
                            <p>Book appointments in seconds with real-time availability</p>
                        </div>
                    </div>
                    <div class="about-feature">
                        <div class="feature-icon">✓</div>
                        <div class="feature-content">
                            <h4>Affordable Healthcare</h4>
                            <p>Quality care at prices you can afford</p>
                        </div>
                    </div>
                </div>
                
                <a href="doctors.php" class="btn-about">Meet Our Doctors</a>
            </div>
            
            <div class="about-image">
                <div class="image-card">
                    <div class="image-placeholder">
                        <div class="image-icon">🏥🩺</div>
                    </div>
                </div>
                <div class="floating-card card-1">
                    <div class="card-icon">👨‍⚕️</div>
                    <div class="card-text">Expert Doctors</div>
                </div>
                <div class="floating-card card-2">
                    <div class="card-icon">❤️</div>
                    <div class="card-text">Patient First</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section - Zig Zag Pattern (Alternating Left/Right) -->
<section class="features-zigzag">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Why Choose Us</span>
            <h2>Our <span class="highlight">Features</span></h2>
            <div class="divider"></div>
            <p class="section-description">Discover what makes SmartClinic the preferred choice for thousands of patients</p>
        </div>

        <div class="zigzag-container">
            <!-- Feature 1 - Content on RIGHT, Image on LEFT -->
            <div class="zigzag-item">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=500&h=350&fit=crop" alt="Easy Booking">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">01</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">📅</div>
                    <h3>Easy Booking</h3>
                    <p>Book appointments with just a few clicks. Choose your preferred doctor, select a convenient time slot, and get instant confirmation. No more waiting on hold or playing phone tag.</p>
                    <ul class="feature-list">
                        <li>24/7 Online Booking</li>
                        <li>Real-time Availability</li>
                        <li>Instant Confirmation</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 2 - Content on LEFT, Image on RIGHT (reverse) -->
            <div class="zigzag-item reverse">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://medicaldialogues.in/h-upload/2021/11/24/164774-specialist-doctors.jpg" alt="Expert Doctors">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">02</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">👨‍⚕️</div>
                    <h3>Expert Doctors</h3>
                    <p>Consult with India's best medical professionals across 15+ specializations. All our doctors are board-certified with years of experience in their respective fields.</p>
                    <ul class="feature-list">
                        <li>50+ Experienced Doctors</li>
                        <li>Board Certified</li>
                        <li>Multiple Specializations</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 3 - Content on RIGHT, Image on LEFT -->
            <div class="zigzag-item">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=500&h=350&fit=crop" alt="Secure Records">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">03</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">🔒</div>
                    <h3>Secure Records</h3>
                    <p>Your medical history is protected with advanced encryption. Access your health records anytime, anywhere with complete peace of mind.</p>
                    <ul class="feature-list">
                        <li>HIPAA Compliant</li>
                        <li>End-to-End Encryption</li>
                        <li>Secure Data Storage</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 4 - Content on LEFT, Image on RIGHT (reverse) -->
            <div class="zigzag-item reverse">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://unitedhospitals.com/sites/doctor-consultation1.jpg" alt="Video Consultation">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">04</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">🎥</div>
                    <h3>Video Consultation</h3>
                    <p>Connect with doctors from the comfort of your home. Secure HD video calls with real-time prescription and follow-up scheduling.</p>
                    <ul class="feature-list">
                        <li>HD Video Quality</li>
                        <li>Real-time Chat</li>
                        <li>Digital Prescriptions</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 5 - Content on RIGHT, Image on LEFT -->
            <div class="zigzag-item">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?w=500&h=350&fit=crop" alt="24/7 Support">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">05</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">💬</div>
                    <h3>24/7 Support</h3>
                    <p>Our dedicated support team is always here to help. Get assistance with bookings, prescriptions, or any other queries anytime.</p>
                    <ul class="feature-list">
                        <li>24/7 Availability</li>
                        <li>Multiple Support Channels</li>
                        <li>Quick Response Time</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 6 - Content on LEFT, Image on RIGHT (reverse) - NEW -->
            <div class="zigzag-item reverse">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://images.unsplash.com/photo-1530026186672-2cd00ffc50fe?w=500&h=350&fit=crop" alt="Modern Diagnostics">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">06</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">🔬</div>
                    <h3>Modern Diagnostics</h3>
                    <p>State-of-the-art diagnostic equipment for accurate results. Digital reports accessible from your patient portal.</p>
                    <ul class="feature-list">
                        <li>Advanced Technology</li>
                        <li>Accurate Results</li>
                        <li>Digital Reports</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 7 - Content on RIGHT, Image on LEFT - NEW -->
            <div class="zigzag-item">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://sudhahospitals.com/_next/image?url=%2F_next%2Fstatic%2Fmedia%2Femergency-overview.40fb9415.webp&w=3840&q=75" alt="Ambulance Services">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">07</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">🚑</div>
                    <h3>Ambulance Services</h3>
                    <p>Fully equipped ambulances with trained paramedics. Available 24/7 for emergency transportation.</p>
                    <ul class="feature-list">
                        <li>24/7 Availability</li>
                        <li>Trained Paramedics</li>
                        <li>Quick Response</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 8 - Content on LEFT, Image on RIGHT (reverse) - NEW -->
            <div class="zigzag-item reverse">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://eliteextra.com/wp-content/uploads/2022/02/AdobeStock_432533244-scaled.jpeg" alt="Pharmacy Delivery">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">08</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">💊</div>
                    <h3>Pharmacy Delivery</h3>
                    <p>Home delivery of prescribed medicines. Partnered with trusted pharmacies for authentic medications.</p>
                    <ul class="feature-list">
                        <li>Home Delivery</li>
                        <li>Authentic Medicines</li>
                        <li>Fast Service</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 9 - Content on RIGHT, Image on LEFT - NEW -->
            <div class="zigzag-item">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://d35oenyzp35321.cloudfront.net/emergency_critical_care_d8e396be8b.jpg" alt="Emergency Care">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">09</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">🕐</div>
                    <h3>Emergency Care</h3>
                    <p>Round-the-clock emergency medical services with specialized trauma care units and experienced emergency physicians.</p>
                    <ul class="feature-list">
                        <li>24/7 Emergency</li>
                        <li>Trauma Care</li>
                        <li>Critical Care</li>
                    </ul>
                </div>
            </div>

            <!-- Feature 10 - Content on LEFT, Image on RIGHT (reverse) - NEW -->
            <div class="zigzag-item reverse">
                <div class="zigzag-image">
                    <div class="image-wrapper">
                        <img src="https://images.unsplash.com/photo-1563013544-824ae1b704d3?w=500&h=350&fit=crop" alt="Online Payments">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="feature-number">10</div>
                </div>
                <div class="zigzag-content">
                    <div class="feature-icon">💳</div>
                    <h3>Online Payments</h3>
                    <p>Secure digital payment options including cards, UPI, net banking, and insurance claims. Cashless and hassle-free.</p>
                    <ul class="feature-list">
                        <li>Multiple Payment Modes</li>
                        <li>Secure Transactions</li>
                        <li>Insurance Accepted</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Login Modal -->
<div id="loginModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeLogin()" title="Close">&times;</span>
        <h2>Login to Your Account</h2>
        <form action="auth/login.php" method="POST">
            <select name="role" id="role" required>
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="doctor">Doctor</option>
                <option value="patient">Patient</option>
            </select>
            <input type="email" name="email" id="email" placeholder="Email" required>
            <input type="password" name="password" id="password" placeholder="Password" required>
            <button type="submit" class="btn-primary">Login</button>
        </form>
    </div>
</div>

<!-- Register Modal -->
<div id="registerModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeRegister()" title="Close">&times;</span>
        <h2>Create Patient Account</h2>
        <form action="auth/register.php" method="POST">
            <input type="text" name="full_name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="tel" name="phone" placeholder="Phone Number" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit" class="btn-secondary">Register</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>