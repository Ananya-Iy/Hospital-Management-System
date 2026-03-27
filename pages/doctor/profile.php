<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];

$result = $conn->query(
    "SELECT d.*, u.name, u.email, u.phone, s.name AS specialization_name
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     JOIN specializations s ON d.specialization_id = s.id
     WHERE u.id = $user_id LIMIT 1"
);
$doctor = $result->fetch_assoc();

if (!$doctor) { header("Location: doctor-dashboard.php"); exit; }

$success = '';
$error   = '';

// ── Update profile ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name              = sanitize($_POST['name']              ?? '');
    $phone             = sanitize($_POST['phone']             ?? '');
    $qualification     = sanitize($_POST['qualification']     ?? '');
    $experience_years  = intval($_POST['experience_years']    ?? 0);
    $consultation_fee  = floatval($_POST['consultation_fee']  ?? 0);
    $bio               = sanitize($_POST['bio']               ?? '');

    if (empty($name)) {
        $error = 'Name is required.';
    } else {
        // Update users table
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $phone, $user_id);
        $stmt->execute();
        $stmt->close();

        // Update doctors table — type string: s i d s
        $stmt = $conn->prepare(
            "UPDATE doctors SET
                qualification     = ?,
                experience_years  = ?,
                consultation_fee  = ?,
                bio               = ?
             WHERE user_id = ?"
        );
        $stmt->bind_param(
            "sidss",
            $qualification,
            $experience_years,
            $consultation_fee,
            $bio,
            $user_id
        );

        if ($stmt->execute()) {
            $_SESSION['name'] = $name;
            $success = 'Profile updated successfully!';
            // Refresh
            $result = $conn->query(
                "SELECT d.*, u.name, u.email, u.phone, s.name AS specialization_name
                 FROM doctors d JOIN users u ON d.user_id = u.id
                 JOIN specializations s ON d.specialization_id = s.id
                 WHERE u.id = $user_id LIMIT 1"
            );
            $doctor = $result->fetch_assoc();
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
        $stmt->close();
    }
}

