<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id   = $_SESSION['user_id'];
$doc_row   = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id")->fetch_assoc();
$doctor_id = $doc_row['id'] ?? null;

$success = '';
$error   = '';

// ── Handle saving appointment notes / suggestions ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notes'])) {
    $apt_id = intval($_POST['apt_id']);
    $notes  = sanitize($_POST['notes'] ?? '');

    $stmt = $conn->prepare(
        "UPDATE appointments SET notes = ? WHERE id = ? AND doctor_id = ?"
    );
    $stmt->bind_param("sii", $notes, $apt_id, $doctor_id);
    if ($stmt->execute()) {
        $success = 'Notes saved successfully.';
    } else {
        $error = 'Failed to save notes.';
    }
    $stmt->close();
}

// ── Fetch patient list ────────────────────────────────────────────────────────
$patients = [];
if ($doctor_id) {
    $res = $conn->query(
        "SELECT DISTINCT p.id, u.name, u.phone, u.email,
                p.date_of_birth AS dob, p.gender, p.blood_group,
                p.allergies, p.address, p.emergency_contact_name, p.emergency_contact_phone,
                (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND doctor_id = $doctor_id) AS total_visits,
                (SELECT MAX(appointment_date) FROM appointments WHERE patient_id = p.id AND doctor_id = $doctor_id) AS last_visit
         FROM patients p
         JOIN users u ON p.user_id = u.id
         JOIN appointments a ON a.patient_id = p.id
         WHERE a.doctor_id = $doctor_id
         ORDER BY last_visit DESC"
    );
    while ($row = $res->fetch_assoc()) $patients[] = $row;
}

// ── Fetch detail data for selected patient ────────────────────────────────────
$view_id       = intval($_GET['patient'] ?? 0);
$detail        = null;
$apt_history   = [];
$prescriptions = [];

