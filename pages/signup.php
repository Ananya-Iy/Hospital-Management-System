<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';

$error = '';
$success = '';
$selected_role = $_GET['role'] ?? 'patient';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role       = trim($_POST['role']      ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $password   = trim($_POST['password']   ?? '');
    $confirm    = trim($_POST['confirm_password'] ?? '');
    $name       = $first_name . ' ' . $last_name;

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match';
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'An account with this email already exists';
        } else {
            // Insert into users table
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $password, $role, $phone);

            if ($stmt->execute()) {
                $user_id = $conn->insert_id;

                // Insert into role-specific table
                if ($role === 'patient') {
                    $dob                      = $_POST['date_of_birth'] ?? '';
                    $gender                   = $_POST['gender']        ?? '';
                    $blood_group              = $_POST['blood_group']   ?? '';
                    $emergency_contact_phone  = trim($_POST['emergency_contact_phone'] ?? '');
                    $address                  = trim($_POST['address']   ?? '');

                    $p = $conn->prepare("INSERT INTO patients (user_id, date_of_birth, gender, blood_group, address, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?)");
                    $p->bind_param("isssss", $user_id, $dob, $gender, $blood_group, $address, $emergency_contact_phone);
                    $p->execute();
                    $p->close();

                } elseif ($role === 'doctor') {
                    $spec   = trim($_POST['specialization']     ?? '');
                    $qual   = trim($_POST['qualification']       ?? '');
                    $exp    = intval($_POST['experience_years']  ?? 0);
                    $bio    = trim($_POST['bio']                 ?? '');

                    // Get or create specialization id
                    $s = $conn->prepare("SELECT id FROM specializations WHERE name = ?");
                    $s->bind_param("s", $spec);
                    $s->execute();
                    $s->bind_result($spec_id);
                    if (!$s->fetch()) {
                        $s->close();
                        $ins = $conn->prepare("INSERT INTO specializations (name) VALUES (?)");
                        $ins->bind_param("s", $spec);
                        $ins->execute();
                        $spec_id = $conn->insert_id;
                        $ins->close();
                    } else {
                        $s->close();
                    }

                    $d = $conn->prepare("INSERT INTO doctors (user_id, specialization_id, qualification, experience_years, bio) VALUES (?, ?, ?, ?, ?)");
                    $d->bind_param("issis", $user_id, $spec_id, $qual, $exp, $bio);
                    $d->execute();
                    $d->close();
                }

                $success = 'Account created successfully! You can now <a href="login.php?role=' . $role . '">sign in</a>.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }

            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valora · Create Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }

        :root {
            --n1: #F2F2F2; --n2: #E6E6E6; --n3: #DADADA; --n4: #C6C6C6;
            --n5: #9E9E9E; --n6: #6E6E6E; --n7: #3F3F3F; --n8: #1C1C1C;
            --maroon-50: #D8C9CE; --maroon-100: #C5A8B3; --maroon-200: #A56C7E;
            --maroon-300: #842646; --maroon-400: #7A2141; --maroon-500: #641732;
            --success-light: #C6D8D2; --success-deep: #2E955C;
            --error-light: #E2D0CD; --error-primary: #F04233; --error-deep: #B03125;
            --shadow-lg: 0 20px 40px -12px rgba(100,23,50,0.15);
        }

        html, body { height: 100%; }

        body {
            background-color: var(--n1);
            color: var(--n8);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
        }

        a { text-decoration: none; }

        /* ===== HEADER ===== */
        header {
            background: white;
            box-shadow: 0 2px 20px var(--n3);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1280px;
            margin: 0 auto;
        }

        .logo { font-size: 1.7rem; font-weight: 700; color: var(--maroon-300); letter-spacing: -0.3px; }
        .nav-links { display: flex; gap: 2.5rem; list-style: none; align-items: center; }
        .nav-links a { color: var(--n7); font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--maroon-300); }
        .nav-links a.active { color: var(--maroon-300); font-weight: 600; }

        /* ===== CONTAINER ===== */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
            width: 100%;
        }

        .page-head { text-align: center; margin-bottom: 2.5rem; }
        .page-head h1 { font-size: 2.8rem; font-weight: 700; color: var(--n8); }
        .page-head p { color: var(--n6); font-size: 1.1rem; margin-top: 0.3rem; }

        /* ===== ALERTS ===== */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 60px;
            margin: 0 auto 2rem;
            text-align: center;
            font-weight: 500;
            max-width: 700px;
        }

        .alert-error {
            background: var(--error-light);
            color: var(--error-deep);
            border: 1px solid var(--error-primary);
        }

        .alert-success {
            background: var(--success-light);
            color: var(--success-deep);
            border: 1px solid var(--success-deep);
        }

        /* ===== ROLE TABS ===== */
        .role-tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
        }

        .role-tab {
            padding: 0.8rem 2rem;
            border-radius: 40px;
            background: white;
            color: var(--n7);
            font-weight: 600;
            border: 1px solid var(--n3);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .role-tab i { color: var(--maroon-300); }
        .role-tab:hover { border-color: var(--maroon-200); transform: translateY(-2px); }
        .role-tab.active { background: var(--maroon-300); color: white; border-color: var(--maroon-300); }
        .role-tab.active i { color: white; }

        /* ===== CARD ===== */
        .signup-card {
            background: white;
            border-radius: 30px;
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--n3);
            max-width: 700px;
            margin: 0 auto;
        }

        .signup-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--maroon-100);
        }

        .signup-header-icon {
            width: 60px;
            height: 60px;
            background: var(--maroon-50);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--maroon-300);
            flex-shrink: 0;
        }

        .signup-header h2 { font-size: 2rem; color: var(--n8); }
        .signup-header p  { color: var(--n6); font-size: 0.95rem; }

        /* ===== FORM ===== */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group { margin-bottom: 1.2rem; }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: var(--n6);
            margin-bottom: 0.5rem;
        }

        .form-group label i { color: var(--maroon-300); margin-right: 0.3rem; }

        .input-wrapper {
            display: flex;
            align-items: center;
            background: var(--n1);
            border-radius: 12px;
            padding: 0.2rem 1rem;
            border: 2px solid transparent;
            transition: 0.2s;
        }

        .input-wrapper:focus-within { border-color: var(--maroon-200); background: white; }
        .input-wrapper > i { color: var(--n5); width: 24px; font-size: 1rem; flex-shrink: 0; }

        .input-wrapper input,
        .input-wrapper select,
        .input-wrapper textarea {
            width: 100%;
            padding: 0.9rem 0.5rem;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            outline: none;
            font-family: inherit;
        }

        .input-wrapper textarea { min-height: 80px; resize: vertical; }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            margin: 1.2rem 0;
            font-size: 0.9rem;
            color: var(--n6);
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--maroon-300);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .checkbox-group a { color: var(--maroon-300); }

        .signup-btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            background: var(--maroon-300);
            color: white;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 1rem 0;
        }

        .signup-btn:hover {
            background: var(--maroon-400);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -8px var(--maroon-200);
        }

        .login-link { text-align: center; color: var(--n6); }
        .login-link a { color: var(--maroon-300); font-weight: 600; }

        /* ===== FOOTER ===== */
        footer {
            background: var(--n7);
            color: var(--n2);
            padding: 2rem;
            text-align: center;
            border-top: 5px solid var(--maroon-300);
            margin-top: auto;
        }

        .footer-content { max-width: 1280px; margin: 0 auto; }
        .footer-copy { color: var(--n5); font-size: 0.85rem; margin-top: 0.5rem; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .signup-card { padding: 1.5rem; }
            nav { flex-direction: column; gap: 1rem; }
            .nav-links { flex-direction: column; gap: 1rem; text-align: center; }
        }
    </style>
