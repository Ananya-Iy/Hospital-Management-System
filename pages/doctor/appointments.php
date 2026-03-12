<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];

// Get doctor record
$d_result  = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id");
$doctor    = $d_result->fetch_assoc();
$doctor_id = $doctor['id'] ?? null;

$success = '';
$error   = '';

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctor_id) {
    $action = $_POST['action'] ?? '';
    $apt_id = intval($_POST['appointment_id'] ?? 0);

    if ($action === 'confirm' && $apt_id) {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Confirmed' WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $apt_id, $doctor_id);
        if ($stmt->execute()) {
            $success = 'Appointment confirmed successfully!';
        } else {
            $error = 'Failed to confirm appointment.';
        }
        $stmt->close();
    }

    if ($action === 'cancel' && $apt_id) {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $apt_id, $doctor_id);
        if ($stmt->execute()) {
            $success = 'Appointment cancelled successfully.';
        } else {
            $error = 'Failed to cancel appointment.';
        }
        $stmt->close();
    }

    if ($action === 'complete' && $apt_id) {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $apt_id, $doctor_id);
        if ($stmt->execute()) {
            $success = 'Appointment marked as completed!';
        } else {
            $error = 'Failed to update appointment.';
        }
        $stmt->close();
    }
}

// Filter
$filter = $_GET['filter'] ?? 'upcoming';
$where_clause = "a.doctor_id = $doctor_id";

switch ($filter) {
    case 'today':
        $where_clause .= " AND a.appointment_date = CURDATE()";
        break;
    case 'upcoming':
        $where_clause .= " AND a.appointment_date >= CURDATE() AND a.status NOT IN ('Cancelled', 'Completed')";
        break;
    case 'past':
        $where_clause .= " AND (a.appointment_date < CURDATE() OR a.status = 'Completed')";
        break;
    case 'pending':
        $where_clause .= " AND a.status = 'Requested'";
        break;
}

// Get appointments
$appointments = [];
if ($doctor_id) {
    $apt_result = $conn->query(
        "SELECT a.*, u.name AS patient_name, p.date_of_birth as dob, p.blood_group, u.phone
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         JOIN users u ON p.user_id = u.id
         WHERE $where_clause
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );
    while ($row = $apt_result->fetch_assoc()) {
        $appointments[] = $row;
    }
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
            max-width: 1200px;
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

        /* ===== FILTER TABS ===== */
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.7rem 1.5rem;
            border-radius: 100px;
            font-weight: 600;
            color: var(--n7);
            background: white;
            border: 2px solid var(--n3);
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .filter-tab:hover { border-color: var(--maroon-200); color: var(--maroon-300); }
        .filter-tab.active { background: var(--maroon-300); color: white; border-color: var(--maroon-300); }

        /* ===== APPOINTMENT CARD ===== */
        .appointment-card {
            background: white;
            border-radius: 24px;
            padding: 1.8rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--n3);
            box-shadow: var(--shadow-md);
            transition: all 0.2s;
        }

        .appointment-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-xl); }

        .apt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .apt-patient {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .patient-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--maroon-50), var(--info-light));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--maroon-300);
            flex-shrink: 0;
        }

        .patient-name { font-weight: 700; font-size: 1.2rem; margin-bottom: 0.2rem; }
        .patient-meta { color: var(--n6); font-size: 0.9rem; }

        .badge {
            padding: 0.4rem 1rem;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-requested { background: var(--info-light);    color: var(--info-primary); }
        .badge-confirmed { background: var(--success-light);  color: var(--success-deep); }
        .badge-completed { background: var(--n2);             color: var(--n6); }
        .badge-cancelled { background: var(--error-light);    color: var(--error-deep); }

        .apt-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1.2rem;
            background: var(--n1);
            border-radius: 16px;
            margin-bottom: 1.2rem;
        }

        .detail-item i { color: var(--maroon-300); margin-right: 0.5rem; }
        .detail-item strong { font-weight: 600; }

        .apt-reason {
            padding: 1rem;
            background: var(--info-light);
            border-radius: 12px;
            margin-bottom: 1.2rem;
            color: var(--n7);
        }

        .apt-reason strong { color: var(--info-primary); display: block; margin-bottom: 0.5rem; }

        .apt-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border-radius: 100px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-confirm { background: var(--success-light); color: var(--success-deep); }
        .btn-confirm:hover { background: var(--success-primary); color: white; }

        .btn-cancel { background: var(--error-light); color: var(--error-deep); }
        .btn-cancel:hover { background: var(--error-primary); color: white; }

        .btn-complete { background: var(--n2); color: var(--n7); }
        .btn-complete:hover { background: var(--n4); color: white; }

        .btn-view { background: var(--info-light); color: var(--info-primary); }
        .btn-view:hover { background: var(--info-primary); color: white; }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-md);
        }

        .empty-state i { font-size: 4rem; color: var(--n4); display: block; margin-bottom: 1.5rem; }
        .empty-state h3 { font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--n7); }
        .empty-state p { color: var(--n5); }

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
            .apt-header { flex-direction: column; }
            .apt-details { grid-template-columns: 1fr; }
            .apt-actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <nav>
        <a href="../../index.php" class="logo">Valora</a>
        <ul class="nav-links">
            <li><a href="doctor-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</header>

