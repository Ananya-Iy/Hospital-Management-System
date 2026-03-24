<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('receptionist');

$user_id           = $_SESSION['user_id'];
$receptionist_name = $_SESSION['name'];
$success = '';
$error   = '';

// ── Handle status update ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $apt_id     = intval($_POST['apt_id']);
    $new_status = sanitize($_POST['new_status']);
    $allowed    = ['Requested','Confirmed','Completed','Cancelled'];
    if (in_array($new_status, $allowed)) {
        $conn->query("UPDATE appointments SET status = '$new_status' WHERE id = $apt_id");
        header("Location: manage-appointments.php?updated=1&filter=" . ($_GET['filter'] ?? 'all'));
        exit;
    }
}

// ── Handle reschedule ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule'])) {
    $apt_id   = intval($_POST['apt_id']);
    $new_date = sanitize($_POST['new_date']);
    $new_time = sanitize($_POST['new_time']);

    // Check slot availability
    $check = $conn->prepare(
        "SELECT id FROM appointments WHERE doctor_id = (SELECT doctor_id FROM appointments WHERE id = ?)
         AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled' AND id != ?"
    );
    $check->bind_param("issi", $apt_id, $new_date, $new_time, $apt_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = 'That time slot is already taken. Please choose another.';
    } else {
        $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'Confirmed' WHERE id = ?");
        $stmt->bind_param("ssi", $new_date, $new_time, $apt_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manage-appointments.php?rescheduled=1");
        exit;
    }
    $check->close();
}

// ── Handle book on behalf ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_apt'])) {
    $patient_id   = intval($_POST['patient_id']);
    $doctor_id    = intval($_POST['doctor_id']);
    $apt_date     = sanitize($_POST['apt_date']);
    $apt_time     = sanitize($_POST['apt_time']);
    $reason       = sanitize($_POST['reason'] ?? '');

    // Check slot
    $check = $conn->prepare(
        "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled'"
    );
    $check->bind_param("iss", $doctor_id, $apt_date, $apt_time);
    $check->execute(); $check->store_result();

    if ($check->num_rows > 0) {
        $error = 'That time slot is already taken.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status)
             VALUES (?, ?, ?, ?, ?, 'Confirmed')"
        );
        $stmt->bind_param("iisss", $patient_id, $doctor_id, $apt_date, $apt_time, $reason);
        if ($stmt->execute()) {
            $apt_id_new = $conn->insert_id;
            // Auto-create invoice
            $fee_res = $conn->query("SELECT consultation_fee FROM doctors WHERE id = $doctor_id");
            $fee     = $fee_res->fetch_assoc()['consultation_fee'] ?? 0;
            $inv_num = 'INV-' . str_pad($apt_id_new, 5, '0', STR_PAD_LEFT);
            $inv_stmt = $conn->prepare(
                "INSERT INTO invoices (patient_id, appointment_id, invoice_number, total_amount, paid_amount, status, due_date)
                 VALUES (?, ?, ?, ?, 0.00, 'Unpaid', ?)"
            );
            $inv_stmt->bind_param("iisds", $patient_id, $apt_id_new, $inv_num, $fee, $apt_date);
            $inv_stmt->execute(); $inv_stmt->close();
            header("Location: manage-appointments.php?booked=1"); exit;
        } else {
            $error = 'Failed to book appointment.';
        }
        $stmt->close();
    }
    $check->close();
}

// ── Filter + Search ───────────────────────────────────────────────────────────
$filter  = $_GET['filter'] ?? 'all';
$search  = sanitize($_GET['search'] ?? '');
$date    = sanitize($_GET['date']   ?? '');
$allowed_filters = ['all','Requested','Confirmed','Completed','Cancelled'];
if (!in_array($filter, $allowed_filters)) $filter = 'all';

$where = "WHERE 1=1";
if ($filter !== 'all') $where .= " AND a.status = '$filter'";
if ($search)           $where .= " AND (u.name LIKE '%$search%' OR du.name LIKE '%$search%')";
if ($date)             $where .= " AND a.appointment_date = '$date'";

