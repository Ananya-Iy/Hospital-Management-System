<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('patient');

$user_id = $_SESSION['user_id'];

// Get patient record
$patient    = $conn->query("SELECT * FROM patients WHERE user_id = $user_id")->fetch_assoc();
$patient_id = $patient['id'];

// Handle cancel
$success = '';
$error   = '';

if (isset($_GET['cancel'])) {
    $cancel_id = intval($_GET['cancel']);
    $check = $conn->query(
        "SELECT id, status FROM appointments 
         WHERE id = $cancel_id AND patient_id = $patient_id LIMIT 1"
    )->fetch_assoc();

    if ($check && in_array($check['status'], ['Requested', 'Confirmed'])) {
        $conn->query("UPDATE appointments SET status = 'Cancelled' WHERE id = $cancel_id");
        $current_filter = $_GET['filter'] ?? 'all';
        header("Location: appointments.php?filter=$current_filter&cancelled=1");
        exit;
    } else {
        $error = 'This appointment cannot be cancelled.';
    }
}

// Show success message after redirect
if (isset($_GET['cancelled'])) {
    $success = 'Appointment cancelled successfully.';
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'Requested', 'Confirmed', 'Completed', 'Cancelled'];
if (!in_array($filter, $allowed_filters)) $filter = 'all';

$where = "WHERE a.patient_id = $patient_id";
if ($filter !== 'all') {
    $where .= " AND a.status = '$filter'";
}