// ── Change password (plain text — university project) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $pw_row = $conn->query("SELECT password FROM users WHERE id = $user_id")->fetch_assoc();

    if ($pw_row['password'] !== $current) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new, $user_id);
        $stmt->execute() ? $success = 'Password changed successfully!' : $error = 'Failed to change password.';
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile · Valora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif}
        :root{
            --n1:#F2F2F2;--n2:#E6E6E6;--n3:#DADADA;--n4:#C6C6C6;--n5:#9E9E9E;--n6:#6E6E6E;--n7:#3F3F3F;--n8:#1C1C1C;
            --m50:#D8C9CE;--m100:#C5A8B3;--m200:#A56C7E;--m300:#842646;--m400:#7A2141;--m500:#641732;
            --sl:#C6D8D2;--sp:#39C37A;--sd:#2E955C;
            --el:#E2D0CD;--ep:#F04233;--ed:#B03125;
            --il:#C9D3E6;--ip:#0E3E9E;
            --bg:#F8F9FC;
            --sh-md:0 8px 16px rgba(0,0,0,.04);
            --sh-lg:0 16px 24px rgba(0,0,0,.04);
            --sh-xl:0 24px 32px rgba(100,23,50,.08);
        }
        html,body{height:100%}
        body{background:var(--bg);color:var(--n8);min-height:100vh;display:flex;flex-direction:column;line-height:1.5}
        a{text-decoration:none}
        header{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);box-shadow:var(--sh-md);position:sticky;top:0;z-index:50;border-bottom:1px solid rgba(218,218,218,.3)}
        nav{display:flex;justify-content:space-between;align-items:center;padding:1rem 2rem;max-width:1400px;margin:0 auto}
        .logo{font-size:1.8rem;font-weight:800;color:var(--m300);letter-spacing:-.5px;position:relative}
        .logo::after{content:'';position:absolute;bottom:-4px;left:0;width:40%;height:3px;background:var(--m300);border-radius:4px}
        .nav-right{display:flex;align-items:center;gap:1rem}
        .user-pill{display:flex;align-items:center;gap:.8rem;padding:.5rem 1.2rem;background:white;border-radius:100px;border:1px solid var(--n3);color:var(--n8);font-weight:600}
        .user-pill i{color:var(--m300)}
        .logout-btn{padding:.5rem 1.2rem;background:var(--n2);border-radius:100px;font-weight:600;color:var(--n7);font-size:.9rem}
        .logout-btn:hover{background:var(--n3)}
        .main-tabs{display:flex;justify-content:center;gap:.5rem;margin:1.5rem auto;padding:.5rem;background:white;border-radius:100px;box-shadow:var(--sh-lg);max-width:960px;border:1px solid var(--n3);flex-wrap:wrap}
        .tab{padding:.7rem 1.4rem;border-radius:100px;font-weight:600;color:var(--n7);transition:.25s;display:flex;align-items:center;gap:.45rem;font-size:.92rem;white-space:nowrap}
        .tab i{color:var(--m300);font-size:.95rem}
        .tab:hover{background:var(--m50);color:var(--m300)}
        .tab.active{background:var(--m300);color:white;box-shadow:0 8px 16px -4px rgba(132,38,70,.4)}
        .tab.active i{color:white}
        .page-wrapper{flex:1;display:flex;flex-direction:column}
        .container{max-width:1000px;margin:1rem auto 3rem;padding:0 2rem;flex:1;width:100%}
        .page-header{margin-bottom:2rem}
        .page-header h1{font-size:2.2rem;font-weight:700;position:relative;padding-bottom:.8rem}
        .page-header h1::after{content:'';position:absolute;bottom:0;left:0;width:80px;height:4px;background:linear-gradient(90deg,var(--m300),var(--ip));border-radius:4px}
        .page-header p{color:var(--n6);margin-top:.8rem}
        .alert{padding:1rem 1.5rem;border-radius:14px;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:var(--sl);color:var(--sd);border-left:6px solid var(--sp)}
        .alert-error{background:var(--el);color:var(--ed);border-left:6px solid var(--ep)}

        /* HERO */
        .hero{background:linear-gradient(135deg,var(--m300),var(--m500));color:white;border-radius:32px;padding:2.5rem;margin-bottom:2rem;box-shadow:var(--sh-xl);position:relative;overflow:hidden;display:flex;align-items:center;gap:2rem;flex-wrap:wrap}
        .hero::before{content:'';position:absolute;top:-60px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(255,255,255,.1) 0%,transparent 70%);border-radius:50%;pointer-events:none}
        .hero-av{width:100px;height:100px;background:white;border-radius:26px;display:flex;align-items:center;justify-content:center;font-size:3rem;color:var(--m300);flex-shrink:0;box-shadow:0 8px 24px rgba(0,0,0,.2);position:relative;z-index:1}
        .hero-info{position:relative;z-index:1}
        .hero-info h1{font-size:1.9rem;font-weight:700;margin-bottom:.3rem}
        .hero-info p{opacity:.85;font-size:.9rem;display:flex;align-items:center;gap:.5rem;margin-bottom:.2rem}
        .spec-pill{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);padding:.35rem 1rem;border-radius:40px;font-size:.88rem;font-weight:600;margin-top:.5rem;display:inline-block}

        /* SECTION */
        .section{background:white;border-radius:24px;padding:2rem;margin-bottom:2rem;box-shadow:var(--sh-md);border:1px solid var(--n3)}
        .sec-title{font-size:1.2rem;font-weight:700;color:var(--m300);margin-bottom:1.5rem;display:flex;align-items:center;gap:.6rem}

        /* INFO CARDS */
        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem}
        .ii{padding:1rem 1.2rem;background:var(--n1);border-radius:14px}
        .ii i{color:var(--m300);margin-right:.4rem}
        .ii strong{display:block;font-size:.78rem;color:var(--n5);text-transform:uppercase;font-weight:600;margin-bottom:.3rem}
        .ii span{font-weight:700;font-size:.95rem}

        /* FORM */
        .form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.2rem;margin-bottom:1.2rem}
        .fg{margin-bottom:0}
        .fg label{display:block;font-weight:600;margin-bottom:.5rem;color:var(--n7);font-size:.9rem}
        .fg label i{color:var(--m300);margin-right:.3rem}
        .fc{width:100%;padding:.95rem 1.1rem;border:2px solid var(--n3);border-radius:14px;font-size:.95rem;transition:.2s;background:var(--n1);font-family:inherit;color:var(--n8)}
        .fc:focus{outline:none;border-color:var(--m300);background:white}
        .fc:disabled{background:var(--n2);color:var(--n6);cursor:not-allowed}
        textarea.fc{resize:vertical;min-height:110px}

        /* DAY CHECKBOXES */
        .days-wrap{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:.3rem}
        .day-cb{position:relative}
        .day-cb input{display:none}
        .day-cb label{display:block;padding:.5rem 1.1rem;border-radius:40px;border:2px solid var(--n3);font-weight:600;font-size:.88rem;cursor:pointer;transition:.2s;background:var(--n1);color:var(--n6)}
        .day-cb input:checked + label{background:var(--m300);color:white;border-color:var(--m300)}
        .day-cb label:hover{border-color:var(--m200)}

        /* BUTTONS */
        .btn{padding:.95rem 2rem;border-radius:40px;font-weight:700;font-size:.95rem;border:none;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:.6rem;font-family:inherit}
        .btn-primary{background:linear-gradient(135deg,var(--m300),var(--m400));color:white;box-shadow:0 8px 16px -4px var(--m200)}
        .btn-primary:hover{background:linear-gradient(135deg,var(--m400),var(--m500));transform:translateY(-2px)}
        .btn-secondary{background:var(--n2);color:var(--n7)}
        .btn-secondary:hover{background:var(--n3)}

        footer{background:var(--n7);color:var(--n2);padding:2rem;text-align:center;border-top:5px solid var(--m300);margin-top:auto}
        .footer-content{max-width:1400px;margin:0 auto}
        .footer-copy{color:var(--n5);font-size:.85rem;margin-top:.5rem}

        @media(max-width:768px){nav{flex-direction:column;gap:1rem}.container{padding:0 1rem}.hero{flex-direction:column;text-align:center}.form-row{grid-template-columns:1fr}}
    </style>
