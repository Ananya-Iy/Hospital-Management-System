<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];

// Get doctor record with specialization
$doctor = null;
$user = null;
if ($user_id) {
    $result = $conn->query(
        "SELECT d.*, u.name, u.email, u.phone, u.is_active, s.name as specialization_name
         FROM doctors d
         JOIN users u ON d.user_id = u.id
         JOIN specializations s ON d.specialization_id = s.id
         WHERE u.id = $user_id LIMIT 1"
    );
    $doctor = $result->fetch_assoc();
}

if (!$doctor) {
    header("Location: doctor-dashboard.php");
    exit;
}

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience_years = intval($_POST['experience_years'] ?? 0);
    $consultation_fee = floatval($_POST['consultation_fee'] ?? 0);
    $bio = trim($_POST['bio'] ?? '');
    $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
    $available_from = trim($_POST['available_from'] ?? '09:00');
    $available_to = trim($_POST['available_to'] ?? '17:00');

    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } else {
        // Check if email is already taken by another user
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'This email is already in use by another account.';
        } else {
            $conn->begin_transaction();
            
            try {
                // Update users table
                $stmt = $conn->prepare(
                    "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?"
                );
                $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Update doctors table
                $stmt = $conn->prepare(
                    "UPDATE doctors SET qualification = ?, experience_years = ?, consultation_fee = ?, 
                     bio = ?, available_days = ?, available_from = ?, available_to = ? 
                     WHERE user_id = ?"
                );
                $stmt->bind_param("sidsssi", $qualification, $experience_years, $consultation_fee, 
                                  $bio, $available_days, $available_from, $available_to, $user_id);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                
                // Update session
                $_SESSION['name'] = $name;
                
                $success = 'Profile updated successfully!';
                
                // Refresh doctor data
                $result = $conn->query(
                    "SELECT d.*, u.name, u.email, u.phone, u.is_active, s.name as specialization_name
                     FROM doctors d
                     JOIN users u ON d.user_id = u.id
                     JOIN specializations s ON d.specialization_id = s.id
                     WHERE u.id = $user_id LIMIT 1"
                );
                $doctor = $result->fetch_assoc();
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to update profile. Please try again.';
            }
        }
        $check->close();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();

        if (!password_verify($current_password, $user_data['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user_id);
            
            if ($stmt->execute()) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password.';
            }
            $stmt->close();
        }
    }
}

