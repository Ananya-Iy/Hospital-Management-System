<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('receptionist');

$user_id           = $_SESSION['user_id'];
$receptionist_name = $_SESSION['name'];

// ── Stats ────────────────────────────────────────────────────────────────────
$today_appointments = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = CURDATE()")->fetch_assoc()['c'];
$pending_invoices   = $conn->query("SELECT COUNT(*) as c FROM invoices WHERE status = 'Unpaid'")->fetch_assoc()['c'];
$total_patients     = $conn->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc()['c'];
$today_revenue      = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as t FROM invoices WHERE DATE(updated_at) = CURDATE() AND status = 'Paid'")->fetch_assoc()['t'];
$requested_count    = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'Requested'")->fetch_assoc()['c'];
$confirmed_today    = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = CURDATE() AND status = 'Confirmed'")->fetch_assoc()['c'];

// ── Today's schedule ─────────────────────────────────────────────────────────
$todays_appointments = [];
$apt_result = $conn->query(
    "SELECT a.*, u.name AS patient_name, du.name AS doctor_name, s.name AS specialization
     FROM appointments a
     JOIN patients p  ON a.patient_id = p.id
     JOIN users u     ON p.user_id    = u.id
     JOIN doctors d   ON a.doctor_id  = d.id
     JOIN users du    ON d.user_id    = du.id
     JOIN specializations s ON d.specialization_id = s.id
     WHERE a.appointment_date = CURDATE()
     ORDER BY a.appointment_time ASC
     LIMIT 15"
);
while ($row = $apt_result->fetch_assoc()) $todays_appointments[] = $row;

// ── Pending requests (need confirmation) ─────────────────────────────────────
$pending_requests = [];
$req_result = $conn->query(
    "SELECT a.*, u.name AS patient_name, du.name AS doctor_name, s.name AS specialization
     FROM appointments a
     JOIN patients p  ON a.patient_id = p.id
     JOIN users u     ON p.user_id    = u.id
     JOIN doctors d   ON a.doctor_id  = d.id
     JOIN users du    ON d.user_id    = du.id
     JOIN specializations s ON d.specialization_id = s.id
     WHERE a.status = 'Requested'
     ORDER BY a.appointment_date ASC, a.appointment_time ASC
     LIMIT 8"
);
while ($row = $req_result->fetch_assoc()) $pending_requests[] = $row;

