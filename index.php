<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="index.php" class="logo">🏥 Hospital Management</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <?php if (isLoggedIn()): ?>
                    <?php if (hasRole('patient')): ?>
                        <li><a href="patient/dashboard.php">Dashboard</a></li>
                    <?php elseif (hasRole('doctor')): ?>
                        <li><a href="doctor/dashboard.php">Dashboard</a></li>
                    <?php elseif (hasRole('receptionist')): ?>
                        <li><a href="receptionist/dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div class="hero">
        <h1>Welcome to Our Hospital Management System</h1>
        <p>Providing Quality Healthcare Services with Modern Technology</p>
        <?php if (!isLoggedIn()): ?>
            <a href="register.php" class="btn btn-success" style="margin-right: 1rem;">Get Started</a>
            <a href="login.php" class="btn btn-primary">Login</a>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="features-grid">
            <div class="feature-card card">
                <div style="font-size: 3rem; color: var(--secondary-color); margin-bottom: 1rem;">👥</div>
                <h3>For Patients</h3>
                <p>Book appointments online, view your medical history, download prescriptions, and manage your healthcare journey.</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-primary" style="margin-top: 1rem;">Register Now</a>
                <?php endif; ?>
            </div>

            <div class="feature-card card">
                <div style="font-size: 3rem; color: var(--success-color); margin-bottom: 1rem;">👨‍⚕️</div>
                <h3>For Doctors</h3>
                <p>Manage your appointments, view patient records, create prescriptions, and set your availability.</p>
                <?php if (hasRole('doctor')): ?>
                    <a href="doctor/dashboard.php" class="btn btn-success" style="margin-top: 1rem;">Go to Dashboard</a>
                <?php endif; ?>
            </div>

            <div class="feature-card card">
                <div style="font-size: 3rem; color: var(--warning-color); margin-bottom: 1rem;">💼</div>
                <h3>For Receptionists</h3>
                <p>Register walk-in patients, manage appointments, generate invoices, and handle billing efficiently.</p>
                <?php if (hasRole('receptionist')): ?>
                    <a href="receptionist/dashboard.php" class="btn btn-warning" style="margin-top: 1rem;">Go to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-top: 3rem;">
            <h2 class="card-header">Our Services</h2>
            <div class="features-grid">
                <div>
                    <h4>🔍 Easy Doctor Search</h4>
                    <p>Find doctors by specialization and check their availability in real-time.</p>
                </div>
                <div>
                    <h4>📅 Online Booking</h4>
                    <p>Book, reschedule, or cancel appointments at your convenience.</p>
                </div>
                <div>
                    <h4>💊 Digital Prescriptions</h4>
                    <p>Access and download your prescriptions anytime, anywhere.</p>
                </div>
                <div>
                    <h4>📊 Medical History</h4>
                    <p>Keep track of all your appointments and medical records.</p>
                </div>
                <div>
                    <h4>💳 Digital Invoices</h4>
                    <p>View and download invoices after your consultation.</p>
                </div>
                <div>
                    <h4>🔔 Reminders</h4>
                    <p>Get notified about your upcoming appointments.</p>
                </div>
            </div>
        </div>

        <?php if (!isLoggedIn()): ?>
        <div class="card" style="text-align: center; margin-top: 2rem;">
            <h2>Ready to Get Started?</h2>
            <p style="margin: 1rem 0;">Join our hospital management system today and experience modern healthcare.</p>
            <a href="register.php" class="btn btn-primary" style="margin-right: 1rem;">Register as Patient</a>
            <a href="login.php" class="btn btn-secondary">Already have an account?</a>
        </div>
        <?php endif; ?>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