if ($view_id && $doctor_id) {
    $detail = $conn->query(
        "SELECT p.*, u.name, u.email, u.phone
         FROM patients p JOIN users u ON p.user_id = u.id
         WHERE p.id = $view_id"
    )->fetch_assoc();

    if ($detail) {
        $r = $conn->query(
            "SELECT a.* FROM appointments a
             WHERE a.patient_id = $view_id AND a.doctor_id = $doctor_id
             ORDER BY a.appointment_date DESC"
        );
        while ($row = $r->fetch_assoc()) $apt_history[] = $row;

        $r = $conn->query(
            "SELECT p.*, GROUP_CONCAT(
                CONCAT(pi.medicine_name,'|',COALESCE(pi.dosage,''),'|',COALESCE(pi.frequency,''),'|',COALESCE(pi.duration,''),'|',COALESCE(pi.instructions,''))
                ORDER BY pi.id SEPARATOR ';;'
             ) AS items_raw
             FROM prescriptions p
             LEFT JOIN prescription_items pi ON pi.prescription_id = p.id
             WHERE p.patient_id = $view_id AND p.doctor_id = $doctor_id
             GROUP BY p.id
             ORDER BY p.issued_date DESC"
        );
        while ($row = $r->fetch_assoc()) $prescriptions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records · Valora</title>
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
            --sh-md:0 8px 16px rgba(0,0,0,.04);
            --sh-lg:0 16px 24px rgba(0,0,0,.04);
            --sh-xl:0 24px 32px rgba(100,23,50,.08);
        }
        html,body{height:100%}
        body{background:var(--bg);color:var(--n8);min-height:100vh;display:flex;flex-direction:column;line-height:1.5}
        a{text-decoration:none}

        /* HEADER */
        header{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);box-shadow:var(--sh-md);position:sticky;top:0;z-index:100;border-bottom:1px solid rgba(218,218,218,.3)}
        nav{display:flex;justify-content:space-between;align-items:center;padding:1rem 2rem;max-width:1400px;margin:0 auto}
        .logo{font-size:1.8rem;font-weight:800;color:var(--m300);letter-spacing:-.5px;position:relative}
        .logo::after{content:'';position:absolute;bottom:-4px;left:0;width:40%;height:3px;background:var(--m300);border-radius:4px}
        .nav-right{display:flex;align-items:center;gap:1rem}
        .user-pill{display:flex;align-items:center;gap:.8rem;padding:.5rem 1.2rem;background:white;border-radius:100px;border:1px solid var(--n3);color:var(--n8);font-weight:600}
        .user-pill i{color:var(--m300)}
        .logout-btn{padding:.5rem 1.2rem;background:var(--n2);border-radius:100px;font-weight:600;color:var(--n7);font-size:.9rem}
        .logout-btn:hover{background:var(--n3)}

        /* TABS */
        .main-tabs{display:flex;justify-content:center;gap:.5rem;margin:1.5rem auto;padding:.5rem;background:white;border-radius:100px;box-shadow:var(--sh-lg);max-width:960px;border:1px solid var(--n3);flex-wrap:wrap}
        .tab{padding:.7rem 1.4rem;border-radius:100px;font-weight:600;color:var(--n7);transition:.25s;display:flex;align-items:center;gap:.45rem;font-size:.92rem;white-space:nowrap}
        .tab i{color:var(--m300);font-size:.95rem;transition:color .25s}
        .tab:hover{background:var(--m50);color:var(--m300)}
        .tab.active{background:var(--m300);color:white;box-shadow:0 8px 16px -4px rgba(132,38,70,.4)}
        .tab.active i{color:white}

        /* PAGE LAYOUT */
        .page-wrapper{flex:1;display:flex;flex-direction:column}
        .container{max-width:1300px;margin:1rem auto 3rem;padding:0 2rem;flex:1;width:100%}
        .page-header{margin-bottom:1.5rem}
        .page-header h1{font-size:2.2rem;font-weight:700;position:relative;padding-bottom:.8rem}
        .page-header h1::after{content:'';position:absolute;bottom:0;left:0;width:80px;height:4px;background:linear-gradient(90deg,var(--m300),var(--ip));border-radius:4px}

        /* ALERTS */
        .alert{padding:1rem 1.5rem;border-radius:14px;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:var(--sl);color:var(--sd);border-left:6px solid var(--sp)}
        .alert-error{background:var(--el);color:var(--ed);border-left:6px solid var(--ep)}

        /* SPLIT LAYOUT */
        .split{display:grid;grid-template-columns:380px 1fr;gap:2rem;align-items:start}

        /* LEFT — PATIENT LIST */
        .patient-list-panel{position:sticky;top:100px}
        .search-wrap{position:relative;margin-bottom:1rem}
        .search-wrap i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--m300)}
        .search-input{width:100%;padding:.9rem 1rem .9rem 2.8rem;border:2px solid var(--n3);border-radius:14px;font-size:.95rem;background:white;font-family:inherit;transition:.2s}
        .search-input:focus{outline:none;border-color:var(--m300)}

        .patient-scroll{max-height:calc(100vh - 240px);overflow-y:auto;display:flex;flex-direction:column;gap:.7rem;padding-right:4px}
        .patient-scroll::-webkit-scrollbar{width:4px}
        .patient-scroll::-webkit-scrollbar-thumb{background:var(--n3);border-radius:4px}

        .pc{background:white;border-radius:18px;padding:1.2rem 1.4rem;border:2px solid var(--n3);cursor:pointer;transition:.2s;display:flex;align-items:center;gap:1rem}
        .pc:hover{border-color:var(--m200);transform:translateY(-2px);box-shadow:var(--sh-md)}
        .pc.selected{border-color:var(--m300);background:var(--m50);box-shadow:0 4px 12px rgba(132,38,70,.15)}
        .pc-av{width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,var(--m50),var(--il));display:flex;align-items:center;justify-content:center;color:var(--m300);font-size:1.3rem;flex-shrink:0}
        .pc-name{font-weight:700;font-size:.95rem;margin-bottom:.2rem}
        .pc-meta{font-size:.8rem;color:var(--n5);display:flex;align-items:center;gap:.4rem}
        .pc-badge{padding:.15rem .6rem;border-radius:40px;font-size:.72rem;font-weight:600;background:var(--m50);color:var(--m300);margin-left:auto;flex-shrink:0}

        /* RIGHT — DETAIL PANEL */
        .detail-panel{background:white;border-radius:28px;box-shadow:var(--sh-xl);border:1px solid var(--n3);overflow:hidden}

        .empty-detail{text-align:center;padding:5rem 2rem;background:white;border-radius:28px;border:2px dashed var(--n4)}
        .empty-detail i{font-size:4rem;color:var(--n4);display:block;margin-bottom:1rem}
        .empty-detail h3{color:var(--n6);margin-bottom:.5rem}
        .empty-detail p{color:var(--n5);font-size:.9rem}

        /* PATIENT HERO */
        .pt-hero{background:linear-gradient(135deg,var(--m300),var(--m500));color:white;padding:2rem 2.5rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap}
        .pt-av{width:80px;height:80px;border-radius:26px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:2.5rem;border:3px solid rgba(255,255,255,.3);flex-shrink:0}
        .pt-info h2{font-size:1.7rem;font-weight:700;margin-bottom:.3rem}
        .pt-info p{opacity:.85;font-size:.9rem;display:flex;align-items:center;gap:.5rem;margin-bottom:.2rem}
        .blood-pill{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);padding:.3rem .9rem;border-radius:40px;font-weight:700;font-size:.88rem;margin-top:.5rem;display:inline-block}

        /* DETAIL BODY */
        .pt-body{padding:2rem 2.5rem}

        /* INFO GRID */
        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem}
        .ii{background:var(--n1);border-radius:14px;padding:.9rem 1.1rem}
        .ii .lbl{font-size:.72rem;color:var(--n5);text-transform:uppercase;font-weight:600;margin-bottom:.3rem}
        .ii .val{font-weight:700;font-size:.92rem}

        /* SECTION */
        .sec{font-size:1rem;font-weight:700;color:var(--m300);margin:2rem 0 1rem;display:flex;align-items:center;gap:.6rem}
        .sec::after{content:'';flex:1;height:2px;background:var(--n2);border-radius:4px}

        /* APPOINTMENT CARDS */
        .apt-item{background:var(--n1);border-radius:16px;padding:1.2rem 1.4rem;margin-bottom:.8rem;border-left:4px solid var(--n3)}
        .apt-item.b-requested{border-left-color:var(--wp)}
        .apt-item.b-confirmed{border-left-color:var(--ip)}
        .apt-item.b-completed{border-left-color:var(--sp)}
        .apt-item.b-cancelled{border-left-color:var(--ep)}
        .apt-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem;flex-wrap:wrap;gap:.5rem}
        .apt-date{font-weight:700;font-size:.92rem}
        .badge{padding:.25rem .8rem;border-radius:100px;font-size:.75rem;font-weight:600;display:inline-block}
        .b-requested{background:var(--wl);color:var(--wd)}
        .b-confirmed{background:var(--il);color:var(--ip)}
        .b-completed{background:var(--sl);color:var(--sd)}
        .b-cancelled{background:var(--el);color:var(--ed)}
        .apt-reason{font-size:.85rem;color:var(--n6);margin-bottom:.6rem}
        .notes-display{background:white;border-radius:10px;padding:.8rem 1rem;font-size:.85rem;color:var(--n7);border-left:3px solid var(--m300);margin-bottom:.6rem}
        .notes-display.empty-notes{color:var(--n4);font-style:italic;border-left-color:var(--n3)}

        /* NOTES FORM */
        .notes-form{margin-top:.6rem}
        .notes-toggle{font-size:.82rem;font-weight:600;color:var(--ip);cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;background:none;border:none;font-family:inherit;padding:0;transition:.2s}
        .notes-toggle:hover{color:var(--m300)}
        .notes-edit{display:none;margin-top:.8rem}
        .notes-edit.open{display:block}
        .ntarea{width:100%;padding:.8rem 1rem;border:2px solid var(--n3);border-radius:12px;font-size:.88rem;resize:vertical;min-height:80px;font-family:inherit;transition:.2s}
        .ntarea:focus{outline:none;border-color:var(--m300)}
        .btn-save-notes{margin-top:.5rem;padding:.55rem 1.3rem;background:var(--m300);color:white;border:none;border-radius:40px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;transition:.2s;display:inline-flex;align-items:center;gap:.4rem}
        .btn-save-notes:hover{background:var(--m400)}
        .btn-cancel-notes{margin-top:.5rem;margin-left:.5rem;padding:.55rem 1.1rem;background:var(--n2);color:var(--n7);border:none;border-radius:40px;font-size:.82rem;font-weight:600;cursor:pointer;font-family:inherit;transition:.2s}

        /* PRESCRIPTIONS */
        .presc-item{background:var(--n1);border-radius:16px;padding:1.2rem 1.4rem;margin-bottom:.8rem}
        .presc-header{font-weight:700;margin-bottom:.6rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem}
        .presc-date{font-size:.82rem;color:var(--n5)}
        .med-list{display:flex;flex-direction:column;gap:.4rem}
        .med-item{background:white;border-radius:10px;padding:.6rem 1rem;font-size:.85rem;display:flex;align-items:flex-start;gap:.6rem}
        .med-item i{color:var(--m300);margin-top:.15rem;flex-shrink:0}
        .med-name{font-weight:700}
        .med-detail{color:var(--n6);font-size:.8rem;margin-top:.1rem}
        .presc-diag{background:white;border-radius:10px;padding:.6rem 1rem;font-size:.85rem;color:var(--n7);margin-bottom:.6rem;border-left:3px solid var(--ip)}

        /* EMPTY */
        .mini-empty{text-align:center;padding:1.5rem;color:var(--n5);font-size:.88rem}
        .mini-empty i{display:block;font-size:2rem;color:var(--n4);margin-bottom:.5rem}

        footer{background:var(--n7);color:var(--n2);padding:2rem;text-align:center;border-top:5px solid var(--m300);margin-top:auto}
        .footer-content{max-width:1400px;margin:0 auto}
        .footer-copy{color:var(--n5);font-size:.85rem;margin-top:.5rem}

        @media(max-width:960px){
            .split{grid-template-columns:1fr}
            .patient-list-panel{position:static}
            .patient-scroll{max-height:300px}
        }
        @media(max-width:768px){
            nav{flex-direction:column;gap:.8rem;padding:1rem}
            .container{padding:0 1rem}
            .pt-hero{flex-direction:column}
            .pt-body{padding:1.5rem}
            .form-row{grid-template-columns:1fr}
        }
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
        <a href="appointments.php"  class="tab"><i class="fas fa-calendar-alt"></i> Appointments</a>
        <a href="patients.php"      class="tab active"><i class="fas fa-users"></i> Patients</a>
        <a href="prescriptions.php" class="tab"><i class="fas fa-prescription"></i> Prescriptions</a>
        <a href="availability.php"  class="tab"><i class="fas fa-calendar-times"></i> Schedule</a>
        <a href="profile.php"       class="tab"><i class="fas fa-user-md"></i> Profile</a>
    </div>

    <div class="container">
        <div class="page-header"><h1>Patient Records</h1></div>

        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="split">

            <!-- ── LEFT: PATIENT LIST ── -->
            <div class="patient-list-panel">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" placeholder="Search patients..."
                           id="searchInput" onkeyup="filterPatients(this.value)">
                </div>

                <div class="patient-scroll" id="patientScroll">
                    <?php if (count($patients) > 0): ?>
                        <?php foreach ($patients as $p): ?>
                        <a href="?patient=<?php echo $p['id']; ?>"
                           class="pc <?php echo ($view_id === $p['id']) ? 'selected' : ''; ?>"
                           data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>">
                            <div class="pc-av"><i class="fas fa-user"></i></div>
                            <div style="flex:1;min-width:0">
                                <div class="pc-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div class="pc-meta">
                                    <i class="fas fa-calendar-check"></i>
                                    <?php echo $p['last_visit'] ? date('M d, Y', strtotime($p['last_visit'])) : 'No visits'; ?>
                                </div>
                            </div>
                            <div class="pc-badge"><?php echo $p['total_visits']; ?> visits</div>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="mini-empty"><i class="fas fa-users"></i>No patients yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── RIGHT: DETAIL PANEL ── -->
            <div>
            <?php if ($detail): ?>
            <div class="detail-panel">

                <!-- HERO -->
                <div class="pt-hero">
                    <div class="pt-av"><i class="fas fa-user-injured"></i></div>
                    <div class="pt-info" style="flex:1">
                        <h2><?php echo htmlspecialchars($detail['name']); ?></h2>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($detail['email']); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($detail['phone'] ?? 'N/A'); ?></p>
                        <?php if (!empty($detail['blood_group'])): ?>
                        <span class="blood-pill"><i class="fas fa-tint"></i> <?php echo $detail['blood_group']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.6rem;position:relative;z-index:1">
                        <a href="prescriptions.php?patient_id=<?php echo $detail['id']; ?>" class="btn-prescribe">
                            <i class="fas fa-prescription"></i> Write Prescription
                        </a>
                        <a href="patients.php" style="padding:.6rem 1.1rem;background:rgba(255,255,255,.2);color:white;border-radius:40px;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem;border:1px solid rgba(255,255,255,.3)">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <div class="pt-body">

                    <!-- ALLERGIES WARNING -->
                    <?php if (!empty($detail['allergies'])): ?>
                    <div class="allergy-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Allergies:</strong>&nbsp;<?php echo htmlspecialchars($detail['allergies']); ?>
                    </div>
                    <?php endif; ?>

                    <!-- PERSONAL INFO -->
                    <div class="info-grid">
                        <div class="ii">
                            <div class="lbl">Date of Birth</div>
                            <div class="val"><?php echo !empty($detail['date_of_birth']) ? date('M d, Y', strtotime($detail['date_of_birth'])) : 'N/A'; ?></div>
                        </div>
                        <div class="ii">
                            <div class="lbl">Age</div>
                            <div class="val"><?php echo !empty($detail['date_of_birth']) ? date_diff(date_create($detail['date_of_birth']), date_create('today'))->y . ' years' : 'N/A'; ?></div>
                        </div>
                        <div class="ii">
                            <div class="lbl">Gender</div>
                            <div class="val"><?php echo htmlspecialchars($detail['gender'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="ii">
                            <div class="lbl">Blood Group</div>
                            <div class="val" style="color:var(--ed)"><?php echo htmlspecialchars($detail['blood_group'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="ii">
                            <div class="lbl">Emergency Contact</div>
                            <div class="val"><?php echo htmlspecialchars($detail['emergency_contact_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="ii">
                            <div class="lbl">Total Visits</div>
                            <div class="val"><?php echo count($apt_history); ?></div>
                        </div>
                    </div>

                    <!-- APPOINTMENT HISTORY WITH NOTES -->
                    <div class="sec"><i class="fas fa-calendar-alt"></i> Appointment History & Notes</div>

                    <?php if (count($apt_history) > 0): ?>
                        <?php foreach ($apt_history as $apt): ?>
                        <div class="apt-item b-<?php echo strtolower($apt['status']); ?>">
                            <div class="apt-top">
                                <span class="apt-date">
                                    <i class="fas fa-calendar" style="color:var(--m300);margin-right:.4rem"></i>
                                    <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?>
                                    &nbsp;·&nbsp;
                                    <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?>
                                </span>
                                <span class="badge b-<?php echo strtolower($apt['status']); ?>"><?php echo $apt['status']; ?></span>
                            </div>

                            <?php if (!empty($apt['reason'])): ?>
                            <div class="apt-reason"><i class="fas fa-comment-medical" style="color:var(--m300)"></i> <?php echo htmlspecialchars($apt['reason']); ?></div>
                            <?php endif; ?>

                            <!-- Current notes display -->
                            <div class="notes-display <?php echo empty($apt['notes']) ? 'empty-notes' : ''; ?>" id="notes-display-<?php echo $apt['id']; ?>">
                                <?php if (!empty($apt['notes'])): ?>
                                <i class="fas fa-stethoscope" style="color:var(--m300);margin-right:.4rem"></i>
                                <strong>Dr's Notes:</strong> <?php echo htmlspecialchars($apt['notes']); ?>
                                <?php else: ?>
                                <i class="fas fa-pen" style="margin-right:.4rem"></i> No notes written yet
                                <?php endif; ?>
                            </div>

                            <!-- Notes edit toggle -->
                            <div class="notes-form">
                                <button class="notes-toggle" onclick="toggleNotes(<?php echo $apt['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                    <?php echo empty($apt['notes']) ? 'Add Notes / Suggestion' : 'Edit Notes'; ?>
                                </button>

                                <div class="notes-edit" id="notes-edit-<?php echo $apt['id']; ?>">
                                    <form method="POST">
                                        <input type="hidden" name="apt_id" value="<?php echo $apt['id']; ?>">
                                        <textarea name="notes" class="ntarea"
                                                  placeholder="Write clinical notes, diagnosis, treatment plan, follow-up suggestions..."><?php echo htmlspecialchars($apt['notes'] ?? ''); ?></textarea>
                                        <button type="submit" name="save_notes" class="btn-save-notes">
                                            <i class="fas fa-save"></i> Save Notes
                                        </button>
                                        <button type="button" class="btn-cancel-notes" onclick="toggleNotes(<?php echo $apt['id']; ?>)">
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="mini-empty"><i class="fas fa-calendar-times"></i>No appointments with this patient yet.</div>
                    <?php endif; ?>

                    <!-- PRESCRIPTIONS -->
                    <div class="sec"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</div>

                    <?php if (count($prescriptions) > 0): ?>
                        <?php foreach ($prescriptions as $pr): ?>
                        <div class="presc-item">
                            <div class="presc-header">
                                <span><i class="fas fa-calendar" style="color:var(--m300);margin-right:.4rem"></i><?php echo date('M d, Y', strtotime($pr['issued_date'])); ?></span>
                                <span class="presc-date">Prescription #<?php echo $pr['id']; ?></span>
                            </div>
                            <?php if (!empty($pr['diagnosis'])): ?>
                            <div class="presc-diag"><i class="fas fa-diagnoses" style="margin-right:.4rem"></i><strong>Diagnosis:</strong> <?php echo htmlspecialchars($pr['diagnosis']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($pr['items_raw'])): ?>
                            <div class="med-list">
                                <?php foreach (explode(';;', $pr['items_raw']) as $item): ?>
                                <?php [$med, $dos, $freq, $dur, $inst] = array_pad(explode('|', $item), 5, ''); ?>
                                <div class="med-item">
                                    <i class="fas fa-pills"></i>
                                    <div>
                                        <div class="med-name"><?php echo htmlspecialchars($med); ?></div>
                                        <div class="med-detail">
                                            <?php echo implode(' · ', array_filter([$dos, $freq, $dur])); ?>
                                            <?php if ($inst): ?> — <?php echo htmlspecialchars($inst); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($pr['notes'])): ?>
                            <div style="margin-top:.6rem;font-size:.83rem;color:var(--n6)"><i class="fas fa-comment" style="color:var(--m300)"></i> <?php echo htmlspecialchars($pr['notes']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="mini-empty"><i class="fas fa-prescription-bottle"></i>No prescriptions yet.</div>
                    <?php endif; ?>

                </div><!-- /pt-body -->
            </div><!-- /detail-panel -->

            <?php else: ?>
            <div class="empty-detail">
                <i class="fas fa-hand-point-left"></i>
                <h3>Select a patient</h3>
                <p>Click any patient from the list to view their full record, appointment history, notes, prescriptions and medical files.</p>
            </div>
            <?php endif; ?>
            </div>

        </div><!-- /split -->
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
function filterPatients(q) {
    document.querySelectorAll('.pc').forEach(c => {
        c.style.display = c.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
    });
}

function toggleNotes(id) {
    const box = document.getElementById('notes-edit-' + id);
    box.classList.toggle('open');
}

<?php if ($success || $error): ?>
// Auto-scroll to top on save
window.scrollTo({ top: 0, behavior: 'smooth' });
<?php endif; ?>
</script>

</body>
</html>