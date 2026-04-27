<footer class="footer">
    <div class="footer-container">
        <div class="footer-logo">
            <h3>Smart<span>Clinic</span></h3>
        </div>
        <div class="footer-info">
            <p>© 2026 SmartClinic. All rights reserved.</p>
        </div>
        <?php
// Add this at the top of footer.php
$is_in_subfolder = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
                    strpos($_SERVER['PHP_SELF'], '/patient/') !== false || 
                    strpos($_SERVER['PHP_SELF'], '/doctor/') !== false);
$prefix = $is_in_subfolder ? '../' : '';
?>

<div class="footer-links">
    <a href="<?php echo $prefix; ?>index.php">Home</a> |
    <a href="<?php echo $prefix; ?>doctors.php">Doctors</a> |
    <a href="<?php echo $prefix; ?>services.php">Services</a> |
    <a href="<?php echo $prefix; ?>feedback.php">Feedback</a>
</div>
    </div>
</footer>

<script>
// Modal functions
function openLogin() { 
    document.getElementById("loginModal").style.display = "block"; 
}
function closeLogin() { 
    document.getElementById("loginModal").style.display = "none"; 
}
function openRegister() { 
    document.getElementById("registerModal").style.display = "block"; 
}
function closeRegister() { 
    document.getElementById("registerModal").style.display = "none"; 
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for(let modal of modals) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
}
</script>
</body>
</html>