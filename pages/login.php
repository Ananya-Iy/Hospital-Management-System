<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';

$error = '';
$selected_role = $_GET['role'] ?? 'patient';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role']     ?? '');

    if (empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && $user['password'] === $password) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            switch ($user['role']) {
                case 'patient':      header('Location: patient/patient-dashboard.php'); exit;
                case 'doctor':       header('Location: doctor/dashboard-doctor.php');   exit;
                case 'receptionist': header('Location: receptionist/receptionist-dashboard.php'); exit;
                case 'admin':        header('Location: admin/dashboard.php'); exit;
                default:             header('Location: ../index.php'); exit;
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valora · Secure Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }

        :root {
            --n1: #F2F2F2; --n2: #E6E6E6; --n3: #DADADA; --n4: #C6C6C6;
            --n5: #9E9E9E; --n6: #6E6E6E; --n7: #3F3F3F; --n8: #1C1C1C;
            --maroon-50: #D8C9CE; --maroon-100: #C5A8B3; --maroon-200: #A56C7E;
            --maroon-300: #842646; --maroon-400: #7A2141; --maroon-500: #641732;
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

        .alert {
            background: var(--error-light);
            color: var(--error-deep);
            padding: 1rem 1.5rem;
            border-radius: 60px;
            margin: 0 auto 2rem;
            text-align: center;
            font-weight: 500;
            border: 1px solid var(--error-primary);
            max-width: 500px;
        }

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

        .login-card {
            background: white;
            border-radius: 30px;
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--n3);
            max-width: 500px;
            margin: 0 auto;
        }

        .login-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--maroon-100);
        }

        .login-header-icon {
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

        .login-header h2 { font-size: 2rem; color: var(--n8); }
        .login-header p  { color: var(--n6); font-size: 0.95rem; }

        .form-group { margin-bottom: 1.5rem; }

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
        .input-wrapper i { color: var(--n5); width: 24px; font-size: 1rem; }

        .input-wrapper input {
            width: 100%;
            padding: 0.9rem 0.5rem;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            outline: none;
        }

        .login-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
            margin: 1.5rem 0 1rem;
        }

        .login-btn:hover {
            background: var(--maroon-400);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -8px var(--maroon-200);
        }

        .register-link { text-align: center; color: var(--n6); }
        .register-link a { color: var(--maroon-300); font-weight: 600; }

        .demo-box {
            background: var(--n1);
            border: 1px dashed var(--n4);
            border-radius: 12px;
            padding: 1rem 1.2rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--n6);
        }

        .demo-box strong { color: var(--n8); display: block; margin-bottom: 0.4rem; }
        .demo-box code {
            background: white;
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            font-size: 0.82rem;
            color: var(--maroon-300);
        }

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

        @media (max-width: 650px) {
            nav { flex-direction: column; gap: 1rem; }
            .nav-links { flex-direction: column; gap: 1rem; text-align: center; }
            .login-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>

<header>
    <nav>
        <a href="index.php" class="logo">Valora</a>
        <ul class="nav-links">
            <li><a href="../index.php">Home</a></li>
            <li><a href="login.php" class="active">Login</a></li>
            <li><a href="signup.php">Register</a></li>
        </ul>
    </nav>
</header>

<div class="container">
    <div class="page-head">
        <h1>Welcome back</h1>
        <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
        <div class="alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
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

    <div class="login-card">

        <!-- PATIENT -->
        <?php if ($selected_role == 'patient'): ?>
        <div class="login-header">
            <div class="login-header-icon"><i class="fas fa-user-injured"></i></div>
            <div><h2>Patient Login</h2><p>Access your health records</p></div>
        </div>
        <form method="POST" action="?role=patient">
            <input type="hidden" name="role" value="patient">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="patient@valora.com" required>
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> Sign in as Patient
            </button>
            <p class="register-link">New patient? <a href="signup.php?role=patient">Create account</a></p>
        </form>
       
        <?php endif; ?>

        <!-- DOCTOR -->
        <?php if ($selected_role == 'doctor'): ?>
        <div class="login-header">
            <div class="login-header-icon"><i class="fas fa-user-md"></i></div>
            <div><h2>Doctor Login</h2><p>Access medical staff portal</p></div>
        </div>
        <form method="POST" action="?role=doctor">
            <input type="hidden" name="role" value="doctor">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="doctor@valora.com" required>
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> Sign in as Doctor
            </button>
            <p class="register-link">New doctor? <a href="signup.php?role=doctor">Register here</a></p>
        </form>
       
        <?php endif; ?>

        <!-- RECEPTIONIST -->
        <?php if ($selected_role == 'receptionist'): ?>
        <div class="login-header">
            <div class="login-header-icon"><i class="fas fa-user-tie"></i></div>
            <div><h2>Receptionist Login</h2><p>Access front desk portal</p></div>
        </div>
        <form method="POST" action="?role=receptionist">
            <input type="hidden" name="role" value="receptionist">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="reception@valora.com" required>
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> Sign in as Receptionist
            </button>
            <p class="register-link">New staff? <a href="signup.php?role=receptionist">Register here</a></p>
        </form>
        
        <?php endif; ?>

    </div>
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