</head>
<body>

<header>
    <nav>
        <a href="../index.php" class="logo">Valora</a>
        <ul class="nav-links">
          <li><a href="../index.php">Home</a></li>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php" class="active">Sign Up</a></li>
        </ul>
    </nav>
</header>

<div class="container">
    <div class="page-head">
        <h1>Create your account</h1>
        <p>Join Valora Medical Center today</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <div class="role-tabs">
        <a href="?role=patient"      class="role-tab <?php echo $selected_role == 'patient'      ? 'active' : ''; ?>">
            <i class="fas fa-user-injured"></i> Patient
        </a>
        <a href="?role=doctor"       class="role-tab <?php echo $selected_role == 'doctor'       ? 'active' : ''; ?>">
            <i class="fas fa-user-md"></i> Doctor
        </a>
        <a href="?role=receptionist" class="role-tab <?php echo $selected_role == 'receptionist' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i> Receptionist
        </a>
    </div>

    <!-- ===== PATIENT ===== -->
    <?php if ($selected_role == 'patient'): ?>
    <div class="signup-card">
        <div class="signup-header">
            <div class="signup-header-icon"><i class="fas fa-user-injured"></i></div>
            <div><h2>Patient Registration</h2><p>Create your patient account</p></div>
        </div>
        <form method="POST" action="?role=patient">
            <input type="hidden" name="role" value="patient">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> First Name</label>
                    <div class="input-wrapper"><i class="fas fa-user"></i>
                        <input type="text" name="first_name" placeholder="John" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Last Name</label>
                    <div class="input-wrapper"><i class="fas fa-user"></i>
                        <input type="text" name="last_name" placeholder="Doe" required>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <div class="input-wrapper"><i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="john@example.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone</label>
                    <div class="input-wrapper"><i class="fas fa-phone"></i>
                        <input type="tel" name="phone" placeholder="+973 1234 5678">
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Date of Birth</label>
                    <div class="input-wrapper"><i class="fas fa-calendar"></i>
                        <input type="date" name="date_of_birth">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-venus-mars"></i> Gender</label>
                    <div class="input-wrapper"><i class="fas fa-venus-mars"></i>
                        <select name="gender">
                            <option value="">Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tint"></i> Blood Group</label>
                    <div class="input-wrapper"><i class="fas fa-tint"></i>
                        <select name="blood_group">
                            <option value="">Select blood group</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Address</label>
                    <div class="input-wrapper"><i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="address" placeholder="Street address, city">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone-square-alt"></i> Emergency Contact Number</label>
                    <div class="input-wrapper"><i class="fas fa-phone"></i>
                        <input type="tel" name="emergency_contact_phone" placeholder="+973 9876 5432" required>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-wrapper"><i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password</label>
                    <div class="input-wrapper"><i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                </div>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="terms_p" required>
                <label for="terms_p">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
            </div>
            <button type="submit" class="signup-btn">
                <i class="fas fa-user-plus"></i> Create Patient Account
            </button>
            <p class="login-link">Already have an account? <a href="login.php?role=patient">Sign in</a></p>
        </form>
    </div>
    <?php endif; ?>

    <!-- ===== DOCTOR ===== -->
    <?php if ($selected_role == 'doctor'): ?>
    <div class="signup-card">
        <div class="signup-header">
            <div class="signup-header-icon"><i class="fas fa-user-md"></i></div>
            <div><h2>Doctor Registration</h2><p>Join our medical staff at Valora</p></div>
        </div>
        <form method="POST" action="?role=doctor">
            <input type="hidden" name="role" value="doctor">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> First Name</label>
                    <div class="input-wrapper"><i class="fas fa-user"></i>
                        <input type="text" name="first_name" placeholder="Jane" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Last Name</label>
                    <div class="input-wrapper"><i class="fas fa-user"></i>
                        <input type="text" name="last_name" placeholder="Smith" required>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <div class="input-wrapper"><i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="dr.smith@valora.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone</label>
                    <div class="input-wrapper"><i class="fas fa-phone"></i>
                        <input type="tel" name="phone" placeholder="+973 1234 5678">
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-stethoscope"></i> Specialization</label>
                    <div class="input-wrapper"><i class="fas fa-stethoscope"></i>
                        <select name="specialization" required>
                            <option value="">Select specialization</option>
                            <option>General Practice</option>
                            <option>Cardiology</option>
                            <option>Dermatology</option>
                            <option>Neurology</option>
                            <option>Orthopedics</option>
                            <option>Pediatrics</option>
                            <option>Psychiatry</option>
                            <option>Radiology</option>
                            <option>Surgery</option>
                            <option>Gynecology</option>
                            <option>Ophthalmology</option>
                            <option>ENT</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Years of Experience</label>
                    <div class="input-wrapper"><i class="fas fa-clock"></i>
                        <input type="number" name="experience_years" placeholder="5" min="0">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-graduation-cap"></i> Qualifications</label>
                <div class="input-wrapper"><i class="fas fa-graduation-cap"></i>
                    <input type="text" name="qualification" placeholder="MBBS, MD, PhD etc.">
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-comment"></i> Bio</label>
                <div class="input-wrapper"><i class="fas fa-comment"></i>
                    <textarea name="bio" placeholder="Tell us about your medical experience..."></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-wrapper"><i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password</label>
                    <div class="input-wrapper"><i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                </div>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="terms_d" required>
                <label for="terms_d">I confirm my credentials are valid and agree to the <a href="#">Terms of Service</a></label>
            </div>
            <button type="submit" class="signup-btn">
                <i class="fas fa-user-md"></i> Register as Doctor
            </button>
            <p class="login-link">Already have an account? <a href="login.php?role=doctor">Sign in</a></p>
        </form>
    </div>
    <?php endif; ?>

    <!-- ===== RECEPTIONIST ===== -->
    <?php if ($selected_role == 'receptionist'): ?>
    <div class="signup-card">
        <div class="signup-header">
            <div class="signup-header-icon"><i class="fas fa-user-tie"></i></div>
            <div><h2>Receptionist Registration</h2><p>Join our front desk team</p></div>
        </div>
        <form method="POST" action="?role=receptionist">
            <input type="hidden" name="role" value="receptionist">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> First Name</label>
                    <div class="input-wrapper"><i class="fas fa-user"></i>
                        <input type="text" name="first_name" placeholder="Sarah" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Last Name</label>
                    <div class="input-wrapper"><i class="fas fa-user"></i>
                        <input type="text" name="last_name" placeholder="Ahmed" required>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <div class="input-wrapper"><i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="s.ahmed@valora.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone</label>
                    <div class="input-wrapper"><i class="fas fa-phone"></i>
                        <input type="tel" name="phone" placeholder="+973 1234 5678">
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-wrapper"><i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password</label>
                    <div class="input-wrapper"><i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                </div>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="terms_r" required>
                <label for="terms_r">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
            </div>
            <button type="submit" class="signup-btn">
                <i class="fas fa-user-plus"></i> Register as Receptionist
            </button>
            <p class="login-link">Already have an account? <a href="login.php?role=receptionist">Sign in</a></p>
        </form>
    </div>
    <?php endif; ?>

</div>

<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem; font-weight:600;">Valora Medical Center</p>
        <p style="color:var(--n4); font-size:0.9rem;">This is a university project for educational purposes; all hospital information & services is  fictional.</p>
        <p class="footer-copy">&copy; 2026</p>
    </div>
</footer>

</body>
</html>