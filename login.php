<?php
require_once 'includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $query = "SELECT * FROM users WHERE email = '$email'";
        $result = $conn->query($query);

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                switch ($user['role']) {
                    case 'patient':
                        header('Location: patient/dashboard.php');
                        break;
                    case 'doctor':
                        header('Location: doctor/dashboard.php');
                        break;
                    case 'receptionist':
                        header('Location: receptionist/dashboard.php');
                        break;
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    default:
                        header('Location: index.php');
                }
                exit();
            } else {
                $error = 'Invalid email or password';
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
    <title>Login - Hospital Management System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="index.php" class="logo">🏥 Hospital Management</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Register</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <div class="card" style="max-width: 500px; margin: 3rem auto;">
            <h2 class="card-header">Login to Your Account</h2>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>

            <p style="text-align: center; margin-top: 1.5rem;">
                Don't have an account? <a href="register.php">Register here</a>
            </p>

            <div style="margin-top: 2rem; padding: 1rem; background-color: #f8f9fa; border-radius: 4px;">
                <p style="font-weight: bold; margin-bottom: 0.5rem;">Demo Accounts:</p>
                <p style="font-size: 0.875rem; margin-bottom: 0.25rem;">
                    <strong>Doctor:</strong> john.smith@hospital.com / doctor123
                </p>
                <p style="font-size: 0.875rem; margin-bottom: 0.25rem;">
                    <strong>Receptionist:</strong> receptionist@hospital.com / receptionist123
                </p>
                <p style="font-size: 0.875rem;">
                    <strong>Admin:</strong> admin@hospital.com / admin123
                </p>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
