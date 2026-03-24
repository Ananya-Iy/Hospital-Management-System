<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('patient');

$user_id      = $_SESSION['user_id'];
$patient_name = $_SESSION['name'];

// Get patient record
$p_result   = $conn->query("SELECT * FROM patients WHERE user_id = $user_id");
$patient    = $p_result->fetch_assoc();
$patient_id = $patient['id'] ?? null;

// Stats
$upcoming_count  = 0;
$total_count     = 0;
$records_count   = 0;
$unpaid_count    = 0;
$recent_appointments = [];

if ($patient_id) {
    $upcoming_count = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE patient_id = $patient_id AND appointment_date >= CURDATE() AND status NOT IN ('Cancelled')")->fetch_assoc()['c'];
    $total_count    = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE patient_id = $patient_id")->fetch_assoc()['c'];
    $records_count  = $conn->query("SELECT COUNT(*) as c FROM medical_records WHERE patient_id = $patient_id")->fetch_assoc()['c'];
    $unpaid_count   = $conn->query("SELECT COUNT(*) as c FROM invoices WHERE patient_id = $patient_id AND status = 'Unpaid'")->fetch_assoc()['c'];

    $apt_result = $conn->query(
        "SELECT a.*, u.name AS doctor_name, s.name AS specialization
         FROM appointments a
         JOIN doctors d ON a.doctor_id = d.id
         JOIN users u ON d.user_id = u.id
         JOIN specializations s ON d.specialization_id = s.id
         WHERE a.patient_id = $patient_id
         ORDER BY a.appointment_date DESC, a.appointment_time DESC
         LIMIT 5"
    );
    while ($row = $apt_result->fetch_assoc()) {
        $recent_appointments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal · Valora Medical Center</title>
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
        .user-profile .fa-user-circle { color: var(--maroon-300); font-size: 1.2rem; }
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

        .book-now-btn {
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

        .book-now-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }

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

        /* ===== TABS ===== */
        .main-tabs {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 0 auto 2rem;
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
        .badge-paid      { background: var(--success-light);  color: var(--success-deep); }

        /* ===== EMPTY STATE ===== */
        .empty-state { text-align: center; padding: 3rem 2rem; }
        .empty-state i { font-size: 3.5rem; color: var(--n4); display: block; margin-bottom: 1rem; }
        .empty-state p { color: var(--n5); margin-bottom: 1.5rem; }

        .empty-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 2rem;
            background: var(--maroon-300);
            color: white;
            border-radius: 100px;
            font-weight: 600;
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            nav { flex-direction: column; gap: 0.8rem; padding: 1rem; }
            .welcome-text h1 { font-size: 1.6rem; }
            .welcome-section { flex-direction: column; }
            .dashboard-container { padding: 1rem; }
            .main-tabs { border-radius: 24px; padding: 0.6rem; }
            .tab { padding: 0.6rem 1rem; font-size: 0.85rem; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <nav>
      <a href="patient-dashboard.php" class="logo">Valora</a> 
        <div class="nav-right">
            <a href="profile.php" class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($patient_name); ?></span>
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
                <h1>Welcome back, <?php echo htmlspecialchars($patient_name); ?>! 👋</h1>
                <p><i class="fas fa-heart"></i>&nbsp; Your health journey continues here</p>
            </div>
            <div class="welcome-action">
                <a href="appointments.php" class="book-now-btn">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon maroon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <p>Upcoming Appointments</p>
                    <h3><?php echo $upcoming_count; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-history"></i></div>
                <div class="stat-info">
                    <p>Total Appointments</p>
                    <h3><?php echo $total_count; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-file-medical"></i></div>
                <div class="stat-info">
                    <p>Medical Records</p>
                    <h3><?php echo $records_count; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="stat-info">
                    <p>Unpaid Invoices</p>
                    <h3><?php echo $unpaid_count; ?></h3>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="section-heading">Quick Actions</div>
        <div class="quick-actions">
            <a href="myappointments.php" class="quick-card"><i class="fas fa-calendar-alt"></i> My Appointments</a>
            <a href="appointments.php" class="quick-card"><i class="fas fa-calendar-plus"></i><span>Book Appointment</span></a>
            <a href="finddoc.php"      class="quick-card"><i class="fas fa-user-md"></i><span>Find Doctors</span></a>
            <a href="reports.php"      class="quick-card"><i class="fas fa-file-medical"></i><span>Medical Records</span></a>
            <a href="billings.php"      class="quick-card"><i class="fas fa-file-invoice"></i><span>View Invoices</span></a>
            <a href="profile.php"      class="quick-card"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
        </div>

        <!-- RECENT APPOINTMENTS -->
        <div class="section-heading">
            Recent Appointments
            <a href="myappointments.php">View all →</a>
        </div>

        <div class="table-container">
            <?php if (count($recent_appointments) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_appointments as $apt): ?>
                    <tr>
                        <td><strong>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($apt['specialization']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($apt['status']); ?>">
                                <?php echo $apt['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p>No appointments yet. Book your first one!</p>
                <a href="home.php" class="empty-btn">
                    <i class="fas fa-calendar-plus"></i> Book Now
                </a>
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