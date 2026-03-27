<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('receptionist');

$receptionist_name = $_SESSION['name'];

$tab          = $_GET['tab']     ?? 'patients';
$search       = sanitize($_GET['search']  ?? '');
$view_patient = intval($_GET['patient']   ?? 0);
$view_doctor  = intval($_GET['doctor']    ?? 0);

// ── Patient detail ────────────────────────────────────────────────────────────
$patient_detail        = null;
$patient_appointments  = [];
$patient_invoices      = [];
$patient_records       = [];
$patient_prescriptions = [];

if ($view_patient) {
    $patient_detail = $conn->query(
        "SELECT p.*, u.name, u.email, u.phone, u.created_at AS registered_at
         FROM patients p JOIN users u ON p.user_id = u.id
         WHERE p.id = $view_patient"
    )->fetch_assoc();

    if ($patient_detail) {
        $r = $conn->query(
            "SELECT a.*, du.name AS doctor_name, s.name AS specialization, d.consultation_fee
             FROM appointments a
             JOIN doctors d ON a.doctor_id = d.id
             JOIN users du  ON d.user_id   = du.id
             JOIN specializations s ON d.specialization_id = s.id
             WHERE a.patient_id = $view_patient ORDER BY a.appointment_date DESC LIMIT 20"
        );
        while ($row = $r->fetch_assoc()) $patient_appointments[] = $row;

        $r = $conn->query(
            "SELECT i.*, a.appointment_date, du.name AS doctor_name
             FROM invoices i
             JOIN appointments a ON i.appointment_id = a.id
             JOIN doctors d      ON a.doctor_id = d.id
             JOIN users du       ON d.user_id   = du.id
             WHERE i.patient_id = $view_patient ORDER BY i.id DESC LIMIT 10"
        );
        while ($row = $r->fetch_assoc()) $patient_invoices[] = $row;

        $r = $conn->query(
            "SELECT pr.*, du.name AS doctor_name
             FROM prescriptions pr
             JOIN doctors d ON pr.doctor_id = d.id
             JOIN users du  ON d.user_id    = du.id
             WHERE pr.patient_id = $view_patient ORDER BY pr.issued_date DESC LIMIT 10"
        );
        while ($row = $r->fetch_assoc()) $patient_prescriptions[] = $row;

        $r = $conn->query(
            "SELECT mr.*, du.name AS doctor_name, s.name AS specialization
             FROM medical_records mr
             JOIN doctors d ON mr.doctor_id = d.id
             JOIN users du  ON d.user_id    = du.id
             JOIN specializations s ON d.specialization_id = s.id
             WHERE mr.patient_id = $view_patient ORDER BY mr.record_date DESC LIMIT 10"
        );
        while ($row = $r->fetch_assoc()) $patient_records[] = $row;
    }
}

// ── Doctor detail ─────────────────────────────────────────────────────────────
$doctor_detail       = null;
$doctor_appointments = [];
$doctor_schedule     = [];

if ($view_doctor) {
    $doctor_detail = $conn->query(
        "SELECT d.*, u.name, u.email, u.phone, s.name AS specialization
         FROM doctors d JOIN users u ON d.user_id = u.id
         JOIN specializations s ON d.specialization_id = s.id
         WHERE d.id = $view_doctor"
    )->fetch_assoc();

    if ($doctor_detail) {
        $r = $conn->query(
            "SELECT a.*, u.name AS patient_name FROM appointments a
             JOIN patients p ON a.patient_id = p.id JOIN users u ON p.user_id = u.id
             WHERE a.doctor_id = $view_doctor ORDER BY a.appointment_date DESC LIMIT 15"
        );
        while ($row = $r->fetch_assoc()) $doctor_appointments[] = $row;

        $r = $conn->query(
            "SELECT a.*, u.name AS patient_name FROM appointments a
             JOIN patients p ON a.patient_id = p.id JOIN users u ON p.user_id = u.id
             WHERE a.doctor_id = $view_doctor AND a.appointment_date >= CURDATE()
             AND a.status NOT IN ('Cancelled') ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 10"
        );
        while ($row = $r->fetch_assoc()) $doctor_schedule[] = $row;
    }
}