// ── Handle quick confirm / cancel from dashboard ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_apt'])) {
        $id = intval($_POST['apt_id']);
        $conn->query("UPDATE appointments SET status = 'Confirmed' WHERE id = $id");
        header("Location: dashboard-receptionist.php?confirmed=1");
        exit;
    }
    if (isset($_POST['cancel_apt'])) {
        $id = intval($_POST['apt_id']);
        $conn->query("UPDATE appointments SET status = 'Cancelled' WHERE id = $id");
        header("Location: dashboard-receptionist.php?cancelled=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard · Valora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif}
        :root{
            --n1:#F2F2F2;--n2:#E6E6E6;--n3:#DADADA;--n4:#C6C6C6;--n5:#9E9E9E;--n6:#6E6E6E;--n7:#3F3F3F;--n8:#1C1C1C;
            --m50:#D8C9CE;--m100:#C5A8B3;--m200:#A56C7E;--m300:#842646;--m400:#7A2141;--m500:#641732;
            --sl:#C6D8D2;--sp:#39C37A;--sd:#2E955C;
            --wl:#E5D8C8;--wp:#F48B05;--wd:#B36805;
            --el:#E2D0CD;--ep:#F04233;--ed:#B03125;
            --il:#C9D3E6;--ip:#0E3E9E;
            --bg:#F8F9FC;
            --sh-md:0 8px 16px rgba(0,0,0,0.04);
            --sh-lg:0 16px 24px rgba(0,0,0,0.04);
            --sh-xl:0 24px 32px rgba(100,23,50,0.08);
        }
        html,body{height:100%}
        body{background:var(--bg);color:var(--n8);display:flex;flex-direction:column;min-height:100vh;line-height:1.5}
        a{text-decoration:none}

        /* HEADER */
        header{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);box-shadow:var(--sh-md);position:sticky;top:0;z-index:50;border-bottom:1px solid rgba(218,218,218,.3)}
        nav{display:flex;justify-content:space-between;align-items:center;padding:1rem 2rem;max-width:1400px;margin:0 auto}
        .logo{font-size:1.8rem;font-weight:800;color:var(--m300);letter-spacing:-.5px;position:relative}
        .logo::after{content:'';position:absolute;bottom:-4px;left:0;width:40%;height:3px;background:var(--m300);border-radius:4px}
        .nav-right{display:flex;align-items:center;gap:1rem}
        .user-pill{display:flex;align-items:center;gap:.8rem;padding:.5rem 1.2rem;background:white;border-radius:100px;border:1px solid var(--n3);color:var(--n8);font-weight:600}
        .user-pill i{color:var(--m300)}
        .logout-btn{padding:.5rem 1.2rem;background:var(--n2);border-radius:100px;font-weight:600;color:var(--n7);font-size:.9rem;transition:.2s}
        .logout-btn:hover{background:var(--n3)}

        /* NAV TABS */
        .nav-tabs{display:flex;justify-content:center;gap:.5rem;margin:1.5rem auto;padding:.5rem;background:white;border-radius:100px;box-shadow:var(--sh-lg);max-width:1100px;border:1px solid var(--n3);flex-wrap:wrap}
        .ntab{padding:.7rem 1.4rem;border-radius:100px;font-weight:600;color:var(--n7);transition:.25s;display:flex;align-items:center;gap:.45rem;font-size:.9rem;white-space:nowrap}
        .ntab i{color:var(--m300);font-size:.9rem;transition:color .25s}
        .ntab:hover{background:var(--m50);color:var(--m300)}
        .ntab.active{background:var(--m300);color:white;box-shadow:0 8px 16px -4px rgba(132,38,70,.4)}
        .ntab.active i{color:white}

        /* WRAPPER */
        .page-wrapper{flex:1;display:flex;flex-direction:column}
        .container{max-width:1400px;width:100%;margin:0 auto;padding:2rem;flex:1}

        /* ALERTS */
        .alert{padding:1rem 1.5rem;border-radius:14px;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:var(--sl);color:var(--sd);border-left:6px solid var(--sp)}
        .alert-info{background:var(--il);color:var(--ip);border-left:6px solid var(--ip)}

        /* WELCOME BANNER */
        .welcome{background:linear-gradient(135deg,var(--m300),var(--m500));color:white;padding:2.5rem;border-radius:32px;margin-bottom:2rem;box-shadow:var(--sh-xl);position:relative;overflow:hidden;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1.5rem}
        .welcome::before{content:'';position:absolute;top:-60px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(255,255,255,.1) 0%,transparent 70%);border-radius:50%;pointer-events:none}
        .welcome h1{font-size:1.9rem;font-weight:700;margin-bottom:.3rem;position:relative;z-index:1}
        .welcome p{opacity:.85;position:relative;z-index:1;font-size:.95rem}
        .welcome-date{background:rgba(255,255,255,.15);backdrop-filter:blur(8px);padding:.8rem 1.5rem;border-radius:16px;text-align:center;position:relative;z-index:1;border:1px solid rgba(255,255,255,.2)}
        .welcome-date .d-day{font-size:2.5rem;font-weight:800;line-height:1}
        .welcome-date .d-month{font-size:.9rem;opacity:.85}

        /* STATS */
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;margin-bottom:2rem}
        .stat-card{background:white;border-radius:24px;padding:1.5rem;box-shadow:var(--sh-md);border:1px solid var(--n3);display:flex;align-items:center;gap:1rem;transition:.2s}
        .stat-card:hover{transform:translateY(-3px);box-shadow:var(--sh-xl)}
        .stat-icon{width:56px;height:56px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
        .si-maroon{background:var(--m50);color:var(--m300)}
        .si-blue{background:var(--il);color:var(--ip)}
        .si-green{background:var(--sl);color:var(--sd)}
        .si-orange{background:var(--wl);color:var(--wd)}
        .si-red{background:var(--el);color:var(--ed)}
        .stat-info p{font-size:.85rem;color:var(--n5);margin-bottom:.2rem}
        .stat-info h3{font-size:1.8rem;font-weight:700}

        /* QUICK ACTIONS */
        .quick-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem}
        .qa-card{background:white;border-radius:20px;padding:1.4rem 1rem;text-align:center;border:1px solid var(--n3);box-shadow:0 2px 8px rgba(0,0,0,.03);transition:.2s;color:var(--n7)}
        .qa-card:hover{transform:translateY(-3px);box-shadow:var(--sh-xl);border-color:var(--m100);color:var(--m300)}
        .qa-card i{font-size:2rem;color:var(--m300);display:block;margin-bottom:.8rem}
        .qa-card span{font-weight:600;font-size:.9rem}

        /* SECTION HEADING */
        .sec-head{font-size:1.2rem;font-weight:700;color:var(--n8);margin-bottom:1.2rem;display:flex;align-items:center;justify-content:space-between}
        .sec-head a{font-size:.88rem;color:var(--m300);font-weight:600}
        .badge-count{background:var(--ep);color:white;border-radius:100px;padding:.1rem .6rem;font-size:.78rem;font-weight:700;margin-left:.5rem}

        /* TABLE */
        .table-box{background:white;border-radius:24px;padding:1.5rem;box-shadow:var(--sh-md);border:1px solid var(--n3);overflow-x:auto;margin-bottom:2rem}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{background:linear-gradient(135deg,var(--m300),var(--m400));color:white;padding:.9rem 1rem;text-align:left;font-weight:600;font-size:.88rem}
        .data-table th:first-child{border-radius:12px 0 0 0}
        .data-table th:last-child{border-radius:0 12px 0 0}
        .data-table td{padding:.9rem 1rem;border-bottom:1px solid var(--n2);color:var(--n7);font-size:.9rem;vertical-align:middle}
        .data-table tr:last-child td{border-bottom:none}
        .data-table tr:hover td{background:var(--n1)}

        /* BADGES */
        .badge{padding:.3rem .9rem;border-radius:100px;font-size:.78rem;font-weight:600;display:inline-block}
        .b-requested{background:var(--wl);color:var(--wd)}
        .b-confirmed{background:var(--il);color:var(--ip)}
        .b-completed{background:var(--sl);color:var(--sd)}
        .b-cancelled{background:var(--el);color:var(--ed)}

        /* ACTION BUTTONS */
        .btn-sm{padding:.4rem .9rem;border-radius:40px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;font-family:inherit;transition:.2s;display:inline-flex;align-items:center;gap:.3rem}
        .btn-confirm{background:var(--sl);color:var(--sd)}
        .btn-confirm:hover{background:var(--sp);color:white}
        .btn-cancel{background:var(--el);color:var(--ed)}
        .btn-cancel:hover{background:var(--ep);color:white}
        .btn-view{background:var(--m50);color:var(--m300)}
        .btn-view:hover{background:var(--m300);color:white}

        /* EMPTY STATE */
        .empty{text-align:center;padding:2.5rem}
        .empty i{font-size:3rem;color:var(--n4);display:block;margin-bottom:.8rem}
        .empty p{color:var(--n5)}

        /* FOOTER */
        footer{background:var(--n7);color:var(--n2);padding:2rem;text-align:center;border-top:5px solid var(--m300);margin-top:auto}
        .footer-content{max-width:1400px;margin:0 auto}
        .footer-copy{color:var(--n5);font-size:.85rem;margin-top:.5rem}

        @media(max-width:768px){
            nav{flex-direction:column;gap:.8rem;padding:1rem}
            .container{padding:1rem}
            .nav-tabs{border-radius:24px;padding:.6rem}
            .ntab{padding:.6rem 1rem;font-size:.82rem}
            .welcome{flex-direction:column}
        }
    </style>
