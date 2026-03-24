<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('patient');

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// Fetch current data
$user    = $conn->query("SELECT * FROM users    WHERE id      = $user_id")->fetch_assoc();
$patient = $conn->query("SELECT * FROM patients WHERE user_id = $user_id")->fetch_assoc();

// ── Handle profile update ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {

    $name              = sanitize($_POST['name']              ?? '');
    $phone             = sanitize($_POST['phone']             ?? '');
    $date_of_birth     = sanitize($_POST['date_of_birth']     ?? '');
    $gender            = sanitize($_POST['gender']            ?? '');
    $blood_group       = sanitize($_POST['blood_group']       ?? '');
    $address           = sanitize($_POST['address']           ?? '');
    $emergency_contact = sanitize($_POST['emergency_contact'] ?? '');
    $allergies         = sanitize($_POST['allergies']         ?? '');

    $stmt_user = $conn->prepare(
        "UPDATE users SET name = ?, phone = ? WHERE id = ?"
    );
    $stmt_user->bind_param("ssi", $name, $phone, $user_id);

    $stmt_pat = $conn->prepare(
        "UPDATE patients SET
            date_of_birth     = ?,
            gender            = ?,
            blood_group       = ?,
            address           = ?,
            emergency_contact = ?,
            allergies         = ?
         WHERE user_id = ?"
    );
    $stmt_pat->bind_param(
        "ssssssi",
        $date_of_birth, $gender, $blood_group,
        $address, $emergency_contact, $allergies,
        $user_id
    );

    if ($stmt_user->execute() && $stmt_pat->execute()) {
        $success = 'Profile updated successfully!';
        $_SESSION['name'] = $name;
        // Refresh
        $user    = $conn->query("SELECT * FROM users    WHERE id      = $user_id")->fetch_assoc();
        $patient = $conn->query("SELECT * FROM patients WHERE user_id = $user_id")->fetch_assoc();
    } else {
        $error = 'Failed to update profile. Please try again.';
    }

    $stmt_user->close();
    $stmt_pat->close();
}