// ── Search lists ──────────────────────────────────────────────────────────────
$patients = [];
if ($tab === 'patients' && !$view_patient) {
    $w = $search ? "WHERE u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%'" : "";
    $order = $search ? "ORDER BY u.name" : "ORDER BY p.created_at DESC LIMIT 20";
    $r = $conn->query("SELECT p.id, u.name, u.email, u.phone, p.gender, p.blood_group, p.date_of_birth FROM patients p JOIN users u ON p.user_id = u.id $w $order");
    while ($row = $r->fetch_assoc()) $patients[] = $row;
}

$doctors = [];
if ($tab === 'doctors' && !$view_doctor) {
    $w = $search ? "WHERE u.name LIKE '%$search%' OR s.name LIKE '%$search%' OR d.qualification LIKE '%$search%'" : "";
    $r = $conn->query("SELECT d.id, u.name, u.email, u.phone, s.name AS specialization, d.qualification, d.experience_years, d.consultation_fee, d.available_days, d.available_from, d.available_to, d.bio FROM doctors d JOIN users u ON d.user_id = u.id JOIN specializations s ON d.specialization_id = s.id $w ORDER BY u.name");
    while ($row = $r->fetch_assoc()) $doctors[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records · Valora Receptionist</title>
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
            --il:#C9D3E6;--ip:#0E3E9E;--id:#082E73;
            --bg:#F8F9FC;
            --sh-sm:0 4px 8px rgba(0,0,0,.03);
            --sh-md:0 8px 16px rgba(0,0,0,.04);
            --sh-lg:0 16px 24px rgba(0,0,0,.04);
            --sh-xl:0 24px 32px rgba(100,23,50,.08);
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
        .page-header{margin-bottom:2rem}
        .page-header h1{font-size:2rem;font-weight:700;position:relative;padding-bottom:.8rem}
        .page-header h1::after{content:'';position:absolute;bottom:0;left:0;width:80px;height:4px;background:linear-gradient(90deg,var(--m300),var(--ip));border-radius:4px}

        /* INNER TABS */
        .inner-tabs{display:flex;gap:.5rem;margin-bottom:2rem;background:white;border-radius:100px;padding:.4rem;border:1px solid var(--n3);box-shadow:var(--sh-sm);width:fit-content}
        .itab{padding:.6rem 1.5rem;border-radius:100px;font-weight:600;font-size:.9rem;color:var(--n6);transition:.2s}
        .itab:hover{background:var(--m50);color:var(--m300)}
        .itab.active{background:var(--m300);color:white}

        /* SEARCH HERO */
        .search-hero{border-radius:28px;padding:2.5rem;margin-bottom:2rem;color:white;box-shadow:var(--sh-xl);position:relative;overflow:hidden}
        .search-hero.patient-hero{background:linear-gradient(135deg,var(--m300),var(--m500))}
        .search-hero.doctor-hero{background:linear-gradient(135deg,var(--ip),var(--id))}
        .search-hero::before{content:'';position:absolute;top:-60px;right:-60px;width:280px;height:280px;background:radial-gradient(circle,rgba(255,255,255,.1) 0%,transparent 70%);border-radius:50%;pointer-events:none}
        .search-hero h2{font-size:1.5rem;font-weight:700;margin-bottom:.3rem;position:relative;z-index:1}
        .search-hero p{opacity:.85;font-size:.92rem;margin-bottom:1.5rem;position:relative;z-index:1}
        .search-box{display:flex;gap:.8rem;position:relative;z-index:1;flex-wrap:wrap}
        .s-input{flex:1;min-width:200px;padding:1rem 1.4rem;border:none;border-radius:14px;font-size:1rem;font-family:inherit;background:rgba(255,255,255,.95)}
        .s-input:focus{outline:2px solid rgba(255,255,255,.6)}
        .s-btn{padding:1rem 2rem;background:white;border:none;border-radius:14px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:.5rem;transition:.2s}
        .s-btn.patient-btn{color:var(--m300)}
        .s-btn.doctor-btn{color:var(--ip)}
        .s-btn:hover{opacity:.9}
        .s-clear{padding:1rem 1.5rem;background:rgba(255,255,255,.2);color:white;border-radius:14px;font-weight:600;display:flex;align-items:center;gap:.5rem;border:1px solid rgba(255,255,255,.3);transition:.2s}
        .s-clear:hover{background:rgba(255,255,255,.3)}

        /* RESULTS GRID */
        .results-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1.5rem;margin-bottom:2rem}
        .rc{background:white;border-radius:22px;padding:1.5rem;box-shadow:var(--sh-md);border:1px solid var(--n3);transition:.2s;position:relative;overflow:hidden}
        .rc::before{content:'';position:absolute;top:0;left:0;width:5px;height:100%}
        .rc.patient-rc::before{background:linear-gradient(180deg,var(--m300),var(--ip))}
        .rc.doctor-rc::before{background:linear-gradient(180deg,var(--ip),var(--id))}
        .rc:hover{transform:translateY(-3px);box-shadow:var(--sh-xl);border-color:var(--m100)}
        .rc-av{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;margin-bottom:.8rem}
        .rc-av.p{background:linear-gradient(135deg,var(--m50),var(--il));color:var(--m300)}
        .rc-av.d{background:linear-gradient(135deg,var(--il),var(--m50));color:var(--ip)}
        .rc-name{font-size:1rem;font-weight:700;margin-bottom:.4rem}
        .rc-line{font-size:.83rem;color:var(--n5);display:flex;align-items:center;gap:.4rem;margin-bottom:.2rem}
        .rc-line i{color:var(--m300);width:14px;font-size:.78rem}
        .rc-tags{display:flex;gap:.4rem;margin-top:.7rem;flex-wrap:wrap}
        .tag{padding:.2rem .7rem;border-radius:40px;font-size:.75rem;font-weight:600}
        .tag-blood{background:var(--el);color:var(--ed)}
        .tag-gender{background:var(--il);color:var(--ip)}
        .tag-spec{background:var(--m50);color:var(--m300)}
        .tag-fee{background:var(--sl);color:var(--sd)}
        .tag-exp{background:var(--wl);color:var(--wd)}
        .view-btn{margin-top:.9rem;padding:.5rem 1.1rem;border-radius:40px;font-size:.82rem;font-weight:700;display:inline-flex;align-items:center;gap:.4rem;transition:.2s;color:white}
        .view-btn.p-btn{background:var(--m300)}
        .view-btn.p-btn:hover{background:var(--m400)}
        .view-btn.d-btn{background:var(--ip)}
        .view-btn.d-btn:hover{background:var(--id)}

        /* DETAIL PANEL */
        .detail-panel{background:white;border-radius:28px;box-shadow:var(--sh-xl);border:1px solid var(--n3);overflow:hidden;margin-bottom:2rem}
        .detail-hero{padding:2rem 2.5rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;color:white}
        .detail-hero.ph{background:linear-gradient(135deg,var(--m300),var(--m500))}
        .detail-hero.dh{background:linear-gradient(135deg,var(--ip),var(--id))}
        .d-av{width:80px;height:80px;border-radius:26px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:2.5rem;border:3px solid rgba(255,255,255,.3);flex-shrink:0}
        .d-info h2{font-size:1.7rem;font-weight:700;margin-bottom:.3rem}
        .d-info p{opacity:.85;font-size:.9rem;display:flex;align-items:center;gap:.5rem;margin-bottom:.2rem}
        .back-btn{margin-left:auto;padding:.6rem 1.3rem;background:rgba(255,255,255,.2);color:white;border-radius:40px;font-weight:600;font-size:.88rem;display:flex;align-items:center;gap:.4rem;border:1px solid rgba(255,255,255,.3);cursor:pointer;transition:.2s}
        .back-btn:hover{background:rgba(255,255,255,.3)}
        .detail-body{padding:2rem 2.5rem}

        /* INFO GRID */
        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1rem;margin-bottom:1.5rem}
        .ii{background:var(--n1);border-radius:14px;padding:1rem 1.2rem}
        .ii .lbl{font-size:.75rem;color:var(--n5);text-transform:uppercase;font-weight:600;margin-bottom:.3rem}
        .ii .val{font-weight:700;font-size:.92rem}

        /* SECTION TITLE */
        .sec{font-size:1rem;font-weight:700;color:var(--m300);margin:1.8rem 0 1rem;display:flex;align-items:center;gap:.6rem}
        .sec.blue{color:var(--ip)}
        .sec::after{content:'';flex:1;height:2px;background:var(--n2);border-radius:4px}

        /* MINI TABLE */
        .mt{width:100%;border-collapse:collapse;font-size:.87rem}
        .mt th{background:var(--n1);padding:.7rem 1rem;text-align:left;font-weight:600;color:var(--n6);font-size:.78rem}
        .mt th:first-child{border-radius:10px 0 0 0}
        .mt th:last-child{border-radius:0 10px 0 0}
        .mt td{padding:.7rem 1rem;border-bottom:1px solid var(--n2);color:var(--n7);vertical-align:middle}
        .mt tr:last-child td{border-bottom:none}
        .mt tr:hover td{background:var(--n1)}

        .badge{padding:.25rem .8rem;border-radius:100px;font-size:.75rem;font-weight:600;display:inline-block}
        .b-requested{background:var(--wl);color:var(--wd)}
        .b-confirmed{background:var(--il);color:var(--ip)}
        .b-completed{background:var(--sl);color:var(--sd)}
        .b-cancelled{background:var(--el);color:var(--ed)}
        .b-paid{background:var(--sl);color:var(--sd)}
        .b-unpaid{background:var(--wl);color:var(--wd)}
        .b-partial{background:var(--il);color:var(--ip)}

        .day-pill{padding:.3rem .8rem;border-radius:40px;font-size:.8rem;font-weight:600;background:var(--il);color:var(--ip)}
        .schedule-days{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}

        .note-box{background:var(--n1);border-radius:14px;padding:1.1rem 1.3rem;margin-bottom:.8rem}
        .note-box h4{font-size:.78rem;color:var(--n5);font-weight:600;text-transform:uppercase;margin-bottom:.4rem}
        .note-box p{font-size:.9rem;color:var(--n7)}

        .empty{text-align:center;padding:2rem}
        .empty i{font-size:2.5rem;color:var(--n4);display:block;margin-bottom:.6rem}
        .empty p{color:var(--n5);font-size:.9rem}

        .qa-row{display:flex;gap:.8rem;flex-wrap:wrap;margin-top:.5rem}

        footer{background:var(--n7);color:var(--n2);padding:2rem;text-align:center;border-top:5px solid var(--m300);margin-top:auto}
        .footer-content{max-width:1400px;margin:0 auto}
        .footer-copy{color:var(--n5);font-size:.85rem;margin-top:.5rem}

        @media(max-width:768px){
            nav{flex-direction:column;gap:.8rem;padding:1rem}
            .container{padding:1rem}
            .detail-hero{flex-direction:column}
            .back-btn{margin-left:0}
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
    <div class="nav-tabs">
        <a href="receptionist-dashboard.php" class="ntab"><i class="fas fa-home"></i> Dashboard</a>
        <a href="manage-appointments.php"    class="ntab"><i class="fas fa-calendar-alt"></i> Appointments</a>
        <a href="records-receptionist.php"   class="ntab active"><i class="fas fa-folder-open"></i> Records</a>
        <a href="billing-receptionist.php"   class="ntab"><i class="fas fa-file-invoice-dollar"></i> Billing</a>
        <a href="register-patient.php"       class="ntab"><i class="fas fa-user-plus"></i> Register Patient</a>
    </div>

    <div class="container">
        <div class="page-header"><h1>Patient & Doctor Records</h1></div>

        <?php if (!$view_patient && !$view_doctor): ?>

        <!-- INNER TABS -->
        <div class="inner-tabs">
            <a href="?tab=patients" class="itab <?php echo $tab==='patients'?'active':''; ?>"><i class="fas fa-user-injured"></i> Patients</a>
            <a href="?tab=doctors"  class="itab <?php echo $tab==='doctors' ?'active':''; ?>"><i class="fas fa-user-md"></i> Doctors</a>
        </div>

        <?php if ($tab === 'patients'): ?>

        <div class="search-hero patient-hero">
            <h2><i class="fas fa-search"></i> Search Patient Files</h2>
            <p>Search by name, email address, or phone number</p>
            <form method="GET" class="search-box">
                <input type="hidden" name="tab" value="patients">
                <input type="text" name="search" class="s-input" placeholder="Patient name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>" autofocus>
                <button type="submit" class="s-btn patient-btn"><i class="fas fa-search"></i> Search</button>
                <?php if ($search): ?><a href="?tab=patients" class="s-clear"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
            </form>
        </div>

        <p style="color:var(--n5);margin-bottom:1.2rem;font-size:.88rem">
            <?php echo $search ? count($patients).' result'.(count($patients)!==1?'s':'').' for "<strong>'.htmlspecialchars($search).'</strong>"' : 'Showing 20 most recently registered patients'; ?>
        </p>

        <?php if (count($patients) > 0): ?>
        <div class="results-grid">
            <?php foreach ($patients as $p): ?>
            <div class="rc patient-rc">
                <div class="rc-av p"><i class="fas fa-user-injured"></i></div>
                <div class="rc-name"><?php echo htmlspecialchars($p['name']); ?></div>
                <div class="rc-line"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($p['email']); ?></div>
                <div class="rc-line"><i class="fas fa-phone"></i><?php echo htmlspecialchars($p['phone'] ?? 'N/A'); ?></div>
                <?php if (!empty($p['date_of_birth'])): ?>
                <div class="rc-line"><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($p['date_of_birth'])); ?></div>
                <?php endif; ?>
                <div class="rc-tags">
                    <?php if (!empty($p['blood_group'])): ?><span class="tag tag-blood"><i class="fas fa-tint"></i> <?php echo $p['blood_group']; ?></span><?php endif; ?>
                    <?php if (!empty($p['gender'])): ?><span class="tag tag-gender"><?php echo $p['gender']; ?></span><?php endif; ?>
                </div>
                <a href="?tab=patients&patient=<?php echo $p['id']; ?><?php echo $search?'&search='.urlencode($search):''; ?>" class="view-btn p-btn">
                    <i class="fas fa-folder-open"></i> View Full File
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty"><i class="fas fa-user-slash"></i><p>No patients found<?php echo $search?' for "'.$search.'"':''; ?>.</p></div>
        <?php endif; ?>

        <?php else: // DOCTORS ?>

        <div class="search-hero doctor-hero">
            <h2><i class="fas fa-search"></i> Search Doctor Profiles</h2>
            <p>Search by name, specialization, or qualification</p>
            <form method="GET" class="search-box">
                <input type="hidden" name="tab" value="doctors">
                <input type="text" name="search" class="s-input" placeholder="Doctor name, specialization, qualification..." value="<?php echo htmlspecialchars($search); ?>" autofocus>
                <button type="submit" class="s-btn doctor-btn"><i class="fas fa-search"></i> Search</button>
                <?php if ($search): ?><a href="?tab=doctors" class="s-clear"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
            </form>
        </div>

        <?php if (count($doctors) > 0): ?>
        <div class="results-grid">
            <?php foreach ($doctors as $d): ?>
            <div class="rc doctor-rc">
                <div class="rc-av d"><i class="fas fa-user-md"></i></div>
                <div class="rc-name">Dr. <?php echo htmlspecialchars($d['name']); ?></div>
                <div class="rc-line"><i class="fas fa-stethoscope"></i><?php echo htmlspecialchars($d['specialization']); ?></div>
                <div class="rc-line"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($d['email']); ?></div>
                <div class="rc-line"><i class="fas fa-phone"></i><?php echo htmlspecialchars($d['phone'] ?? 'N/A'); ?></div>
                <?php if (!empty($d['qualification'])): ?>
                <div class="rc-line"><i class="fas fa-graduation-cap"></i><?php echo htmlspecialchars($d['qualification']); ?></div>
                <?php endif; ?>
                <div class="rc-tags">
                    <span class="tag tag-spec"><?php echo htmlspecialchars($d['specialization']); ?></span>
                    <span class="tag tag-fee">BD <?php echo number_format($d['consultation_fee'],2); ?></span>
                    <?php if ($d['experience_years']): ?><span class="tag tag-exp"><?php echo $d['experience_years']; ?> yrs</span><?php endif; ?>
                </div>
                <a href="?tab=doctors&doctor=<?php echo $d['id']; ?><?php echo $search?'&search='.urlencode($search):''; ?>" class="view-btn d-btn">
                    <i class="fas fa-folder-open"></i> View Profile
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty"><i class="fas fa-user-md"></i><p>No doctors found.</p></div>
        <?php endif; ?>

        <?php endif; // tab end ?>

        <?php elseif ($view_patient && $patient_detail): ?>
        <!-- ══════════ PATIENT DETAIL ══════════ -->
        <div class="detail-panel">
            <div class="detail-hero ph">
                <div class="d-av"><i class="fas fa-user-injured"></i></div>
                <div class="d-info">
                    <h2><?php echo htmlspecialchars($patient_detail['name']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient_detail['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient_detail['phone'] ?? 'N/A'); ?></p>
                    <p><i class="fas fa-calendar-plus"></i> Registered <?php echo date('M d, Y', strtotime($patient_detail['registered_at'])); ?></p>
                </div>
                <a href="?tab=patients<?php echo $search?'&search='.urlencode($search):''; ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
            </div>

            <div class="detail-body">
                <div class="info-grid">
                    <div class="ii"><div class="lbl">Date of Birth</div><div class="val"><?php echo !empty($patient_detail['date_of_birth'])?date('M d, Y',strtotime($patient_detail['date_of_birth'])):'N/A'; ?></div></div>
                    <div class="ii"><div class="lbl">Gender</div><div class="val"><?php echo htmlspecialchars($patient_detail['gender']??'N/A'); ?></div></div>
                    <div class="ii"><div class="lbl">Blood Group</div><div class="val" style="color:var(--ed)"><?php echo htmlspecialchars($patient_detail['blood_group']??'N/A'); ?></div></div>
                    <div class="ii"><div class="lbl">Address</div><div class="val"><?php echo htmlspecialchars($patient_detail['address']??'N/A'); ?></div></div>
                    <div class="ii"><div class="lbl">Emergency Contact</div><div class="val"><?php echo htmlspecialchars($patient_detail['emergency_contact_name']??'N/A'); ?><?php if(!empty($patient_detail['emergency_contact_phone'])): ?> · <?php echo htmlspecialchars($patient_detail['emergency_contact_phone']); ?><?php endif; ?></div></div>
                    <div class="ii"><div class="lbl">Total Appointments</div><div class="val"><?php echo count($patient_appointments); ?></div></div>
                </div>

                <?php if (!empty($patient_detail['allergies'])): ?>
                <div class="note-box"><h4><i class="fas fa-allergies"></i> Allergies</h4><p><?php echo htmlspecialchars($patient_detail['allergies']); ?></p></div>
                <?php endif; ?>

                <!-- APPOINTMENTS -->
                <div class="sec"><i class="fas fa-calendar-alt"></i> Appointment History</div>
                <?php if (count($patient_appointments) > 0): ?>
                <div style="overflow-x:auto"><table class="mt">
                    <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Specialization</th><th>Fee</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($patient_appointments as $a): ?>
                    <tr>
                        <td><?php echo date('M d, Y',strtotime($a['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A',strtotime($a['appointment_time'])); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($a['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($a['specialization']); ?></td>
                        <td>BD <?php echo number_format($a['consultation_fee'],2); ?></td>
                        <td><span class="badge b-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php else: ?><div class="empty"><i class="fas fa-calendar-times"></i><p>No appointments yet.</p></div><?php endif; ?>

                <!-- INVOICES -->
                <div class="sec"><i class="fas fa-file-invoice-dollar"></i> Billing History</div>
                <?php if (count($patient_invoices) > 0): ?>
                <div style="overflow-x:auto"><table class="mt">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Doctor</th><th>Total</th><th>Paid</th><th>Status</th><th>Method</th></tr></thead>
                    <tbody>
                    <?php foreach ($patient_invoices as $inv): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($inv['invoice_number']??'#'.$inv['id']); ?></td>
                        <td><?php echo date('M d, Y',strtotime($inv['appointment_date'])); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($inv['doctor_name']); ?></td>
                        <td>BD <?php echo number_format($inv['total_amount'],2); ?></td>
                        <td>BD <?php echo number_format($inv['paid_amount'],2); ?></td>
                        <td><span class="badge b-<?php echo strtolower($inv['status']); ?>"><?php echo $inv['status']; ?></span></td>
                        <td><?php echo htmlspecialchars($inv['payment_method']??'—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php else: ?><div class="empty"><i class="fas fa-file-invoice"></i><p>No invoices yet.</p></div><?php endif; ?>

                <!-- PRESCRIPTIONS -->
                <?php if (count($patient_prescriptions) > 0): ?>
                <div class="sec"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</div>
                <div style="overflow-x:auto"><table class="mt">
                    <thead><tr><th>Date</th><th>Doctor</th><th>Diagnosis</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($patient_prescriptions as $pr): ?>
                    <tr>
                        <td><?php echo date('M d, Y',strtotime($pr['issued_date'])); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($pr['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($pr['diagnosis']??'—'); ?></td>
                        <td><?php echo htmlspecialchars($pr['notes']??'—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>

                <!-- MEDICAL RECORDS -->
                <?php if (count($patient_records) > 0): ?>
                <div class="sec"><i class="fas fa-file-medical"></i> Medical Records</div>
                <div style="overflow-x:auto"><table class="mt">
                    <thead><tr><th>Date</th><th>Type</th><th>Doctor</th><th>Description</th></tr></thead>
                    <tbody>
                    <?php foreach ($patient_records as $rec): ?>
                    <tr>
                        <td><?php echo date('M d, Y',strtotime($rec['record_date'])); ?></td>
                        <td><?php echo htmlspecialchars($rec['record_type']??'General'); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($rec['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($rec['description']??'—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>

                <div class="sec"><i class="fas fa-bolt"></i> Quick Actions</div>
                <div class="qa-row">
                    <a href="manage-appointments.php?action=book" class="view-btn p-btn"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
                    <a href="billing-receptionist.php" class="view-btn d-btn"><i class="fas fa-file-invoice-dollar"></i> Billing</a>
                </div>
            </div>
        </div>

        <?php elseif ($view_doctor && $doctor_detail): ?>
        <!-- ══════════ DOCTOR DETAIL ══════════ -->
        <div class="detail-panel">
            <div class="detail-hero dh">
                <div class="d-av"><i class="fas fa-user-md"></i></div>
                <div class="d-info">
                    <h2>Dr. <?php echo htmlspecialchars($doctor_detail['name']); ?></h2>
                    <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor_detail['specialization']); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor_detail['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor_detail['phone']??'N/A'); ?></p>
                </div>
                <a href="?tab=doctors<?php echo $search?'&search='.urlencode($search):''; ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
            </div>

            <div class="detail-body">
                <div class="info-grid">
                    <div class="ii"><div class="lbl">Specialization</div><div class="val"><?php echo htmlspecialchars($doctor_detail['specialization']); ?></div></div>
                    <div class="ii"><div class="lbl">Qualification</div><div class="val"><?php echo htmlspecialchars($doctor_detail['qualification']??'N/A'); ?></div></div>
                    <div class="ii"><div class="lbl">Experience</div><div class="val"><?php echo ($doctor_detail['experience_years']??0); ?> years</div></div>
                    <div class="ii"><div class="lbl">Consultation Fee</div><div class="val" style="color:var(--sd)">BD <?php echo number_format($doctor_detail['consultation_fee'],2); ?></div></div>
                    <div class="ii"><div class="lbl">Available Hours</div><div class="val"><?php echo date('h:i A',strtotime($doctor_detail['available_from'])); ?> – <?php echo date('h:i A',strtotime($doctor_detail['available_to'])); ?></div></div>
                    <div class="ii"><div class="lbl">Total Appointments</div><div class="val"><?php echo count($doctor_appointments); ?></div></div>
                </div>

                <?php if (!empty($doctor_detail['available_days'])): ?>
                <div class="sec blue"><i class="fas fa-calendar-week"></i> Available Days</div>
                <div class="schedule-days">
                    <?php foreach (explode(',', $doctor_detail['available_days']) as $day): ?>
                    <span class="day-pill"><?php echo trim($day); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($doctor_detail['bio'])): ?>
                <div class="note-box"><h4><i class="fas fa-info-circle"></i> About</h4><p><?php echo htmlspecialchars($doctor_detail['bio']); ?></p></div>
                <?php endif; ?>

                <div class="sec blue"><i class="fas fa-calendar-check"></i> Upcoming Schedule</div>
                <?php if (count($doctor_schedule) > 0): ?>
                <div style="overflow-x:auto"><table class="mt">
                    <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($doctor_schedule as $a): ?>
                    <tr>
                        <td><?php echo date('M d, Y',strtotime($a['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A',strtotime($a['appointment_time'])); ?></td>
                        <td><?php echo htmlspecialchars($a['patient_name']); ?></td>
                        <td><span class="badge b-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php else: ?><div class="empty"><i class="fas fa-calendar"></i><p>No upcoming appointments.</p></div><?php endif; ?>

                <div class="sec blue"><i class="fas fa-history"></i> Recent Appointments</div>
                <?php if (count($doctor_appointments) > 0): ?>
                <div style="overflow-x:auto"><table class="mt">
                    <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($doctor_appointments as $a): ?>
                    <tr>
                        <td><?php echo date('M d, Y',strtotime($a['appointment_date'])); ?></td>
                        <td><?php echo date('h:i A',strtotime($a['appointment_time'])); ?></td>
                        <td><?php echo htmlspecialchars($a['patient_name']); ?></td>
                        <td><span class="badge b-<?php echo strtolower($a['status']); ?>"><?php echo $a['status']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php else: ?><div class="empty"><i class="fas fa-calendar-times"></i><p>No appointments yet.</p></div><?php endif; ?>

                <div class="sec blue"><i class="fas fa-bolt"></i> Quick Actions</div>
                <div class="qa-row">
                    <a href="manage-appointments.php?action=book" class="view-btn d-btn">
                        <i class="fas fa-calendar-plus"></i> Book with Dr. <?php echo htmlspecialchars(explode(' ',$doctor_detail['name'])[0]); ?>
                    </a>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="empty"><i class="fas fa-exclamation-circle"></i><p>Record not found.</p></div>
        <?php endif; ?>

    </div>
</div>

<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem;font-weight:600">Valora Medical Center</p>
        <p style="color:var(--n4);font-size:.9rem">This is a university project for educational purposes; all hospital information & services is  fictional.</p>
        <p class="footer-copy">&copy; 2026</p>
    </div>
</footer>
</body>
</html>