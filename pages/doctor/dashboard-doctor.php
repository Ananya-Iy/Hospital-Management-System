<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id      = $_SESSION['user_id'];
$doctor_name  = $_SESSION['name'];

// Get doctor record
$d_result   = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id");
$doctor     = $d_result->fetch_assoc();
$doctor_id  = $doctor['id'] ?? null;

// Stats
$today_count      = 0;
$upcoming_count   = 0;
$total_patients   = 0;
$pending_count    = 0;
$todays_appointments = [];

if ($doctor_id) {
    $today_count = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND appointment_date = CURDATE() AND status NOT IN ('Cancelled')")->fetch_assoc()['c'];
    $upcoming_count = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND appointment_date > CURDATE() AND status NOT IN ('Cancelled')")->fetch_assoc()['c'];
    $total_patients = $conn->query("SELECT COUNT(DISTINCT patient_id) as c FROM appointments WHERE doctor_id = $doctor_id")->fetch_assoc()['c'];
    $pending_count = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND status = 'Requested'")->fetch_assoc()['c'];

    $apt_result = $conn->query(
        "SELECT a.*, u.name AS patient_name, p.date_of_birth as dob, p.blood_group
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         JOIN users u ON p.user_id = u.id
         WHERE a.doctor_id = $doctor_id AND a.appointment_date = CURDATE()
         ORDER BY a.appointment_time ASC"
    );
    while ($row = $apt_result->fetch_assoc()) {
        $todays_appointments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Portal · Valora Medical Center</title>
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
            --warning-light: #E5D8C8; --warning-deep: #B36805;
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
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
        .user-profile .fa-user-md { color: var(--maroon-300); font-size: 1.2rem; }
        .user-profile span { font-weight: 600; }
        .user-profile .fa-chevron-down { color: var(--n5); font-size: 0.8rem; }

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

        /* ===== WRAPPER ===== */
        .page-wrapper { flex: 1; display: flex; flex-direction: column; }

        .dashboard-container {
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
        }

        /* ===== WELCOME ===== */
        .welcome-section {
            background: linear-gradient(135deg, var(--maroon-300), var(--maroon-500));
            color: white;
            padding: 2.5rem;
            border-radius: 32px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-text h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.4rem; position: relative; z-index: 1; }
        .welcome-text p  { opacity: 0.85; position: relative; z-index: 1; }
        .welcome-action  { position: relative; z-index: 1; }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.9rem 2rem;
            background: white;
            color: var(--maroon-300);
            border-radius: 100px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
        }

        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-xl); }

        .stat-icon {
            width: 56px; height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .stat-icon.maroon { background: var(--maroon-50);    color: var(--maroon-300); }
        .stat-icon.blue   { background: var(--info-light);   color: var(--info-primary); }
        .stat-icon.green  { background: var(--success-light); color: var(--success-deep); }
        .stat-icon.orange { background: var(--warning-light); color: var(--warning-deep); }

        .stat-info p  { font-size: 0.85rem; color: var(--n5); margin-bottom: 0.2rem; }
        .stat-info h3 { font-size: 1.8rem; font-weight: 700; color: var(--n8); }

        /* ===== SECTION HEADING ===== */
        .section-heading {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--n8);
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-heading a { font-size: 0.9rem; color: var(--maroon-300); font-weight: 600; }

        /* ===== QUICK ACTIONS ===== */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem 1rem;
            text-align: center;
            border: 1px solid var(--n3);
            box-shadow: var(--shadow-sm);
            transition: all 0.2s;
            color: var(--n7);
        }

        .quick-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-xl); border-color: var(--maroon-100); color: var(--maroon-300); }
        .quick-card i    { font-size: 2rem; color: var(--maroon-300); display: block; margin-bottom: 0.8rem; }
        .quick-card span { font-weight: 600; font-size: 0.9rem; }

        /* ===== TABLE ===== */
        .table-container {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
            overflow-x: auto;
            margin-bottom: 2rem;
        }

        .data-table { width: 100%; border-collapse: collapse; }

        .data-table th {
            background: linear-gradient(135deg, var(--maroon-300), var(--maroon-400));
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .data-table th:first-child { border-radius: 12px 0 0 0; }
        .data-table th:last-child  { border-radius: 0 12px 0 0; }
        .data-table td { padding: 1rem; border-bottom: 1px solid var(--n2); color: var(--n7); font-size: 0.95rem; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: var(--n1); }

        /* ===== BADGES ===== */
        .badge {
            padding: 0.35rem 0.9rem;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-requested { background: var(--info-light);    color: var(--info-primary); }
        .badge-confirmed { background: var(--success-light);  color: var(--success-deep); }
        .badge-completed { background: var(--n2);             color: var(--n6); }
        .badge-cancelled { background: var(--error-light);    color: var(--error-deep); }

        /* ===== ACTION BUTTONS ===== */
        .action-btns { display: flex; gap: 0.5rem; }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .btn-view { background: var(--info-light); color: var(--info-primary); }
        .btn-view:hover { background: var(--info-primary); color: white; }

        /* ===== EMPTY STATE ===== */
        .empty-state { text-align: center; padding: 3rem 2rem; }
        .empty-state i { font-size: 3.5rem; color: var(--n4); display: block; margin-bottom: 1rem; }
        .empty-state p { color: var(--n5); margin-bottom: 1.5rem; }

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
            nav { flex-direction: column; gap: 0.8rem; padding: 1rem; }
            .welcome-text h1 { font-size: 1.6rem; }
            .welcome-section { flex-direction: column; }
            .dashboard-container { padding: 1rem; }
            .action-btns { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <nav>
       <a href="dashboard-doctor.php" class="logo">Valora</a>
        <div class="nav-right">
            <a href="profile.php" class="user-profile">
                <i class="fas fa-user-md"></i>
                <span>Dr. <?php echo htmlspecialchars($doctor_name); ?></span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>
</header>

<div class="page-wrapper">
    <div class="dashboard-container">

        <!-- WELCOME -->
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Welcome back, Dr. <?php echo htmlspecialchars($doctor_name); ?>! 👨‍⚕️</h1>
                <p><i class="fas fa-stethoscope"></i>&nbsp; Ready to make a difference today</p>
            </div>
            <div class="welcome-action">
                <a href="appointments.php" class="action-btn">
                    <i class="fas fa-calendar-alt"></i> View Appointments
                </a>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon maroon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-info">
                    <p>Today's Appointments</p>
                    <h3><?php echo $today_count; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <p>Upcoming Appointments</p>
                    <h3><?php echo $upcoming_count; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <p>Total Patients</p>
                    <h3><?php echo $total_patients; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <p>Pending Requests</p>
                    <h3><?php echo $pending_count; ?></h3>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="section-heading">Quick Actions</div>
        <div class="quick-actions">
            <a href="appointments.php" class="quick-card"><i class="fas fa-calendar-alt"></i><span>My Appointments</span></a>
            <a href="patients.php" class="quick-card"><i class="fas fa-users"></i><span>Patient Records</span></a>
            <a href="prescriptions.php" class="quick-card"><i class="fas fa-prescription"></i><span>Prescriptions</span></a>
            <a href="availability.php" class="quick-card"><i class="fas fa-calendar-times"></i><span>Manage Schedule</span></a>
            <a href="profile.php" class="quick-card"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
        </div>

        <!-- TODAY'S APPOINTMENTS -->
        <div class="section-heading">
            Today's Schedule
            <a href="appointments.php">View all →</a>
        </div>

        <div class="table-container">
            <?php if (count($todays_appointments) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Blood Group</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todays_appointments as $apt): 
                        $age = $apt['dob'] ? date_diff(date_create($apt['dob']), date_create('today'))->y : 'N/A';
                    ?>
                    <tr>
                        <td><strong><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                        <td><?php echo $age; ?> years</td>
                        <td><?php echo htmlspecialchars($apt['blood_group'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($apt['status']); ?>">
                                <?php echo $apt['status']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="view-patient.php?id=<?php echo $apt['patient_id']; ?>" class="btn-sm btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <p>No appointments scheduled for today. Enjoy your day!</p>
            </div>
            <?php endif; ?>
        </div>

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