// Get appointments
$appointments = [];
$result = $conn->query(
    "SELECT a.*, u.name AS doctor_name, s.name AS specialization, d.consultation_fee
     FROM appointments a
     JOIN doctors d ON a.doctor_id = d.id
     JOIN users u ON d.user_id = u.id
     JOIN specializations s ON d.specialization_id = s.id
     $where
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
);

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments · Valora Medical Center</title>
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
            --warning-light: #E5D8C8; --warning-primary: #F48B05; --warning-deep: #B36805;
            --error-light: #E2D0CD; --error-primary: #F04233; --error-deep: #B03125;
            --info-light: #C9D3E6; --info-primary: #0E3E9E; --info-deep: #082E73;
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
            box-shadow: var(--shadow-sm);
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

        /* ===== PAGE WRAPPER ===== */
        .page-wrapper { flex: 1; display: flex; flex-direction: column; }

        .container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
        }

        /* ===== TABS ===== */
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

        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

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

        .book-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.9rem 2rem;
            background: var(--maroon-300);
            color: white;
            border-radius: 100px;
            font-weight: 700;
            transition: all 0.2s;
            box-shadow: 0 8px 16px -4px var(--maroon-200);
        }

        .book-btn:hover { background: var(--maroon-400); transform: translateY(-2px); }

        /* ===== ALERTS ===== */
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success { background: var(--success-light); color: var(--success-deep); border-left: 6px solid var(--success-primary); }
        .alert-error   { background: var(--error-light);   color: var(--error-deep);   border-left: 6px solid var(--error-primary); }

        /* ===== FILTER TABS ===== */
        .filter-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.88rem;
            border: 1px solid var(--n3);
            background: white;
            color: var(--n7);
            transition: all 0.2s;
        }

        .filter-btn:hover { border-color: var(--maroon-200); color: var(--maroon-300); }
        .filter-btn.active { background: var(--maroon-300); color: white; border-color: var(--maroon-300); }

        /* ===== APPOINTMENT CARDS ===== */
        .appointments-grid {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .apt-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .apt-card::before {
            content: '';
            position: absolute;
            left: 0; top: 0;
            width: 5px; height: 100%;
            border-radius: 24px 0 0 24px;
        }

        .apt-card.status-requested::before  { background: var(--warning-primary); }
        .apt-card.status-confirmed::before  { background: var(--info-primary); }
        .apt-card.status-completed::before  { background: var(--success-primary); }
        .apt-card.status-cancelled::before  { background: var(--error-primary); }
        .apt-card.status-paid::before       { background: var(--success-primary); }

        .apt-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-xl); }

        .apt-left { display: flex; align-items: center; gap: 1.2rem; }

        .apt-avatar {
            width: 56px; height: 56px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--maroon-50), var(--info-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon-300);
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .apt-doctor { font-weight: 700; font-size: 1rem; margin-bottom: 0.2rem; }
        .apt-spec   { color: var(--info-primary); font-size: 0.9rem; font-weight: 500; }

        .apt-middle {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .apt-meta { text-align: center; }
        .apt-meta-label { font-size: 0.8rem; color: var(--n5); margin-bottom: 0.2rem; }
        .apt-meta-value { font-weight: 600; color: var(--n8); font-size: 0.95rem; }

        .apt-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.8rem;
        }

        /* ===== BADGES ===== */
        .badge {
            padding: 0.35rem 1rem;
            border-radius: 100px;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-requested { background: var(--warning-light); color: var(--warning-deep); }
        .badge-confirmed { background: var(--info-light);    color: var(--info-deep); }
        .badge-completed { background: var(--success-light); color: var(--success-deep); }
        .badge-cancelled { background: var(--error-light);   color: var(--error-deep); }
        .badge-paid      { background: var(--success-light); color: var(--success-deep); }

        /* ===== ACTION BUTTONS ===== */
        .action-row { display: flex; gap: 0.5rem; }

        .btn-view {
            padding: 0.5rem 1.2rem;
            background: var(--maroon-300);
            color: white;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-view:hover { background: var(--maroon-400); transform: translateY(-1px); }

        .btn-cancel {
            padding: 0.5rem 1.2rem;
            background: var(--error-light);
            color: var(--error-deep);
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            border: 1px solid var(--error-primary);
        }

        .btn-cancel:hover { background: var(--error-primary); color: white; }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border-radius: 28px;
            border: 2px dashed var(--n4);
        }

        .empty-state i { font-size: 4rem; color: var(--n4); display: block; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.4rem; color: var(--n6); margin-bottom: 0.5rem; }
        .empty-state p  { color: var(--n5); margin-bottom: 2rem; }

        .empty-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.9rem 2rem;
            background: var(--maroon-300);
            color: white;
            border-radius: 100px;
            font-weight: 700;
            transition: all 0.2s;
        }

        .empty-btn:hover { background: var(--maroon-400); transform: translateY(-2px); }

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

        @media (max-width: 768px) {
            nav { flex-direction: column; gap: 0.8rem; padding: 1rem; }
            .apt-card { flex-direction: column; align-items: flex-start; }
            .apt-right { align-items: flex-start; }
            .container { padding: 1rem; }
            .main-tabs { border-radius: 24px; padding: 0.6rem; }
            .tab { padding: 0.6rem 1rem; font-size: 0.85rem; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <nav>
        <a href="../../index.php" class="logo">Valora</a>
        <div class="nav-right">
            <a href="profile.php" class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
            </a>
            <a href="../../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>
</header>

<div class="page-wrapper">

    <!-- ===== NAVIGATION TABS ===== -->
    <div class="main-tabs">
        <a href="appointments.php"         class="tab"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
        <a href="myappointments.php" class="tab active"><i class="fas fa-calendar-alt"></i> My Appointments</a>
        <a href="finddoc.php"      class="tab"><i class="fas fa-user-md"></i> Find Doctors</a>
        <a href="reports.php"      class="tab"><i class="fas fa-file-medical"></i> Medical Records</a>
        <a href="billings.php"      class="tab"><i class="fas fa-file-invoice"></i> Billing</a>
        <a href="profile.php"      class="tab"><i class="fas fa-user"></i> Profile</a>
    </div>

    <div class="container">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1>My Appointments</h1>
            <a href="appointments.php" class="book-btn">
                <i class="fas fa-calendar-plus"></i> Book New
            </a>
        </div>

        <!-- ALERTS -->
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <a href="?filter=all"       class="filter-btn <?php echo $filter === 'all'       ? 'active' : ''; ?>">All</a>
            <a href="?filter=Requested" class="filter-btn <?php echo $filter === 'Requested' ? 'active' : ''; ?>">🕐 Requested</a>
            <a href="?filter=Confirmed" class="filter-btn <?php echo $filter === 'Confirmed' ? 'active' : ''; ?>">✅ Confirmed</a>
            <a href="?filter=Completed" class="filter-btn <?php echo $filter === 'Completed' ? 'active' : ''; ?>">🏁 Completed</a>
            <a href="?filter=Cancelled" class="filter-btn <?php echo $filter === 'Cancelled' ? 'active' : ''; ?>">❌ Cancelled</a>
        </div>

        <!-- APPOINTMENTS -->
        <?php if (count($appointments) > 0): ?>
        <div class="appointments-grid">
            <?php foreach ($appointments as $apt): ?>
            <div class="apt-card status-<?php echo strtolower($apt['status']); ?>">

                <!-- LEFT: Doctor info -->
                <div class="apt-left">
                    <div class="apt-avatar"><i class="fas fa-user-md"></i></div>
                    <div>
                        <div class="apt-doctor">Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></div>
                        <div class="apt-spec"><?php echo htmlspecialchars($apt['specialization']); ?></div>
                    </div>
                </div>

                <!-- MIDDLE: Date, time, fee -->
                <div class="apt-middle">
                    <div class="apt-meta">
                        <div class="apt-meta-label"><i class="fas fa-calendar"></i> Date</div>
                        <div class="apt-meta-value"><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></div>
                    </div>
                    <div class="apt-meta">
                        <div class="apt-meta-label"><i class="fas fa-clock"></i> Time</div>
                        <div class="apt-meta-value"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></div>
                    </div>
                    <div class="apt-meta">
                        <div class="apt-meta-label"><i class="fas fa-money-bill"></i> Fee</div>
                        <div class="apt-meta-value">BD <?php echo number_format($apt['consultation_fee'], 2); ?></div>
                    </div>
                </div>

                <!-- RIGHT: Status + actions -->
                <div class="apt-right">
                    <span class="badge badge-<?php echo strtolower($apt['status']); ?>">
                        <?php echo $apt['status']; ?>
                    </span>
                    <div class="action-row">
                        <a href="appointment-details.php?id=<?php echo $apt['id']; ?>" class="btn-view">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if (in_array($apt['status'], ['Requested', 'Confirmed'])): ?>
                        <a href="?cancel=<?php echo $apt['id']; ?>&filter=<?php echo $filter; ?>"
                           class="btn-cancel"
                           onclick="return confirm('Cancel this appointment?')">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No appointments found</h3>
            <p><?php echo $filter !== 'all' ? "No $filter appointments." : "You haven't booked any appointments yet."; ?></p>
            <a href="appointments.php" class="empty-btn">
                <i class="fas fa-calendar-plus"></i> Book Your First Appointment
            </a>
        </div>
        <?php endif; ?>

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