// ── Handle password change ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Your DB uses plain text passwords (university project)
    if ($user['password'] !== $current) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new, $user_id);
        if ($stmt->execute()) {
            $success = 'Password changed successfully!';
            $user['password'] = $new;
        } else {
            $error = 'Failed to change password. Please try again.';
        }
        $stmt->close();
    }
}
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
            --shadow-sm: 0 4px 8px rgba(0,0,0,0.03);
            --shadow-md: 0 8px 16px rgba(0,0,0,0.04);
            --shadow-lg: 0 16px 24px rgba(0,0,0,0.04);
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

        /* ── HEADER ── */
        header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0; z-index: 50;
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
            bottom: -4px; left: 0;
            width: 40%; height: 3px;
            background: var(--maroon-300);
            border-radius: 4px;
        }

        .nav-right { display: flex; align-items: center; gap: 1rem; }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.5rem 1.2rem;
            background: white;
            border-radius: 100px;
            border: 1px solid var(--n3);
            transition: all 0.2s;
            color: var(--n8);
        }

        .user-profile:hover { border-color: var(--maroon-200); transform: translateY(-1px); }
        .user-profile .fa-user-circle { color: var(--maroon-300); font-size: 1.2rem; }
        .user-profile span { font-weight: 600; }

        .logout-btn {
            padding: 0.5rem 1.2rem;
            background: var(--n2);
            border-radius: 100px;
            font-weight: 600;
            color: var(--n7);
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .logout-btn:hover { background: var(--n3); }

        /* ── WRAPPER ── */
        .page-wrapper { flex: 1; display: flex; flex-direction: column; }

        .container {
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
        }

        /* ── TABS ── */
        .main-tabs {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 1.5rem auto;
            padding: 0.5rem;
            background: white;
            border-radius: 100px;
            box-shadow: var(--shadow-lg);
            max-width: 960px;
            border: 1px solid var(--n3);
            flex-wrap: wrap;
        }

        .tab {
            padding: 0.75rem 1.5rem;
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

        /* ── PAGE HEADER ── */
        .page-header { margin-bottom: 2rem; }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            position: relative;
            padding-bottom: 0.8rem;
        }

        .page-header h1::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0;
            width: 80px; height: 4px;
            background: linear-gradient(90deg, var(--maroon-300), var(--info-primary));
            border-radius: 4px;
        }

        /* ── ALERTS ── */
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
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

        /* ── PROFILE HERO ── */
        .profile-hero {
            background: linear-gradient(135deg, var(--maroon-300), var(--maroon-500));
            border-radius: 32px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-avatar {
            width: 110px; height: 110px;
            background: rgba(255,255,255,0.2);
            border-radius: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            border: 3px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(8px);
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .hero-info { flex: 1; position: relative; z-index: 1; }
        .hero-info h2 { font-size: 2rem; font-weight: 700; margin-bottom: 0.4rem; }
        .hero-info p  { opacity: 0.85; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; font-size: 0.95rem; }

        .blood-badge {
            display: inline-block;
            margin-top: 0.8rem;
            padding: 0.4rem 1.2rem;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.4);
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            backdrop-filter: blur(4px);
        }

        /* ── SECTION CARD ── */
        .section-card {
            background: white;
            border-radius: 28px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .section-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 6px; height: 100%;
            background: linear-gradient(180deg, var(--maroon-300), var(--info-primary));
        }

        .card-head {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.8rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--n2);
        }

        .card-head-icon {
            width: 46px; height: 46px;
            background: linear-gradient(135deg, var(--maroon-50), var(--info-light));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: var(--maroon-300);
            flex-shrink: 0;
        }

        .card-head h2 { font-size: 1.3rem; font-weight: 700; }

        /* ── FORM ── */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.2rem;
        }

        .fg { }
        .fg.full { grid-column: span 2; }

        .fg label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--n7);
            font-size: 0.9rem;
        }

        .fg label i { color: var(--maroon-300); margin-right: 0.4rem; }

        .fc {
            width: 100%;
            padding: 0.95rem 1.1rem;
            border: 2px solid var(--n3);
            border-radius: 14px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: var(--n1);
            color: var(--n8);
            font-family: inherit;
        }

        .fc:focus {
            outline: none;
            border-color: var(--maroon-300);
            background: white;
            box-shadow: 0 6px 14px -6px var(--maroon-200);
        }

        select.fc {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23842646' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            background-color: var(--n1);
        }

        textarea.fc { min-height: 100px; resize: vertical; }

        .fc[readonly] { background: var(--n2); color: var(--n6); cursor: not-allowed; border-color: var(--n4); }

        /* ── BUTTONS ── */
        .btn-row { display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; align-items: center; }

        .btn {
            padding: 0.9rem 2rem;
            border-radius: 40px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--maroon-300), var(--maroon-400));
            color: white;
            box-shadow: 0 8px 16px -4px var(--maroon-200);
        }

        .btn-primary:hover { background: linear-gradient(135deg, var(--maroon-400), var(--maroon-500)); transform: translateY(-2px); }

        .btn-ghost {
            background: transparent;
            color: var(--info-primary);
            font-weight: 600;
            padding: 0.9rem 1.2rem;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .btn-ghost:hover { color: var(--maroon-300); }

        .btn-secondary {
            background: var(--n2);
            color: var(--n7);
        }

        .btn-secondary:hover { background: var(--n3); }

        /* ── PASSWORD SECTION ── */
        .pwd-toggle {
            cursor: pointer;
            color: var(--info-primary);
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: none;
            border: none;
            font-family: inherit;
            padding: 0;
            transition: color 0.2s;
        }

        .pwd-toggle:hover { color: var(--maroon-300); }

        .pwd-panel {
            display: none;
            background: var(--n1);
            border-radius: 20px;
            padding: 1.8rem;
            margin-top: 1.2rem;
            border: 1px solid var(--n3);
        }

        .pwd-panel.open { display: block; }

        .pwd-panel h3 { font-size: 1.1rem; margin-bottom: 1.2rem; color: var(--maroon-300); }

        /* ── FOOTER ── */
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

        @media (max-width: 768px) {
            nav { flex-direction: column; gap: 0.8rem; padding: 1rem; }
            .container { padding: 1rem; }
            .main-tabs { border-radius: 24px; padding: 0.6rem; }
            .tab { padding: 0.6rem 1rem; font-size: 0.85rem; }
            .form-grid { grid-template-columns: 1fr; }
            .fg.full { grid-column: span 1; }
            .profile-hero { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header>
    <nav>
      <a href="patient-dashboard.php" class="logo">Valora</a> 
        <div class="nav-right">
            <a href="profile.php" class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
            </a>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>
</header>

<div class="page-wrapper">

    <!-- TABS -->
    <div class="main-tabs">
        <a href="appointments.php"         class="tab"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
        <a href="myappointments.php" class="tab"><i class="fas fa-calendar-alt"></i> My Appointments</a>
        <a href="finddoc.php"      class="tab"><i class="fas fa-user-md"></i> Find Doctors</a>
        <a href="reports.php"      class="tab"><i class="fas fa-file-medical"></i> Medical Records</a>
        <a href="billings.php"      class="tab"><i class="fas fa-file-invoice"></i> Billing</a>
        <a href="profile.php"      class="tab active"><i class="fas fa-user"></i> Profile</a>
    </div>

    <div class="container">

        <div class="page-header"><h1>My Profile</h1></div>

        <!-- ALERTS -->
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- PROFILE HERO -->
        <div class="profile-hero">
            <div class="hero-avatar"><i class="fas fa-user-circle"></i></div>
            <div class="hero-info">
                <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><i class="fas fa-phone"></i>    <?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></p>
                <?php if (!empty($patient['date_of_birth'])): ?>
                <p><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($patient['date_of_birth'])); ?></p>
                <?php endif; ?>
                <div class="blood-badge">
                    <i class="fas fa-tint"></i>
                    <?php echo !empty($patient['blood_group']) ? htmlspecialchars($patient['blood_group']) : 'Blood group not set'; ?>
                </div>
            </div>
        </div>

        <!-- PROFILE FORM -->
        <form method="POST" action="">

            <!-- Personal Info -->
            <div class="section-card">
                <div class="card-head">
                    <div class="card-head-icon"><i class="fas fa-user-edit"></i></div>
                    <h2>Personal Information</h2>
                </div>
                <div class="form-grid">
                    <div class="fg">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="name" class="fc" required
                               value="<?php echo htmlspecialchars($user['name']); ?>">
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" class="fc" readonly
                               value="<?php echo htmlspecialchars($user['email']); ?>"
                               title="Email cannot be changed">
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="phone" class="fc"
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-calendar"></i> Date of Birth</label>
                        <input type="date" name="date_of_birth" class="fc"
                               value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-venus-mars"></i> Gender</label>
                        <select name="gender" class="fc">
                            <option value="">Select Gender</option>
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                            <option value="<?php echo $g; ?>" <?php echo ($patient['gender'] ?? '') === $g ? 'selected' : ''; ?>>
                                <?php echo $g; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-tint"></i> Blood Group</label>
                        <select name="blood_group" class="fc">
                            <option value="">Select Blood Group</option>
                            <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                            <option value="<?php echo $bg; ?>" <?php echo ($patient['blood_group'] ?? '') === $bg ? 'selected' : ''; ?>>
                                <?php echo $bg; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg full">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea name="address" class="fc" rows="3"><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="section-card">
                <div class="card-head">
                    <div class="card-head-icon"><i class="fas fa-ambulance"></i></div>
                    <h2>Emergency Contact</h2>
                </div>
                <div class="form-grid">
                    <div class="fg full">
                        <label><i class="fas fa-user-friends"></i> Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="fc"
                               placeholder="e.g. Jane Doe – +973 3333 4444"
                               value="<?php echo htmlspecialchars($patient['emergency_contact'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Medical Info -->
            <div class="section-card">
                <div class="card-head">
                    <div class="card-head-icon"><i class="fas fa-notes-medical"></i></div>
                    <h2>Medical Information</h2>
                </div>
                <div class="form-grid">
                    <div class="fg full">
                        <label><i class="fas fa-allergies"></i> Allergies</label>
                        <textarea name="allergies" class="fc" rows="3"
                                  placeholder="e.g. Penicillin, Peanuts..."><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Save -->
            <div class="btn-row">
                <button type="submit" name="save_profile" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="patient-dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>

        </form>

       

    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem; font-weight:600;">Valora Medical Center</p>
        <p style="color:var(--n4); font-size:0.9rem;">University Project · All information is fictional</p>
        <p class="footer-copy">&copy; 2026 Valora HMS. All rights reserved.</p>
    </div>
</footer>

<script>
    function togglePwd() {
        document.getElementById('pwdPanel').classList.toggle('open');
    }

    <?php if ($success && isset($_POST['change_password'])): ?>
        // Auto-open password panel if there was a password error/success
        document.getElementById('pwdPanel').classList.add('open');
    <?php endif; ?>
    <?php if ($error && isset($_POST['change_password'])): ?>
        document.getElementById('pwdPanel').classList.add('open');
    <?php endif; ?>
</script>

</body>
</html>