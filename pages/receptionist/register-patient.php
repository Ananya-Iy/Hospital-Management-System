<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('receptionist');

$success = '';
$error = '';

// Handle patient registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Name, email, and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'This email is already registered.';
        } else {
            $conn->begin_transaction();
            
            try {
                // Create user account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'patient';
                
                $stmt = $conn->prepare(
                    "INSERT INTO users (name, email, password, role, phone) 
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("sssss", $name, $email, $hashed_password, $role, $phone);
                $stmt->execute();
                $user_id = $conn->insert_id;
                $stmt->close();
                
                // Create patient record
                $stmt = $conn->prepare(
                    "INSERT INTO patients (user_id, date_of_birth, gender, blood_group, address, allergies, emergency_contact_name, emergency_contact_phone) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("isssssss", $user_id, $dob, $gender, $blood_group, $address, $allergies, $emergency_contact_name, $emergency_contact_phone);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                $success = 'Patient registered successfully! You can now book an appointment for them.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to register patient. Please try again.';
            }
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
    <title>Register Patient · Valora Medical Center</title>
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
        body { background-color: var(--bg-body); color: var(--n8); min-height: 100vh; display: flex; flex-direction: column; line-height: 1.5; }
        a { text-decoration: none; }
        header { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); box-shadow: var(--shadow-md); position: sticky; top: 0; z-index: 50; border-bottom: 1px solid rgba(218,218,218,0.3); }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1400px; margin: 0 auto; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--maroon-300); letter-spacing: -0.5px; position: relative; }
        .logo::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 40%; height: 3px; background: var(--maroon-300); border-radius: 4px; }
        .nav-links { display: flex; gap: 2rem; list-style: none; align-items: center; }
        .nav-links a { color: var(--n7); font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--maroon-300); }
        .logout-btn { background: var(--n2); padding: 0.5rem 1.2rem; border-radius: 40px; font-weight: 600; color: var(--n7) !important; }
        .logout-btn:hover { background: var(--n3); }
        .container { max-width: 900px; margin: 2rem auto 3rem; padding: 0 2rem; flex: 1; width: 100%; }
        .back-btn { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--maroon-300); font-weight: 600; margin-bottom: 1.5rem; transition: all 0.2s; }
        .back-btn:hover { gap: 0.8rem; }
        .alert { padding: 1.2rem 1.5rem; border-radius: 16px; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: var(--success-light); color: var(--success-deep); border-left: 6px solid var(--success-primary); }
        .alert-error { background: var(--error-light); color: var(--error-deep); border-left: 6px solid var(--error-primary); }
        .alert i { font-size: 1.4rem; }
        .form-card { background: white; border-radius: 40px; padding: 3rem; box-shadow: var(--shadow-xl); border: 1px solid var(--n3); position: relative; overflow: hidden; }
        .form-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: linear-gradient(90deg, var(--maroon-300), var(--info-primary), var(--success-primary)); }
        .form-icon { width: 80px; height: 80px; background: linear-gradient(135deg, var(--maroon-50), #F2F2F2); border-radius: 26px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.4rem; color: var(--maroon-300); box-shadow: var(--shadow-md); }
        .form-card h2 { text-align: center; margin-bottom: 2rem; color: var(--n8); font-size: 1.8rem; font-weight: 700; }
        .section-divider { margin: 2rem 0 1.5rem; padding-top: 2rem; border-top: 2px dashed var(--n3); }
        .section-divider h3 { font-size: 1.1rem; color: var(--maroon-300); margin-bottom: 1.5rem; font-weight: 700; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.6rem; color: var(--n7); font-size: 0.95rem; }
        .form-group label i { color: var(--maroon-300); margin-right: 0.4rem; }
        .form-control { width: 100%; padding: 1rem 1.2rem; border: 2px solid var(--n3); border-radius: 16px; font-size: 1rem; transition: all 0.3s; background: var(--n1); color: var(--n8); font-family: inherit; }
        .form-control:focus { outline: none; border-color: var(--maroon-300); background: white; box-shadow: 0 8px 16px -8px var(--maroon-200); }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23842646' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 1.2rem center; background-size: 1.2rem; }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .btn { padding: 1rem; border: none; border-radius: 30px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.6rem; width: 100%; }
        .btn-primary { background: linear-gradient(135deg, var(--maroon-300), var(--maroon-400)); color: white; box-shadow: 0 8px 16px -4px var(--maroon-200); }
        .btn-primary:hover { background: linear-gradient(135deg, var(--maroon-400), var(--maroon-500)); transform: translateY(-2px); box-shadow: 0 16px 24px -8px var(--maroon-200); }
        footer { background: var(--n7); color: var(--n2); padding: 2rem; text-align: center; border-top: 5px solid var(--maroon-300); margin-top: auto; }
        .footer-content { max-width: 1400px; margin: 0 auto; }
        .footer-copy { color: var(--n5); font-size: 0.85rem; margin-top: 0.5rem; }
        @media (max-width: 768px) { nav { flex-direction: column; gap: 1rem; } .nav-links { flex-direction: column; text-align: center; gap: 1rem; } .form-card { padding: 1.5rem; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <nav>
     <a href="receptionist-dashboard.php" class="logo">Valora</a>
        <ul class="nav-links">
            <li><a href="receptionist-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</header>
<div class="container">
    <a href="receptionist-dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="form-card">
        <div class="form-icon"><i class="fas fa-user-plus"></i></div>
        <h2>Register New Patient</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="Enter patient's full name">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="patient@example.com">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+973 XXXX XXXX">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
                </div>
            </div>

            <div class="section-divider">
                <h3><i class="fas fa-notes-medical"></i> Medical Information</h3>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-birthday-cake"></i> Date of Birth</label>
                    <input type="date" name="dob" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-venus-mars"></i> Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">Select gender...</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tint"></i> Blood Group</label>
                    <select name="blood_group" class="form-control">
                        <option value="">Select blood group...</option>
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
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Street address, city">
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-allergies"></i> Allergies <span style="color:var(--n5); font-weight:400;">(optional)</span></label>
                <textarea name="allergies" class="form-control" placeholder="List any known allergies..."></textarea>
            </div>

            <div class="section-divider">
                <h3><i class="fas fa-phone-square-alt"></i> Emergency Contact</h3>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user-friends"></i> Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" placeholder="Emergency contact person">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" class="form-control" placeholder="+973 XXXX XXXX">
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register Patient</button>
        </form>
    </div>
</div>
<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem; font-weight:600;">Valora Medical Center</p>
        <p style="color:var(--n4); font-size:0.9rem;">University Project · All information is fictional</p>
        <p class="footer-copy">&copy; 2026 Valora HMS. All rights reserved.</p>
    </div>
</footer>
</body>
</html>