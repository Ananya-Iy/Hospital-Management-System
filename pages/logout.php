<?php
session_start();

// Store user info before destroying session for personalized message
$user_name = $_SESSION['name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'guest';

// Destroy the session
session_unset();
session_destroy();

// Start a new session to show the logout message (won't have user data)
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out · Valora Medical Center</title>
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
            --info-light: #C9D3E6; --info-primary: #0E3E9E;
            --bg-body: #F8F9FC;
            --shadow-xl: 0 24px 32px rgba(100,23,50,0.08);
        }

        html, body { height: 100%; }

        body {
            background: linear-gradient(135deg, var(--bg-body) 0%, var(--maroon-50) 100%);
            color: var(--n8);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            line-height: 1.5;
            padding: 2rem;
        }

        .logout-container {
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .logout-card {
            background: white;
            border-radius: 40px;
            padding: 4rem 3rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--n3);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--maroon-300), var(--info-primary), var(--success-primary));
        }

        .logout-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--success-light), var(--success-primary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3.5rem;
            color: white;
            box-shadow: 0 16px 32px rgba(57, 195, 122, 0.3);
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 16px 32px rgba(57, 195, 122, 0.3);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 20px 40px rgba(57, 195, 122, 0.4);
            }
        }

        .logout-card h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--n8);
            margin-bottom: 1rem;
        }

        .logout-card p {
            color: var(--n6);
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .logout-card .user-name {
            color: var(--maroon-300);
            font-weight: 600;
        }

        .redirect-info {
            background: var(--info-light);
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--info-primary);
        }

        .redirect-info i {
            color: var(--info-primary);
            margin-right: 0.5rem;
        }

        .redirect-info span {
            font-weight: 600;
            color: var(--info-primary);
        }

        .countdown {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--info-primary);
            color: white;
            border-radius: 50%;
            font-size: 1.3rem;
            font-weight: 700;
            margin-left: 0.5rem;
            animation: countPulse 1s ease-in-out infinite;
        }

        @keyframes countPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2.5rem;
            border-radius: 100px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--maroon-300), var(--maroon-400));
            color: white;
            box-shadow: 0 8px 16px -4px var(--maroon-200);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--maroon-400), var(--maroon-500));
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -4px var(--maroon-200);
        }

        .btn-secondary {
            background: var(--n2);
            color: var(--n7);
        }

        .btn-secondary:hover {
            background: var(--n3);
            transform: translateY(-2px);
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--maroon-300);
            letter-spacing: -0.5px;
            margin-bottom: 3rem;
            position: relative;
            display: inline-block;
        }

        .logo::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 4px;
            background: var(--maroon-300);
            border-radius: 4px;
        }

        .features {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px dashed var(--n3);
        }

        .features h3 {
            font-size: 1.1rem;
            color: var(--n7);
            margin-bottom: 1.5rem;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .feature-item {
            padding: 1rem;
            background: var(--n1);
            border-radius: 16px;
            transition: all 0.3s;
        }

        .feature-item:hover {
            background: var(--maroon-50);
            transform: translateY(-2px);
        }

        .feature-item i {
            font-size: 1.8rem;
            color: var(--maroon-300);
            display: block;
            margin-bottom: 0.5rem;
        }

        .feature-item span {
            font-size: 0.9rem;
            color: var(--n7);
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .logout-card {
                padding: 3rem 2rem;
            }

            .logout-card h1 {
                font-size: 1.8rem;
            }

            .logout-icon {
                width: 100px;
                height: 100px;
                font-size: 3rem;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .feature-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>

<div class="logout-container">
    <div class="logo">Valora</div>
    
    <div class="logout-card">
        <div class="logout-icon">
            <i class="fas fa-check-circle"></i>
        </div>

        <h1>Successfully Logged Out</h1>
        <p>
            Thank you, <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>! 
            <br>You have been safely logged out of your account.
        </p>

        <div class="redirect-info">
            <i class="fas fa-info-circle"></i>
            Redirecting to home page in <span class="countdown" id="countdown">5</span> seconds...
        </div>

        <div class="button-group">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Go to Home
            </a>
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Login Again
            </a>
        </div>

        <div class="features">
            <h3>Valora Medical Center Services</h3>
            <div class="feature-grid">
                <div class="feature-item">
                    <i class="fas fa-user-md"></i>
                    <span>Expert Doctors</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Easy Booking</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-file-medical"></i>
                    <span>Digital Records</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-hospital"></i>
                    <span>24/7 Care</span>
                </div>
            </div>
        </div>
    </div>

    <p style="margin-top: 2rem; color: var(--n6); font-size: 0.9rem;">
        <i class="fas fa-shield-alt"></i> Your session has been securely terminated
    </p>
</div>

<script>
    // Countdown timer
    let seconds = 5;
    const countdownElement = document.getElementById('countdown');
    
    const timer = setInterval(() => {
        seconds--;
        countdownElement.textContent = seconds;
        
        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = 'index.php';
        }
    }, 1000);
</script>

</body>
</html>