// Parse available days
$available_days_array = !empty($doctor['available_days']) ? explode(',', $doctor['available_days']) : [];
$all_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile · Valora Medical Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }

        :root {
            --n1: #F2F2F2; --n2: #E6E6E6; --n3: #DADADA; --n4: #C6C6C6;
            --n5: #9E9E9E; --n6: #6E6E6E; --n7: #3F3F3F; --n8: #1C1C1C;
            --maroon-50: #D8C9CE; --maroon-100: #C5A8B3; --maroon-200: #A56C7E;
            --maroon-300: #842646; --maroon-400: #7A2141; --maroon-500: #641732;
            --success-light: #C6D8D2; --success-primary: #39C37A; --success-deep: #2E955C;
            --error-light: #E2D0CD; --error-primary: #F04233; --error-deep: #B03125;
            --info-light: #C9D3E6; --info-primary: #0E3E9E;
            --bg-body: #F8F9FC;
            --shadow-md: 0 8px 16px rgba(0,0,0,0.04);
            --shadow-xl: 0 24px 32px rgba(100,23,50,0.08);
        }

        html, body { height: 100%; }

        body {
            background-color: var(--bg-body);
            color: var(--n8);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
        }

        a { text-decoration: none; }

        /* ===== HEADER ===== */
        header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid rgba(218,218,218,0.3);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--maroon-300);
            letter-spacing: -0.5px;
            position: relative;
        }

        .logo::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 40%;
            height: 3px;
            background: var(--maroon-300);
            border-radius: 4px;
        }

        .nav-links { display: flex; gap: 2rem; list-style: none; align-items: center; }
        .nav-links a { color: var(--n7); font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--maroon-300); }

        .logout-btn {
            background: var(--n2);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: var(--n7) !important;
        }

        .logout-btn:hover { background: var(--n3); }

        /* ===== TABS ===== */
        .main-tabs {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 1.5rem auto;
            padding: 0.5rem;
            background: white;
            border-radius: 100px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            max-width: 960px;
            border: 1px solid var(--n3);
            flex-wrap: wrap;
        }

        .tab {
            padding: 0.7rem 1.4rem;
            border-radius: 100px;
            font-weight: 600;
            color: var(--n7);
            transition: all 0.25s;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.92rem;
            white-space: nowrap;
        }

        .tab i { color: var(--maroon-300); font-size: 0.95rem; transition: color 0.25s; }
        .tab:hover { background: var(--maroon-50); color: var(--maroon-300); }
        .tab.active { background: var(--maroon-300); color: white; box-shadow: 0 8px 16px -4px rgba(132,38,70,0.4); }
        .tab.active i { color: white; }

        /* ===== CONTAINER ===== */
        .container {
            max-width: 1000px;
            margin: 1rem auto 3rem;
            padding: 0 2rem;
            flex: 1;
            width: 100%;
        }

        /* ===== PAGE HEADER ===== */
        .page-header { margin-bottom: 2rem; }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--n8);
            position: relative;
            padding-bottom: 0.8rem;
        }

        .page-header h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--maroon-300), var(--info-primary));
            border-radius: 4px;
        }

        .page-header p { color: var(--n6); margin-top: 0.8rem; }

        /* ===== ALERTS ===== */
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-success { background: var(--success-light); color: var(--success-deep); border-left: 6px solid var(--success-primary); }
        .alert-error   { background: var(--error-light);   color: var(--error-deep);   border-left: 6px solid var(--error-primary); }
        .alert i { font-size: 1.4rem; }

        /* ===== PROFILE HEADER ===== */
        .profile-header-card {
            background: linear-gradient(135deg, var(--maroon-300), var(--maroon-500));
            color: white;
            border-radius: 32px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .profile-header-card::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .profile-main {
            display: flex;
            align-items: center;
            gap: 2rem;
            position: relative;
            z-index: 1;
        }

        .profile-avatar-large {
            width: 100px; height: 100px;
            background: white;
            border-radius: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--maroon-300);
            flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }

        .profile-main h1 { font-size: 2rem; margin-bottom: 0.3rem; }
        .profile-main p { opacity: 0.9; }
        .profile-specialty { 
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        /* ===== SECTION ===== */
        .section {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--maroon-300);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        /* ===== FORM ===== */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group { margin-bottom: 1.5rem; }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.6rem;
            color: var(--n7);
            font-size: 0.95rem;
        }

        .form-group label i { color: var(--maroon-300); margin-right: 0.4rem; }

        .form-control {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid var(--n3);
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s;
            background: var(--n1);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--maroon-300);
            background: white;
        }

        .form-control:disabled {
            background: var(--n2);
            color: var(--n6);
            cursor: not-allowed;
        }

        textarea.form-control { min-height: 120px; resize: vertical; }

        /* ===== CHECKBOXES ===== */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--maroon-300);
        }

        .checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        /* ===== INFO DISPLAY ===== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            padding: 1.2rem;
            background: var(--n1);
            border-radius: 16px;
        }

        .info-item i { color: var(--maroon-300); margin-right: 0.6rem; }
        .info-item strong { display: block; margin-bottom: 0.5rem; color: var(--n7); }
        .info-item span { color: var(--n8); font-size: 1.1rem; }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 1rem 2rem;
            border-radius: 100px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--maroon-300), var(--maroon-400));
            color: white;
            box-shadow: 0 8px 16px -4px var(--maroon-200);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--maroon-400), var(--maroon-500));
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--n2);
            color: var(--n7);
        }

        .btn-secondary:hover {
            background: var(--n3);
        }

        /* ===== FOOTER ===== */
        footer {
            background: var(--n7);
            color: var(--n2);
            padding: 2rem;
            text-align: center;
            border-top: 5px solid var(--maroon-300);
            margin-top: auto;
        }

        .footer-content { max-width: 1400px; margin: 0 auto; }
        .footer-copy { color: var(--n5); font-size: 0.85rem; margin-top: 0.5rem; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            nav { flex-direction: column; gap: 1rem; }
            .nav-links { flex-direction: column; text-align: center; gap: 1rem; }
            .profile-main { flex-direction: column; text-align: center; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <nav>
      <a href="dashboard-doctor.php" class="logo">Valora</a>
        <ul class="nav-links">
            <li><a href="doctor-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</header>

<!-- ===== TABS ===== -->
<div class="main-tabs">
    <a href="appointments.php" class="tab">
        <i class="fas fa-calendar-alt"></i> Appointments
    </a>
    <a href="patients.php" class="tab">
        <i class="fas fa-users"></i> Patients
    </a>
    <a href="prescriptions.php" class="tab">
        <i class="fas fa-prescription"></i> Prescriptions
    </a>
    <a href="availability.php" class="tab">
        <i class="fas fa-calendar-times"></i> Schedule
    </a>
    <a href="profile.php" class="tab active">
        <i class="fas fa-user-md"></i> Profile
    </a>
</div>

<div class="container">
    <div class="page-header">
        <h1>My Profile</h1>
        <p>Manage your professional information and account settings</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- PROFILE HEADER -->
    <div class="profile-header-card">
        <div class="profile-main">
            <div class="profile-avatar-large">
                <i class="fas fa-user-md"></i>
            </div>
            <div>
                <h1>Dr. <?php echo htmlspecialchars($doctor['name']); ?></h1>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></p>
                <span class="profile-specialty">
                    <i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor['specialization_name']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- PROFESSIONAL INFORMATION -->
    <div class="section">
        <h2 class="section-title">
            <i class="fas fa-id-card"></i> Professional Information
        </h2>
        <div class="info-grid">
            <div class="info-item">
                <i class="fas fa-graduation-cap"></i>
                <strong>Qualification</strong>
                <span><?php echo htmlspecialchars($doctor['qualification'] ?: 'Not specified'); ?></span>
            </div>
            <div class="info-item">
                <i class="fas fa-clock"></i>
                <strong>Experience</strong>
                <span><?php echo $doctor['experience_years']; ?> years</span>
            </div>
            <div class="info-item">
                <i class="fas fa-dollar-sign"></i>
                <strong>Consultation Fee</strong>
                <span>BD <?php echo number_format($doctor['consultation_fee'], 2); ?></span>
            </div>
            <div class="info-item">
                <i class="fas fa-phone"></i>
                <strong>Phone</strong>
                <span><?php echo htmlspecialchars($doctor['phone'] ?: 'Not specified'); ?></span>
            </div>
        </div>
    </div>

    <!-- EDIT PROFILE -->
    <div class="section">
        <h2 class="section-title">
            <i class="fas fa-edit"></i> Edit Profile
        </h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($doctor['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($doctor['phone'] ?: ''); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-stethoscope"></i> Specialization</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($doctor['specialization_name']); ?>" disabled>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-graduation-cap"></i> Qualification</label>
                    <input type="text" name="qualification" class="form-control" value="<?php echo htmlspecialchars($doctor['qualification'] ?: ''); ?>" placeholder="e.g., MBBS, MD">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Years of Experience</label>
                    <input type="number" name="experience_years" class="form-control" value="<?php echo $doctor['experience_years']; ?>" min="0">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> Consultation Fee (BD)</label>
                    <input type="number" name="consultation_fee" class="form-control" value="<?php echo $doctor['consultation_fee']; ?>" min="0" step="0.01">
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-info-circle"></i> Bio</label>
                <textarea name="bio" class="form-control" placeholder="Brief description about yourself and your expertise..."><?php echo htmlspecialchars($doctor['bio'] ?: ''); ?></textarea>
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar-week"></i> Available Days</label>
                <div class="checkbox-group">
                    <?php foreach ($all_days as $day): ?>
                    <div class="checkbox-item">
                        <input type="checkbox" name="available_days[]" value="<?php echo $day; ?>" 
                               id="day_<?php echo $day; ?>"
                               <?php echo in_array($day, $available_days_array) ? 'checked' : ''; ?>>
                        <label for="day_<?php echo $day; ?>"><?php echo $day; ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Available From</label>
                    <input type="time" name="available_from" class="form-control" value="<?php echo $doctor['available_from']; ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Available To</label>
                    <input type="time" name="available_to" class="form-control" value="<?php echo $doctor['available_to']; ?>">
                </div>
            </div>

            <button type="submit" name="update_profile" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="section">
        <h2 class="section-title">
            <i class="fas fa-lock"></i> Change Password
        </h2>
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-key"></i> Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
            </div>

            <button type="submit" name="change_password" class="btn btn-primary">
                <i class="fas fa-shield-alt"></i> Change Password
            </button>
        </form>
    </div>

</div>

<!-- ===== FOOTER ===== -->
<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem; font-weight:600;">Valora Medical Center</p>
        <p style="color:var(--n4); font-size:0.9rem;">University Project · All information is fictional</p>
        <p class="footer-copy">&copy; 2026 Valora HMS. All rights reserved.</p>
    </div>
</footer>

</body>
</html>