<!-- ===== TABS ===== -->
<div class="main-tabs">
    <a href="appointments.php" class="tab active">
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
    <a href="profile.php" class="tab">
        <i class="fas fa-user-md"></i> Profile
    </a>
</div>

<div class="container">
    <div class="page-header">
        <h1>My Appointments</h1>
        <p>Manage your patient appointments and schedule</p>
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

    <!-- FILTER TABS -->
    <div class="filter-tabs">
        <a href="?filter=today" class="filter-tab <?php echo $filter === 'today' ? 'active' : ''; ?>">
            Today
        </a>
        <a href="?filter=upcoming" class="filter-tab <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
            Upcoming
        </a>
        <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
            Pending Requests
        </a>
        <a href="?filter=past" class="filter-tab <?php echo $filter === 'past' ? 'active' : ''; ?>">
            Past
        </a>
    </div>

    <!-- APPOINTMENTS LIST -->
    <?php if (count($appointments) > 0): ?>
        <?php foreach ($appointments as $apt): 
            $age = $apt['dob'] ? date_diff(date_create($apt['dob']), date_create('today'))->y : 'N/A';
        ?>
        <div class="appointment-card">
            <div class="apt-header">
                <div class="apt-patient">
                    <div class="patient-avatar"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="patient-name"><?php echo htmlspecialchars($apt['patient_name']); ?></div>
                        <div class="patient-meta">
                            Age: <?php echo $age; ?> years • 
                            Blood: <?php echo htmlspecialchars($apt['blood_group'] ?? 'N/A'); ?> •
                            Phone: <?php echo htmlspecialchars($apt['phone'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
                <span class="badge badge-<?php echo strtolower($apt['status']); ?>">
                    <?php echo $apt['status']; ?>
                </span>
            </div>

            <div class="apt-details">
                <div class="detail-item">
                    <i class="fas fa-calendar"></i>
                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <strong>Time:</strong> <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?>
                </div>
                <div class="detail-item">
                    <i class="fas fa-info-circle"></i>
                    <strong>ID:</strong> #APT-<?php echo str_pad($apt['id'], 5, '0', STR_PAD_LEFT); ?>
                </div>
            </div>

            <?php if (!empty($apt['reason'])): ?>
            <div class="apt-reason">
                <strong><i class="fas fa-notes-medical"></i> Reason for Visit:</strong>
                <?php echo htmlspecialchars($apt['reason']); ?>
            </div>
            <?php endif; ?>

            <div class="apt-actions">
                <a href="view-patient.php?id=<?php echo $apt['patient_id']; ?>" class="btn btn-view">
                    <i class="fas fa-eye"></i> View Patient
                </a>

                <?php if ($apt['status'] === 'Requested'): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn btn-confirm">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($apt['status'] === 'Confirmed' && $apt['appointment_date'] <= date('Y-m-d')): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-complete">
                        <i class="fas fa-check-double"></i> Mark Complete
                    </button>
                </form>
                <?php endif; ?>

                <?php if (!in_array($apt['status'], ['Cancelled', 'Completed'])): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-cancel" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No appointments found</h3>
            <p>You don't have any appointments matching this filter.</p>
        </div>
    <?php endif; ?>
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