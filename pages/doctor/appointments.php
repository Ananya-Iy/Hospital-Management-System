<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];

$d_result  = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id");
$doctor    = $d_result->fetch_assoc();
$doctor_id = $doctor['id'] ?? null;

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctor_id) {
    $action = $_POST['action'] ?? '';
    $apt_id = intval($_POST['appointment_id'] ?? 0);

    if ($action === 'confirm' && $apt_id) {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Confirmed' WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $apt_id, $doctor_id);
        $stmt->execute() ? $success = 'Appointment confirmed!' : $error = 'Failed to confirm.';
        $stmt->close();
        header("Location: appointments.php?filter=" . ($_GET['filter'] ?? 'upcoming') . "&confirmed=1"); exit;
    }
    if ($action === 'cancel' && $apt_id) {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $apt_id, $doctor_id);
        $stmt->execute() ? $success = 'Appointment cancelled.' : $error = 'Failed to cancel.';
        $stmt->close();
        header("Location: appointments.php?filter=" . ($_GET['filter'] ?? 'upcoming') . "&cancelled=1"); exit;
    }
    if ($action === 'complete' && $apt_id) {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $apt_id, $doctor_id);
        $stmt->execute() ? $success = 'Marked as completed!' : $error = 'Failed to update.';
        $stmt->close();
        header("Location: appointments.php?filter=" . ($_GET['filter'] ?? 'upcoming') . "&completed=1"); exit;
    }
}

$filter = $_GET['filter'] ?? 'upcoming';
$where_clause = "a.doctor_id = $doctor_id";

switch ($filter) {
    case 'today':    $where_clause .= " AND a.appointment_date = CURDATE()"; break;
    case 'upcoming': $where_clause .= " AND a.appointment_date >= CURDATE() AND a.status NOT IN ('Cancelled','Completed')"; break;
    case 'past':     $where_clause .= " AND (a.appointment_date < CURDATE() OR a.status = 'Completed')"; break;
    case 'pending':  $where_clause .= " AND a.status = 'Requested'"; break;
}