</head>
<body>

<header>
    <nav>
     <a href="receptionist-dashboard.php" class="logo">Valora</a>
        <div class="nav-right">
            <span class="user-pill"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($receptionist_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
</header>

<div class="page-wrapper">

    <!-- NAV TABS -->
    <div class="nav-tabs">
        <a href="receptionist-dashboard.php" class="ntab active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="manage-appointments.php"    class="ntab"><i class="fas fa-calendar-alt"></i> Appointments</a>
        <a href="records-receptionist.php"                class="ntab"><i class="fas fa-user-check"></i> Records</a>
        <a href="billing-receptionist.php"   class="ntab"><i class="fas fa-file-invoice-dollar"></i> Billing</a>
        <a href="register-patient.php"       class="ntab"><i class="fas fa-user-plus"></i> Register Patient</a>
    </div>

    <div class="container">

        <?php if (isset($_GET['confirmed'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Appointment confirmed successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['cancelled'])): ?>
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> Appointment cancelled.</div>
        <?php endif; ?>

        <!-- WELCOME -->
        <div class="welcome">
            <div>
                <h1>Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 17) ? 'Afternoon' : 'Evening'); ?>, <?php echo htmlspecialchars(explode(' ', $receptionist_name)[0]); ?>! 👋</h1>
                <p><i class="fas fa-clipboard-list"></i>&nbsp; Front Desk Operations Center</p>
            </div>
            <div class="welcome-date">
                <div class="d-day"><?php echo date('d'); ?></div>
                <div class="d-month"><?php echo date('M Y'); ?></div>
                <div class="d-month" style="margin-top:.2rem"><?php echo date('l'); ?></div>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon si-maroon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-info"><p>Today's Appointments</p><h3><?php echo $today_appointments; ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-blue"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info"><p>Confirmed Today</p><h3><?php echo $confirmed_today; ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-red"><i class="fas fa-clock"></i></div>
                <div class="stat-info"><p>Pending Requests</p><h3><?php echo $requested_count; ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-orange"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-info"><p>Unpaid Invoices</p><h3><?php echo $pending_invoices; ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-blue"><i class="fas fa-users"></i></div>
                <div class="stat-info"><p>Total Patients</p><h3><?php echo $total_patients; ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-green"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info"><p>Today's Revenue</p><h3>BD <?php echo number_format($today_revenue, 2); ?></h3></div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="sec-head">Quick Actions</div>
        <div class="quick-actions">
            <a href="register-patient.php"     class="qa-card"><i class="fas fa-user-plus"></i><span>Register Patient</span></a>
            <a href="manage-appointments.php?action=book" class="qa-card"><i class="fas fa-calendar-plus"></i><span>Book Appointment</span></a>
            <a href="checkin.php"              class="qa-card"><i class="fas fa-user-check"></i><span>Patient Check-In</span></a>
            <a href="billing-receptionist.php" class="qa-card"><i class="fas fa-file-invoice-dollar"></i><span>Generate Invoice</span></a>
            <a href="manage-appointments.php"  class="qa-card"><i class="fas fa-calendar-alt"></i><span>All Appointments</span></a>
            <a href="billing-receptionist.php?tab=payments" class="qa-card"><i class="fas fa-credit-card"></i><span>Process Payment</span></a>
        </div>

        <!-- PENDING REQUESTS -->
        <?php if (count($pending_requests) > 0): ?>
        <div class="sec-head">
            Pending Requests <span class="badge-count"><?php echo count($pending_requests); ?></span>
            <a href="manage-appointments.php?filter=Requested">View all →</a>
        </div>
        <div class="table-box">
            <table class="data-table">
                <thead>
                    <tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_requests as $apt): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong></td>
                        <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?><br><span style="color:var(--ip);font-size:.82rem"><?php echo htmlspecialchars($apt['specialization']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="apt_id" value="<?php echo $apt['id']; ?>">
                                <button name="confirm_apt" class="btn-sm btn-confirm"><i class="fas fa-check"></i> Confirm</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this appointment?')">
                                <input type="hidden" name="apt_id" value="<?php echo $apt['id']; ?>">
                                <button name="cancel_apt" class="btn-sm btn-cancel"><i class="fas fa-times"></i> Cancel</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- TODAY'S SCHEDULE -->
        <div class="sec-head">
            Today's Schedule
            <a href="manage-appointments.php">View all →</a>
        </div>
        <div class="table-box">
            <?php if (count($todays_appointments) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Specialization</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($todays_appointments as $apt): ?>
                    <tr>
                        <td><strong><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($apt['specialization']); ?></td>
                        <td><span class="badge b-<?php echo strtolower($apt['status']); ?>"><?php echo $apt['status']; ?></span></td>
                        <td>
                            <a href="checkin.php?apt_id=<?php echo $apt['id']; ?>" class="btn-sm btn-view"><i class="fas fa-sign-in-alt"></i> Check In</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty"><i class="fas fa-calendar-check"></i><p>No appointments scheduled for today.</p></div>
            <?php endif; ?>
        </div>

    </div>
</div>

<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem;font-weight:600">Valora Medical Center</p>
        <p style="color:var(--n4);font-size:.9rem">University Project · All information is fictional</p>
        <p class="footer-copy">&copy; 2026 Valora HMS. All rights reserved.</p>
    </div>
</footer>

</body>
</html>