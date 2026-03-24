<?php
session_start();

// منع الوصول إذا لم يكن المستخدم مسجل دخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// حفظ بيانات المستخدم قبل حذف السيشن
$user_name = $_SESSION['name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'guest';

// حذف كل بيانات السيشن
$_SESSION = [];

// حذف الكوكيز (أمان إضافي)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير السيشن
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out · Valora Medical Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }

        :root {
            --n1: #F2F2F2; --n6: #6E6E6E; --n7: #3F3F3F; --n8: #1C1C1C;
            --maroon-300: #842646; --maroon-400: #7A2141;
            --success-light: #C6D8D2; --success-primary: #39C37A;
            --info-light: #C9D3E6; --info-primary: #0E3E9E;
        }

        body {
            background: linear-gradient(135deg, #F8F9FC, #D8C9CE);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .card {
            background: white;
            padding: 3rem;
            border-radius: 30px;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .icon {
            font-size: 4rem;
            color: var(--success-primary);
            margin-bottom: 1rem;
        }

        h1 {
            margin-bottom: 1rem;
            color: var(--n8);
        }

        p {
            color: var(--n6);
            margin-bottom: 2rem;
        }

        .countdown {
            font-weight: bold;
            color: var(--info-primary);
        }

        .btn {
            display: inline-block;
            margin: 0.5rem;
            padding: 0.8rem 1.8rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
        }

        .primary {
            background: var(--maroon-300);
            color: white;
        }

        .secondary {
            background: #eee;
            color: var(--n7);
        }
    </style>
</head>
<body>

<div class="card">
    <div class="icon"><i class="fas fa-check-circle"></i></div>

    <h1>Logged Out</h1>

    <p>
        Goodbye, <strong><?php echo htmlspecialchars($user_name); ?></strong>!<br>
        You have been logged out from your 
        <strong><?php echo ucfirst($user_role); ?></strong> account.
    </p>

    <p>
        Redirecting to home in <span class="countdown" id="countdown">5</span> seconds...
    </p>

    <a href="index.php" class="btn primary">Home</a>
    <a href="login.php" class="btn secondary">Login Again</a>
</div>

<script>
let time = 5;
const el = document.getElementById('countdown');

const timer = setInterval(() => {
    time--;
    el.textContent = time;

    if (time <= 0) {
        clearInterval(timer);
        window.location.href = 'index.php';
    }
}, 1000);
</script>

</body>
</html>