$appointments = [];
if ($doctor_id) {
    $apt_result = $conn->query(
        "SELECT a.*, u.name AS patient_name, p.id AS pat_id,
                p.date_of_birth AS dob, p.blood_group, u.phone
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         JOIN users u    ON p.user_id    = u.id
         WHERE $where_clause
         ORDER BY a.appointment_date ASC, a.appointment_time ASC"
    );
    while ($row = $apt_result->fetch_assoc()) $appointments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments · Valora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif}
        :root{
            --n1:#F2F2F2;--n2:#E6E6E6;--n3:#DADADA;--n4:#C6C6C6;--n5:#9E9E9E;--n6:#6E6E6E;--n7:#3F3F3F;--n8:#1C1C1C;
            --m50:#D8C9CE;--m100:#C5A8B3;--m200:#A56C7E;--m300:#842646;--m400:#7A2141;--m500:#641732;
            --sl:#C6D8D2;--sp:#39C37A;--sd:#2E955C;
            --el:#E2D0CD;--ep:#F04233;--ed:#B03125;
            --il:#C9D3E6;--ip:#0E3E9E;
            --wl:#E5D8C8;--wd:#B36805;
            --bg:#F8F9FC;
            --sh-md:0 8px 16px rgba(0,0,0,.04);
            --sh-lg:0 16px 24px rgba(0,0,0,.04);
            --sh-xl:0 24px 32px rgba(100,23,50,.08);
        }
        html,body{height:100%}
        body{background:var(--bg);color:var(--n8);min-height:100vh;display:flex;flex-direction:column;line-height:1.5}
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
        .main-tabs{display:flex;justify-content:center;gap:.5rem;margin:1.5rem auto;padding:.5rem;background:white;border-radius:100px;box-shadow:var(--sh-lg);max-width:960px;border:1px solid var(--n3);flex-wrap:wrap}
        .tab{padding:.7rem 1.4rem;border-radius:100px;font-weight:600;color:var(--n7);transition:.25s;display:flex;align-items:center;gap:.45rem;font-size:.92rem;white-space:nowrap}
        .tab i{color:var(--m300);font-size:.95rem}
        .tab:hover{background:var(--m50);color:var(--m300)}
        .tab.active{background:var(--m300);color:white;box-shadow:0 8px 16px -4px rgba(132,38,70,.4)}
        .tab.active i{color:white}
        .page-wrapper{flex:1;display:flex;flex-direction:column}
        .container{max-width:1200px;margin:1rem auto 3rem;padding:0 2rem;flex:1;width:100%}
        .page-header{margin-bottom:2rem}
        .page-header h1{font-size:2.2rem;font-weight:700;position:relative;padding-bottom:.8rem}
        .page-header h1::after{content:'';position:absolute;bottom:0;left:0;width:80px;height:4px;background:linear-gradient(90deg,var(--m300),var(--ip));border-radius:4px}
        .page-header p{color:var(--n6);margin-top:.8rem}
        .alert{padding:1rem 1.5rem;border-radius:14px;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:var(--sl);color:var(--sd);border-left:6px solid var(--sp)}
        .alert-error{background:var(--el);color:var(--ed);border-left:6px solid var(--ep)}
        .filter-tabs{display:flex;gap:.5rem;margin-bottom:2rem;flex-wrap:wrap}
        .filter-tab{padding:.6rem 1.4rem;border-radius:100px;font-weight:600;color:var(--n7);background:white;border:2px solid var(--n3);transition:.2s;font-size:.9rem}
        .filter-tab:hover{border-color:var(--m200);color:var(--m300)}
        .filter-tab.active{background:var(--m300);color:white;border-color:var(--m300)}
        .apt-card{background:white;border-radius:24px;padding:1.8rem;margin-bottom:1.5rem;border:1px solid var(--n3);box-shadow:var(--sh-md);transition:.2s;position:relative;overflow:hidden}
        .apt-card::before{content:'';position:absolute;left:0;top:0;width:5px;height:100%}
        .apt-card.s-Requested::before{background:linear-gradient(180deg,var(--wl),var(--wd))}
        .apt-card.s-Confirmed::before{background:linear-gradient(180deg,var(--ip),#082E73)}
        .apt-card.s-Completed::before{background:linear-gradient(180deg,var(--sp),var(--sd))}
        .apt-card.s-Cancelled::before{background:linear-gradient(180deg,var(--ep),var(--ed))}
        .apt-card:hover{transform:translateY(-2px);box-shadow:var(--sh-xl)}
        .apt-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem}
        .apt-patient{display:flex;align-items:center;gap:1rem}
        .pt-av{width:60px;height:60px;background:linear-gradient(135deg,var(--m50),var(--il));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--m300);flex-shrink:0}
        .pt-name{font-weight:700;font-size:1.1rem;margin-bottom:.2rem}
        .pt-meta{color:var(--n6);font-size:.85rem}
        .badge{padding:.35rem 1rem;border-radius:100px;font-size:.82rem;font-weight:600}
        .b-Requested{background:var(--wl);color:var(--wd)}
        .b-Confirmed{background:var(--il);color:var(--ip)}
        .b-Completed{background:var(--sl);color:var(--sd)}
        .b-Cancelled{background:var(--el);color:var(--ed)}
        .apt-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.8rem;padding:1rem 1.2rem;background:var(--n1);border-radius:14px;margin-bottom:1rem}
        .ai{font-size:.9rem;color:var(--n7)}
        .ai i{color:var(--m300);margin-right:.4rem}
        .ai strong{font-weight:600}
        .apt-reason{padding:.9rem 1rem;background:var(--il);border-radius:12px;margin-bottom:1rem;font-size:.88rem;color:var(--ip)}
        .apt-reason strong{font-weight:700;display:block;margin-bottom:.2rem}
        .apt-actions{display:flex;gap:.6rem;flex-wrap:wrap}
        .btn{padding:.6rem 1.3rem;border-radius:40px;font-weight:600;font-size:.85rem;border:none;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:.4rem;font-family:inherit}
        .btn-view{background:var(--m50);color:var(--m300)}
        .btn-view:hover{background:var(--m300);color:white}
        .btn-prescribe{background:var(--il);color:var(--ip)}
        .btn-prescribe:hover{background:var(--ip);color:white}
        .btn-confirm{background:var(--sl);color:var(--sd)}
        .btn-confirm:hover{background:var(--sp);color:white}
        .btn-complete{background:var(--n2);color:var(--n7)}
        .btn-complete:hover{background:var(--n4);color:white}
        .btn-cancel{background:var(--el);color:var(--ed)}
        .btn-cancel:hover{background:var(--ep);color:white}
        .empty{text-align:center;padding:4rem 2rem;background:white;border-radius:24px;box-shadow:var(--sh-md)}
        .empty i{font-size:4rem;color:var(--n4);display:block;margin-bottom:1.5rem}
        .empty h3{font-size:1.5rem;margin-bottom:.5rem;color:var(--n7)}
        .empty p{color:var(--n5)}
        footer{background:var(--n7);color:var(--n2);padding:2rem;text-align:center;border-top:5px solid var(--m300);margin-top:auto}
        .footer-content{max-width:1400px;margin:0 auto}
        .footer-copy{color:var(--n5);font-size:.85rem;margin-top:.5rem}
        @media(max-width:768px){nav{flex-direction:column;gap:1rem}.container{padding:0 1rem}.apt-head{flex-direction:column}.apt-actions{flex-direction:column}.btn{width:100%;justify-content:center}}
    </style>