$appointments = [];
$result = $conn->query(
    "SELECT a.*, u.name AS patient_name, du.name AS doctor_name, s.name AS specialization, d.consultation_fee
     FROM appointments a
     JOIN patients p  ON a.patient_id = p.id
     JOIN users u     ON p.user_id    = u.id
     JOIN doctors d   ON a.doctor_id  = d.id
     JOIN users du    ON d.user_id    = du.id
     JOIN specializations s ON d.specialization_id = s.id
     $where
     ORDER BY a.appointment_date DESC, a.appointment_time ASC"
);
while ($row = $result->fetch_assoc()) $appointments[] = $row;

// ── Data for booking form ─────────────────────────────────────────────────────
$all_patients = [];
$pr = $conn->query("SELECT p.id, u.name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name");
while ($r = $pr->fetch_assoc()) $all_patients[] = $r;

$all_doctors = [];
$dr = $conn->query("SELECT d.id, u.name, s.name AS spec FROM doctors d JOIN users u ON d.user_id = u.id JOIN specializations s ON d.specialization_id = s.id ORDER BY u.name");
while ($r = $dr->fetch_assoc()) $all_doctors[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments · Valora Receptionist</title>
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
        header{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);box-shadow:var(--sh-md);position:sticky;top:0;z-index:50;border-bottom:1px solid rgba(218,218,218,.3)}
        nav{display:flex;justify-content:space-between;align-items:center;padding:1rem 2rem;max-width:1400px;margin:0 auto}
        .logo{font-size:1.8rem;font-weight:800;color:var(--m300);letter-spacing:-.5px;position:relative}
        .logo::after{content:'';position:absolute;bottom:-4px;left:0;width:40%;height:3px;background:var(--m300);border-radius:4px}
        .nav-right{display:flex;align-items:center;gap:1rem}
        .user-pill{display:flex;align-items:center;gap:.8rem;padding:.5rem 1.2rem;background:white;border-radius:100px;border:1px solid var(--n3);color:var(--n8);font-weight:600}
        .user-pill i{color:var(--m300)}
        .logout-btn{padding:.5rem 1.2rem;background:var(--n2);border-radius:100px;font-weight:600;color:var(--n7);font-size:.9rem}
        .logout-btn:hover{background:var(--n3)}
        .nav-tabs{display:flex;justify-content:center;gap:.5rem;margin:1.5rem auto;padding:.5rem;background:white;border-radius:100px;box-shadow:var(--sh-lg);max-width:1100px;border:1px solid var(--n3);flex-wrap:wrap}
        .ntab{padding:.7rem 1.4rem;border-radius:100px;font-weight:600;color:var(--n7);transition:.25s;display:flex;align-items:center;gap:.45rem;font-size:.9rem;white-space:nowrap}
        .ntab i{color:var(--m300);font-size:.9rem}
        .ntab:hover{background:var(--m50);color:var(--m300)}
        .ntab.active{background:var(--m300);color:white;box-shadow:0 8px 16px -4px rgba(132,38,70,.4)}
        .ntab.active i{color:white}
        .page-wrapper{flex:1;display:flex;flex-direction:column}
        .container{max-width:1400px;width:100%;margin:0 auto;padding:2rem;flex:1}

        .alert{padding:1rem 1.5rem;border-radius:14px;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:var(--sl);color:var(--sd);border-left:6px solid var(--sp)}
        .alert-error{background:var(--el);color:var(--ed);border-left:6px solid var(--ep)}

        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
        .page-header h1{font-size:2rem;font-weight:700;position:relative;padding-bottom:.8rem}
        .page-header h1::after{content:'';position:absolute;bottom:0;left:0;width:80px;height:4px;background:linear-gradient(90deg,var(--m300),var(--ip));border-radius:4px}

        /* BOOK BUTTON */
        .btn-primary{padding:.8rem 1.8rem;background:var(--m300);color:white;border-radius:40px;font-weight:700;font-size:.9rem;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;font-family:inherit;transition:.2s}
        .btn-primary:hover{background:var(--m400);transform:translateY(-1px)}

        /* FILTERS */
        .filter-row{display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:center}
        .filter-btn{padding:.55rem 1.3rem;border-radius:40px;font-weight:600;font-size:.88rem;border:1px solid var(--n3);background:white;color:var(--n7);transition:.2s;cursor:pointer;display:flex;align-items:center;gap:.4rem}
        .filter-btn:hover{border-color:var(--m200);color:var(--m300);background:var(--m50)}
        .filter-btn.active{background:var(--m300);color:white;border-color:var(--m300)}

        /* SEARCH BAR */
        .search-bar{display:flex;gap:.8rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .search-input{flex:1;min-width:200px;padding:.8rem 1.2rem;border:2px solid var(--n3);border-radius:14px;font-size:.95rem;background:white;font-family:inherit}
        .search-input:focus{outline:none;border-color:var(--m300)}
        .date-input{padding:.8rem 1.2rem;border:2px solid var(--n3);border-radius:14px;font-size:.95rem;background:white;font-family:inherit}
        .date-input:focus{outline:none;border-color:var(--m300)}
        .search-btn{padding:.8rem 1.5rem;background:var(--m300);color:white;border:none;border-radius:14px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:.5rem}

        /* TABLE */
        .table-box{background:white;border-radius:24px;padding:1.5rem;box-shadow:var(--sh-md);border:1px solid var(--n3);overflow-x:auto;margin-bottom:2rem}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{background:linear-gradient(135deg,var(--m300),var(--m400));color:white;padding:.9rem 1rem;text-align:left;font-weight:600;font-size:.88rem}
        .data-table th:first-child{border-radius:12px 0 0 0}
        .data-table th:last-child{border-radius:0 12px 0 0}
        .data-table td{padding:.9rem 1rem;border-bottom:1px solid var(--n2);color:var(--n7);font-size:.9rem;vertical-align:middle}
        .data-table tr:last-child td{border-bottom:none}
        .data-table tr:hover td{background:var(--n1)}

        .badge{padding:.3rem .9rem;border-radius:100px;font-size:.78rem;font-weight:600;display:inline-block}
        .b-requested{background:var(--wl);color:var(--wd)}
        .b-confirmed{background:var(--il);color:var(--ip)}
        .b-completed{background:var(--sl);color:var(--sd)}
        .b-cancelled{background:var(--el);color:var(--ed)}

        /* ACTION BUTTONS */
        .btn-sm{padding:.35rem .8rem;border-radius:40px;font-size:.78rem;font-weight:600;cursor:pointer;border:none;font-family:inherit;transition:.2s;display:inline-flex;align-items:center;gap:.3rem}
        .btn-confirm{background:var(--sl);color:var(--sd)}
        .btn-confirm:hover{background:var(--sp);color:white}
        .btn-cancel{background:var(--el);color:var(--ed)}
        .btn-cancel:hover{background:var(--ep);color:white}
        .btn-reschedule{background:var(--il);color:var(--ip)}
        .btn-reschedule:hover{background:var(--ip);color:white}
        .btn-complete{background:var(--m50);color:var(--m300)}
        .btn-complete:hover{background:var(--m300);color:white}

        /* EMPTY STATE */
        .empty{text-align:center;padding:3rem 2rem}
        .empty i{font-size:3rem;color:var(--n4);display:block;margin-bottom:.8rem}
        .empty p{color:var(--n5)}

        /* MODAL */
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center}
        .modal.active{display:flex}
        .modal-box{background:white;border-radius:28px;padding:2.5rem;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:var(--sh-xl);position:relative;animation:popIn .25s ease}
        @keyframes popIn{from{opacity:0;transform:scale(.95) translateY(-10px)}to{opacity:1;transform:scale(1) translateY(0)}}
        .modal-close{position:absolute;top:1.2rem;right:1.2rem;font-size:1.3rem;color:var(--n5);cursor:pointer}
        .modal-close:hover{color:var(--n8)}
        .modal-title{font-size:1.4rem;font-weight:700;margin-bottom:1.5rem}
        .fg{margin-bottom:1.2rem}
        .fg label{display:block;font-weight:600;font-size:.88rem;margin-bottom:.4rem;color:var(--n7)}
        .fc{width:100%;padding:.9rem 1.1rem;border:2px solid var(--n3);border-radius:14px;font-size:.95rem;background:var(--n1);font-family:inherit;transition:.2s}
        .fc:focus{outline:none;border-color:var(--m300);background:white}
        select.fc{appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23842646' stroke-width='2'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right 1rem center;background-size:1rem;background-color:var(--n1)}
        .modal-actions{display:flex;gap:1rem;margin-top:1.5rem}
        .mbtn{flex:1;padding:.9rem;border:none;border-radius:40px;font-weight:700;font-size:.9rem;cursor:pointer;font-family:inherit;transition:.2s}
        .mbtn-primary{background:var(--m300);color:white}
        .mbtn-primary:hover{background:var(--m400)}
        .mbtn-secondary{background:var(--n2);color:var(--n7)}
        .mbtn-secondary:hover{background:var(--n3)}

        footer{background:var(--n7);color:var(--n2);padding:2rem;text-align:center;border-top:5px solid var(--m300);margin-top:auto}
        .footer-content{max-width:1400px;margin:0 auto}
        .footer-copy{color:var(--n5);font-size:.85rem;margin-top:.5rem}
        @media(max-width:768px){nav{flex-direction:column;gap:.8rem;padding:1rem}.container{padding:1rem}.nav-tabs{border-radius:24px}.ntab{padding:.6rem 1rem;font-size:.82rem}}
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
    <div class="nav-tabs">
        <a href="receptionist-dashboard.php" class="ntab"><i class="fas fa-home"></i> Dashboard</a>
        <a href="manage-appointments.php"    class="ntab active"><i class="fas fa-calendar-alt"></i> Appointments</a>
        <a href="records-receptionist.php"                class="ntab"><i class="fas fa-user-check"></i> Records</a>
        <a href="billing-receptionist.php"   class="ntab"><i class="fas fa-file-invoice-dollar"></i> Billing</a>
        <a href="register-patient.php"       class="ntab"><i class="fas fa-user-plus"></i> Register Patient</a>
    </div>

    <div class="container">

        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
        <?php if (isset($_GET['updated'])):     ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Status updated.</div><?php endif; ?>
        <?php if (isset($_GET['booked'])):      ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Appointment booked and invoice created.</div><?php endif; ?>
        <?php if (isset($_GET['rescheduled'])): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Appointment rescheduled.</div><?php endif; ?>

        <div class="page-header">
            <h1>Appointments</h1>
            <button class="btn-primary" onclick="document.getElementById('bookModal').classList.add('active')">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </button>
        </div>

        <!-- FILTERS -->
        <div class="filter-row">
            <?php foreach (['all'=>'All','Requested'=>'Requested','Confirmed'=>'Confirmed','Completed'=>'Completed','Cancelled'=>'Cancelled'] as $val => $label): ?>
            <a href="?filter=<?php echo $val; ?><?php echo $search ? '&search='.$search : ''; ?><?php echo $date ? '&date='.$date : ''; ?>"
               class="filter-btn <?php echo $filter === $val ? 'active' : ''; ?>">
                <?php echo $label; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- SEARCH -->
        <form method="GET" action="">
            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
            <div class="search-bar">
                <input type="text"  name="search" class="search-input" placeholder="Search patient or doctor name..." value="<?php echo htmlspecialchars($search); ?>">
                <input type="date"  name="date"   class="date-input"   value="<?php echo htmlspecialchars($date); ?>">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                <?php if ($search || $date): ?>
                <a href="?filter=<?php echo $filter; ?>" style="padding:.8rem 1.2rem;background:var(--n2);border-radius:14px;color:var(--n7);font-weight:600;display:flex;align-items:center;gap:.4rem">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- TABLE -->
        <div class="table-box">
            <?php if (count($appointments) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Fee</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $apt): ?>
                    <tr>
                        <td style="color:var(--n5);font-size:.82rem">#<?php echo $apt['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong></td>
                        <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?><br>
                            <span style="color:var(--ip);font-size:.8rem"><?php echo htmlspecialchars($apt['specialization']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></td>
                        <td>BD <?php echo number_format($apt['consultation_fee'], 2); ?></td>
                        <td><span class="badge b-<?php echo strtolower($apt['status']); ?>"><?php echo $apt['status']; ?></span></td>
                        <td style="white-space:nowrap">
                            <?php if ($apt['status'] === 'Requested'): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="apt_id"    value="<?php echo $apt['id']; ?>">
                                <input type="hidden" name="new_status" value="Confirmed">
                                <button name="update_status" class="btn-sm btn-confirm"><i class="fas fa-check"></i> Confirm</button>
                            </form>
                            <?php endif; ?>
                            <?php if (in_array($apt['status'], ['Requested','Confirmed'])): ?>
                            <button class="btn-sm btn-reschedule"
                                onclick="openReschedule(<?php echo $apt['id']; ?>, '<?php echo $apt['appointment_date']; ?>', '<?php echo substr($apt['appointment_time'],0,5); ?>')">
                                <i class="fas fa-calendar-day"></i> Reschedule
                            </button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this appointment?')">
                                <input type="hidden" name="apt_id"     value="<?php echo $apt['id']; ?>">
                                <input type="hidden" name="new_status" value="Cancelled">
                                <button name="update_status" class="btn-sm btn-cancel"><i class="fas fa-times"></i> Cancel</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($apt['status'] === 'Confirmed'): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="apt_id"    value="<?php echo $apt['id']; ?>">
                                <input type="hidden" name="new_status" value="Completed">
                                <button name="update_status" class="btn-sm btn-complete"><i class="fas fa-flag-checkered"></i> Complete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty"><i class="fas fa-calendar-times"></i><p>No appointments found.</p></div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- BOOK MODAL -->
<div id="bookModal" class="modal">
    <div class="modal-box">
        <i class="fas fa-times modal-close" onclick="document.getElementById('bookModal').classList.remove('active')"></i>
        <div class="modal-title"><i class="fas fa-calendar-plus" style="color:var(--m300)"></i> Book Appointment</div>
        <form method="POST">
            <div class="fg" style="position:relative">
                <label>Patient</label>
                <input type="text" id="patientSearch" class="fc" placeholder="Type patient name to search..."
                       autocomplete="off" oninput="filterPatients(this.value)">
                <input type="hidden" name="patient_id" id="patientIdHidden" required>
                <div id="patientDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:2px solid var(--m300);border-radius:14px;box-shadow:var(--sh-xl);max-height:200px;overflow-y:auto;z-index:100;margin-top:.3rem"></div>
                <div id="patientSelected" style="display:none;margin-top:.5rem;padding:.5rem 1rem;background:var(--sl);color:var(--sd);border-radius:10px;font-size:.88rem;font-weight:600;display:flex;align-items:center;justify-content:space-between">
                    <span id="patientSelectedName"></span>
                    <span onclick="clearPatient()" style="cursor:pointer;color:var(--ed)"><i class="fas fa-times"></i></span>
                </div>
            </div>
            <div class="fg">
                <label>Doctor</label>
                <select name="doctor_id" class="fc" required>
                    <option value="">Select doctor...</option>
                    <?php foreach ($all_doctors as $d): ?>
                    <option value="<?php echo $d['id']; ?>">Dr. <?php echo htmlspecialchars($d['name']); ?> – <?php echo htmlspecialchars($d['spec']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Date</label>
                <input type="date" name="apt_date" class="fc" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="fg">
                <label>Time Slot</label>
                <select name="apt_time" class="fc" required>
                    <option value="">Select time...</option>
                    <?php foreach (['09:00','10:00','11:00','12:00','14:00','15:00','16:00','17:00'] as $t): ?>
                    <option value="<?php echo $t; ?>"><?php echo date('h:i A', strtotime($t)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Reason <span style="color:var(--n5);font-weight:400">(optional)</span></label>
                <input type="text" name="reason" class="fc" placeholder="e.g. Routine check-up, Follow-up...">
            </div>
            <div class="modal-actions">
                <button type="button" class="mbtn mbtn-secondary" onclick="document.getElementById('bookModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="book_apt" class="mbtn mbtn-primary"><i class="fas fa-calendar-check"></i> Book</button>
            </div>
        </form>
    </div>
</div>

<!-- RESCHEDULE MODAL -->
<div id="rescheduleModal" class="modal">
    <div class="modal-box">
        <i class="fas fa-times modal-close" onclick="document.getElementById('rescheduleModal').classList.remove('active')"></i>
        <div class="modal-title"><i class="fas fa-calendar-day" style="color:var(--ip)"></i> Reschedule Appointment</div>
        <form method="POST">
            <input type="hidden" name="apt_id" id="rescheduleId">
            <div class="fg">
                <label>New Date</label>
                <input type="date" name="new_date" id="rescheduleDate" class="fc" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="fg">
                <label>New Time Slot</label>
                <select name="new_time" id="rescheduleTime" class="fc" required>
                    <option value="">Select time...</option>
                    <?php foreach (['09:00','10:00','11:00','12:00','14:00','15:00','16:00','17:00'] as $t): ?>
                    <option value="<?php echo $t; ?>"><?php echo date('h:i A', strtotime($t)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="mbtn mbtn-secondary" onclick="document.getElementById('rescheduleModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="reschedule" class="mbtn mbtn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem;font-weight:600">Valora Medical Center</p>
        <p style="color:var(--n4);font-size:.9rem">University Project · All information is fictional</p>
        <p class="footer-copy">&copy; 2026 Valora HMS. All rights reserved.</p>
    </div>
</footer>

<script>
// ── Patient live search ───────────────────────────────────────────────────────
const patients = <?php echo json_encode($all_patients); ?>;

function filterPatients(query) {
    const dropdown = document.getElementById('patientDropdown');
    document.getElementById('patientIdHidden').value = '';
    document.getElementById('patientSelected').style.display = 'none';
    if (query.trim().length < 1) { dropdown.style.display = 'none'; return; }
    const matches = patients.filter(p => p.name.toLowerCase().includes(query.toLowerCase()));
    if (matches.length === 0) {
        dropdown.innerHTML = '<div style="padding:.8rem 1.2rem;color:var(--n5);font-size:.88rem">No patients found</div>';
    } else {
        dropdown.innerHTML = matches.map(p =>
            `<div onclick="selectPatient(${p.id}, '${p.name.replace(/'/g,"\\'")}')"
                  style="padding:.8rem 1.2rem;cursor:pointer;font-size:.9rem;border-bottom:1px solid var(--n2);transition:.15s"
                  onmouseover="this.style.background='var(--m50)'" onmouseout="this.style.background='white'">
                <i class="fas fa-user" style="color:var(--m300);margin-right:.5rem;font-size:.8rem"></i>${p.name}
            </div>`
        ).join('');
    }
    dropdown.style.display = 'block';
}

function selectPatient(id, name) {
    document.getElementById('patientIdHidden').value = id;
    document.getElementById('patientSearch').value   = '';
    document.getElementById('patientDropdown').style.display = 'none';
    document.getElementById('patientSelectedName').textContent = '✓ ' + name;
    document.getElementById('patientSelected').style.display = 'flex';
}

function clearPatient() {
    document.getElementById('patientIdHidden').value = '';
    document.getElementById('patientSearch').value   = '';
    document.getElementById('patientSelected').style.display = 'none';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#patientSearch') && !e.target.closest('#patientDropdown')) {
        document.getElementById('patientDropdown').style.display = 'none';
    }
});

function openReschedule(id, date, time) {
    document.getElementById('rescheduleId').value   = id;
    document.getElementById('rescheduleDate').value = date;
    document.getElementById('rescheduleTime').value = time;
    document.getElementById('rescheduleModal').classList.add('active');
}
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); });
});
<?php if (isset($_GET['action']) && $_GET['action'] === 'book'): ?>
document.getElementById('bookModal').classList.add('active');
<?php endif; ?>
</script>

</body>
</html>