</head>
<body>

<header>
    <nav>
        <a href="doctor-dashboard.php" class="logo">Valora</a>
        <div class="nav-right">
            <span class="user-pill"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
</header>

<div class="page-wrapper">
    <div class="main-tabs">
        <a href="appointments.php"  class="tab"><i class="fas fa-calendar-alt"></i> Appointments</a>
        <a href="patients.php"      class="tab"><i class="fas fa-users"></i> Patients</a>
        <a href="prescriptions.php" class="tab"><i class="fas fa-prescription"></i> Prescriptions</a>
        <a href="availability.php"  class="tab"><i class="fas fa-calendar-times"></i> Schedule</a>
        <a href="profile.php"       class="tab active"><i class="fas fa-user-md"></i> Profile</a>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>My Profile</h1>
            <p>Manage your professional information and account settings</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- HERO -->
        <div class="hero">
            <div class="hero-av"><i class="fas fa-user-md"></i></div>
            <div class="hero-info">
                <h1>Dr. <?php echo htmlspecialchars($doctor['name']); ?></h1>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone'] ?? 'Not set'); ?></p>
                <span class="spec-pill"><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor['specialization_name']); ?></span>
            </div>
        </div>

        <!-- QUICK INFO -->
        <div class="section">
            <div class="sec-title"><i class="fas fa-id-card"></i> Professional Overview</div>
            <div class="info-grid">
                <div class="ii"><i class="fas fa-graduation-cap"></i><strong>Qualification</strong><span><?php echo htmlspecialchars($doctor['qualification'] ?: '—'); ?></span></div>
                <div class="ii"><i class="fas fa-clock"></i><strong>Experience</strong><span><?php echo $doctor['experience_years']; ?> years</span></div>
                <div class="ii"><i class="fas fa-money-bill-wave"></i><strong>Consultation Fee</strong><span>BD <?php echo number_format($doctor['consultation_fee'], 2); ?></span></div>
            </div>
        </div>

        <!-- EDIT PROFILE -->
        <div class="section">
            <div class="sec-title"><i class="fas fa-edit"></i> Edit Profile</div>
            <form method="POST">
                <div class="form-row">
                    <div class="fg">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="name" class="fc" required value="<?php echo htmlspecialchars($doctor['name']); ?>">
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-envelope"></i> Email <span style="color:var(--n5);font-weight:400">(read-only)</span></label>
                        <input type="email" class="fc" disabled value="<?php echo htmlspecialchars($doctor['email']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <input type="text" name="phone" class="fc" value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>" placeholder="+973 XXXX XXXX">
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-stethoscope"></i> Specialization <span style="color:var(--n5);font-weight:400">(read-only)</span></label>
                        <input type="text" class="fc" disabled value="<?php echo htmlspecialchars($doctor['specialization_name']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg">
                        <label><i class="fas fa-graduation-cap"></i> Qualification</label>
                        <input type="text" name="qualification" class="fc" value="<?php echo htmlspecialchars($doctor['qualification'] ?? ''); ?>" placeholder="e.g. MBBS, MD">
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-clock"></i> Years of Experience</label>
                        <input type="number" name="experience_years" class="fc" min="0" max="60" value="<?php echo intval($doctor['experience_years']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg">
                        <label><i class="fas fa-money-bill-wave"></i> Consultation Fee (BD)</label>
                        <input type="number" name="consultation_fee" class="fc" min="0" step="0.01"
                               value="<?php echo number_format($doctor['consultation_fee'], 2, '.', ''); ?>">
                    </div>
                    <div class="fg">
                        <!-- spacer -->
                    </div>
                </div>
                <div class="fg" style="margin-bottom:1.2rem">
                    <label><i class="fas fa-info-circle"></i> Bio</label>
                    <textarea name="bio" class="fc" placeholder="Brief description about your expertise..."><?php echo htmlspecialchars($doctor['bio'] ?? ''); ?></textarea>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- CHANGE PASSWORD -->
        <div class="section">
            <div class="sec-title"><i class="fas fa-lock"></i> Change Password</div>
            <form method="POST">
                <div class="fg" style="margin-bottom:1.2rem">
                    <label><i class="fas fa-key"></i> Current Password</label>
                    <input type="password" name="current_password" class="fc" required placeholder="Enter current password">
                </div>
                <div class="form-row">
                    <div class="fg">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" name="new_password" class="fc" required minlength="6" placeholder="Min. 6 characters">
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" name="confirm_password" class="fc" required minlength="6" placeholder="Repeat new password">
                    </div>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">
                    <i class="fas fa-shield-alt"></i> Change Password
                </button>
            </form>
        </div>

    </div>
</div>

<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem;font-weight:600">Valora Medical Center</p>
        <p style="color:var(--n4);font-size:.9rem">University Project · All information is fictional</p>
        <p class="footer-copy">&copy; 2026 Valora HMS. All rights reserved.</p>
    </div>
</footer>

</body>
</html>