</head>
<body>

<header>
    <nav>
        <a href="dashboard-doctor.php" class="logo">Valora</a>
        <div class="nav-right">
            <span class="user-pill"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
</header>

<div class="page-wrapper">
    <div class="main-tabs">
        <a href="appointments.php"  class="tab active"><i class="fas fa-calendar-alt"></i> Appointments</a>
        <a href="patients.php"      class="tab"><i class="fas fa-users"></i> Patients</a>
        <a href="prescriptions.php" class="tab"><i class="fas fa-prescription"></i> Prescriptions</a>
        <a href="availability.php"  class="tab"><i class="fas fa-calendar-times"></i> Schedule</a>
        <a href="profile.php"       class="tab"><i class="fas fa-user-md"></i> Profile</a>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>My Appointments</h1>
            <p>Manage your patient appointments and schedule</p>
        </div>

        <?php if (isset($_GET['confirmed'])): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Appointment confirmed!</div><?php endif; ?>
        <?php if (isset($_GET['cancelled'])): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Appointment cancelled.</div><?php endif; ?>
        <?php if (isset($_GET['completed'])): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Marked as completed.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="filter-tabs">
            <a href="?filter=today"    class="filter-tab <?php echo $filter==='today'   ?'active':''; ?>">Today</a>
            <a href="?filter=upcoming" class="filter-tab <?php echo $filter==='upcoming'?'active':''; ?>">Upcoming</a>
            <a href="?filter=pending"  class="filter-tab <?php echo $filter==='pending' ?'active':''; ?>">Pending Requests</a>
            <a href="?filter=past"     class="filter-tab <?php echo $filter==='past'    ?'active':''; ?>">Past</a>
        </div>

        <?php if (count($appointments) > 0): ?>
            <?php foreach ($appointments as $apt):
                $age = $apt['dob'] ? date_diff(date_create($apt['dob']), date_create('today'))->y : 'N/A';
            ?>
            <div class="apt-card s-<?php echo $apt['status']; ?>">
                <div class="apt-head">
                    <div class="apt-patient">
                        <div class="pt-av"><i class="fas fa-user-injured"></i></div>
                        <div>
                            <div class="pt-name"><?php echo htmlspecialchars($apt['patient_name']); ?></div>
                            <div class="pt-meta">
                                <?php echo $age !== 'N/A' ? "Age $age" : ''; ?>
                                <?php echo !empty($apt['blood_group']) ? " · Blood: {$apt['blood_group']}" : ''; ?>
                                <?php echo !empty($apt['phone']) ? " · {$apt['phone']}" : ''; ?>
                            </div>
                        </div>
                    </div>
                    <span class="badge b-<?php echo $apt['status']; ?>"><?php echo $apt['status']; ?></span>
                </div>

                <div class="apt-info">
                    <div class="ai"><i class="fas fa-calendar"></i><strong>Date:</strong> <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></div>
                    <div class="ai"><i class="fas fa-clock"></i><strong>Time:</strong> <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></div>
                    <div class="ai"><i class="fas fa-hashtag"></i><strong>ID:</strong> #APT-<?php echo str_pad($apt['id'], 5, '0', STR_PAD_LEFT); ?></div>
                </div>

                <?php if (!empty($apt['reason'])): ?>
                <div class="apt-reason">
                    <strong><i class="fas fa-notes-medical"></i> Reason for Visit</strong>
                    <?php echo htmlspecialchars($apt['reason']); ?>
                </div>
                <?php endif; ?>

                <div class="apt-actions">

                    <!-- View patient full record -->
                    <a href="patients.php?patient=<?php echo $apt['pat_id']; ?>" class="btn btn-view">
                        <i class="fas fa-folder-open"></i> Patient Record
                    </a>

                    <!-- Write prescription -->
                    <a href="prescriptions.php?patient_id=<?php echo $apt['pat_id']; ?>" class="btn btn-prescribe">
                        <i class="fas fa-prescription"></i> Prescribe
                    </a>

                    <?php if ($apt['status'] === 'Requested'): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="btn btn-confirm"><i class="fas fa-check"></i> Confirm</button>
                    </form>
                    <?php endif; ?>

                    <?php if ($apt['status'] === 'Confirmed' && $apt['appointment_date'] <= date('Y-m-d')): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="btn btn-complete"><i class="fas fa-check-double"></i> Complete</button>
                    </form>
                    <?php endif; ?>

                    <?php if (!in_array($apt['status'], ['Cancelled','Completed'])): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-cancel"
                                onclick="return confirm('Cancel this appointment?')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="empty">
            <i class="fas fa-calendar-times"></i>
            <h3>No appointments found</h3>
            <p>You don't have any appointments matching this filter.</p>
        </div>
        <?php endif; ?>

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