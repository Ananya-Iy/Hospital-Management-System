<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('patient');

$user_id    = $_SESSION['user_id'];
$patient    = $conn->query("SELECT * FROM patients WHERE user_id = $user_id")->fetch_assoc();
$patient_id = $patient['id'];

// ── Prescriptions (with items) ──────────────────────────────────────────────
$prescriptions = [];
$presc_result  = $conn->query(
    "SELECT p.id, p.diagnosis, p.notes, p.issued_date,
            u.name AS doctor_name,
            s.name AS specialization
     FROM prescriptions p
     JOIN doctors d  ON p.doctor_id = d.id
     JOIN users u    ON d.user_id   = u.id
     JOIN specializations s ON d.specialization_id = s.id
     WHERE p.patient_id = $patient_id
     ORDER BY p.issued_date DESC"
);
while ($row = $presc_result->fetch_assoc()) {
    // Fetch items for this prescription
    $items_result = $conn->query(
        "SELECT medicine_name, dosage, frequency, duration
         FROM prescription_items
         WHERE prescription_id = {$row['id']}"
    );
    $row['items'] = [];
    while ($item = $items_result->fetch_assoc()) {
        $row['items'][] = $item;
    }
    $prescriptions[] = $row;
}

// ── Appointment notes (reason + doctor notes) ───────────────────────────────
$apt_notes = [];
$apt_result = $conn->query(
    "SELECT a.id, a.appointment_date, a.reason, a.notes,
            u.name AS doctor_name,
            s.name AS specialization
     FROM appointments a
     JOIN doctors d  ON a.doctor_id = d.id
     JOIN users u    ON d.user_id   = u.id
     JOIN specializations s ON d.specialization_id = s.id
     WHERE a.patient_id = $patient_id
       AND a.status = 'Completed'
       AND (a.notes IS NOT NULL AND a.notes != '')
     ORDER BY a.appointment_date DESC"
);
while ($row = $apt_result->fetch_assoc()) {
    $apt_notes[] = $row;
}

// ── Counts ──────────────────────────────────────────────────────────────────
$presc_count   = count($prescriptions);
$notes_count   = count($apt_notes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records · Valora Medical Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }

        :root {
            --n1: #F2F2F2;
            --n2: #E6E6E6;
            --n3: #DADADA;
            --n4: #C6C6C6;
            --n5: #9E9E9E;
            --n6: #6E6E6E;
            --n7: #3F3F3F;
            --n8: #1C1C1C;
            --maroon-50: #D8C9CE;
            --maroon-100: #C5A8B3;
            --maroon-200: #A56C7E;
            --maroon-300: #842646;
            --maroon-400: #7A2141;
            --maroon-500: #641732;
            --success-light: #C6D8D2;
            --success-primary: #39C37A;
            --success-deep: #2E955C;
            --warning-light: #E5D8C8;
            --warning-primary: #F48B05;
            --warning-deep: #B36805;
            --error-light: #E2D0CD;
            --error-primary: #F04233;
            --error-deep: #B03125;
            --info-light: #C9D3E6;
            --info-primary: #0E3E9E;
            --info-deep: #082E73;
            --bg-body: #F8F9FC;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
            --shadow-md: 0 8px 16px rgba(0,0,0,0.04);
            --shadow-lg: 0 16px 24px rgba(0,0,0,0.04);
            --shadow-xl: 0 20px 28px rgba(100,23,50,0.08);
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
            transition: all 0.2s;
        }

        .logout-btn:hover { background: var(--error-light); color: var(--error-deep); }

        /* ===== WRAPPER ===== */
        .page-wrapper { flex: 1; display: flex; flex-direction: column; }

        .container {
            max-width: 1280px;
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
            border-radius: 60px;
            box-shadow: var(--shadow-lg);
            max-width: 900px;
            border: 1px solid var(--n3);
            flex-wrap: wrap;
        }

        .tab {
            padding: 0.7rem 1.8rem;
            border-radius: 60px;
            font-weight: 600;
            color: var(--n7);
            transition: all 0.25s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .tab i { color: var(--maroon-300); font-size: 1rem; }
        .tab:hover { background: var(--maroon-50); color: var(--maroon-300); transform: translateY(-1px); }
        .tab.active { background: var(--maroon-300); color: white; box-shadow: 0 6px 14px -4px var(--maroon-200); }
        .tab.active i { color: white; }

        /* ===== PAGE HEADER (ALIGNED LEFT) ===== */
        .page-header { 
            margin-bottom: 2.5rem;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            position: relative;
            display: inline-block;
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

        .page-header p { 
            color: var(--n6); 
            margin-top: 1rem;
            font-size: 1rem;
        }

        /* ===== STATS ===== */
        .stats-grid {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .stat-card {
            background: white;
            border-radius: 24px;
            padding: 1.2rem 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
            display: flex;
            align-items: center;
            gap: 1.2rem;
            min-width: 180px;
            transition: all 0.2s;
        }

        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-xl); }

        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.blue   { background: var(--info-light);    color: var(--info-primary); }
        .stat-icon.orange { background: var(--warning-light);  color: var(--warning-deep); }

        .stat-info p  { font-size: 0.8rem; color: var(--n5); margin-bottom: 0.2rem; }
        .stat-info h3 { font-size: 2rem; font-weight: 700; line-height: 1; }

        /* ===== SECTION HEADER ===== */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2.5rem 0 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--n3);
        }

        .section-header h2 {
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .section-header h2 i { color: var(--maroon-300); font-size: 1.4rem; }

        .section-count {
            background: var(--maroon-50);
            color: var(--maroon-300);
            padding: 0.2rem 0.8rem;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* ===== RECORDS GRID ===== */
        .records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
        }

        /* ===== RECORD CARD ===== */
        .record-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
            transition: all 0.25s;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .record-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 6px; height: 100%;
        }

        .record-card.presc-card::before  { background: linear-gradient(180deg, var(--info-primary), var(--info-deep)); }
        .record-card.note-card::before   { background: linear-gradient(180deg, var(--warning-primary), var(--warning-deep)); }

        .record-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-xl); border-color: var(--maroon-100); }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .card-icon {
            width: 48px; height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .card-icon.blue   { background: var(--info-light);    color: var(--info-deep); }
        .card-icon.orange { background: var(--warning-light);  color: var(--warning-deep); }

        .card-date {
            background: var(--n1);
            padding: 0.3rem 1rem;
            border-radius: 40px;
            font-size: 0.8rem;
            color: var(--n6);
            font-weight: 500;
        }

        .card-title { 
            font-weight: 700; 
            font-size: 1.2rem; 
            margin-bottom: 0.3rem;
            color: var(--n8);
        }
        
        .card-doctor {
            color: var(--info-primary);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Medicine items */
        .medicine-list { display: flex; flex-direction: column; gap: 0.6rem; margin: 1rem 0; }

        .medicine-item {
            background: var(--n1);
            border-radius: 14px;
            padding: 0.8rem 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            transition: all 0.2s;
        }

        .medicine-item:hover { background: var(--n2); }

        .medicine-item i { color: var(--info-primary); margin-top: 0.2rem; }
        .med-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 0.2rem; }
        .med-meta { font-size: 0.8rem; color: var(--n6); }

        /* Diagnosis / notes box */
        .notes-box {
            background: var(--n1);
            border-radius: 16px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .notes-box p {
            color: var(--n7);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .notes-box strong { color: var(--maroon-300); }

        /* Card footer */
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            margin-top: auto;
            border-top: 1px solid var(--n2);
        }

        .med-count {
            font-size: 0.8rem;
            color: var(--n5);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1.2rem;
            background: var(--maroon-300);
            color: white;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .view-btn:hover { background: var(--maroon-400); transform: translateY(-1px); }

        .print-btn {
            background: var(--n2);
            color: var(--n7);
        }

        .print-btn:hover { background: var(--n3); }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 24px;
            border: 2px dashed var(--n4);
        }

        .empty-state i { font-size: 3.5rem; color: var(--n4); display: block; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.3rem; color: var(--n6); margin-bottom: 0.5rem; }
        .empty-state p  { color: var(--n5); }

        /* ===== TIMELINE ===== */
        .timeline-section {
            margin-top: 3rem;
            background: white;
            border-radius: 28px;
            padding: 2rem;
            border: 1px solid var(--n3);
            box-shadow: var(--shadow-md);
        }

        .timeline-section .section-header {
            margin-top: 0;
            border-bottom: none;
            padding-bottom: 0;
        }

        .timeline-wrap { 
            position: relative; 
            padding-left: 2rem;
            margin-top: 1rem;
        }

        .timeline-wrap::before {
            content: '';
            position: absolute;
            left: 7px; top: 0; bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--maroon-300), var(--info-primary), var(--n3));
        }

        .tl-item { position: relative; padding-bottom: 1.5rem; }

        .tl-dot {
            position: absolute;
            left: -2rem;
            top: 4px;
            width: 14px; height: 14px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: var(--shadow-sm);
        }

        .tl-dot.blue   { background: var(--info-primary); }
        .tl-dot.orange { background: var(--warning-primary); }

        .tl-card {
            background: var(--n1);
            border-radius: 18px;
            padding: 1rem 1.5rem;
            transition: all 0.2s;
        }

        .tl-card:hover { background: white; box-shadow: var(--shadow-sm); }

        .tl-date  { font-size: 0.8rem; color: var(--n5); margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.3rem; }
        .tl-title { font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
        .tl-title i { color: var(--maroon-300); }

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
            .container { padding: 1rem; }
            .main-tabs { border-radius: 24px; padding: 0.6rem; }
            .tab { padding: 0.5rem 1rem; font-size: 0.8rem; }
            .records-grid { grid-template-columns: 1fr; }
            .stats-grid { gap: 1rem; }
            .stat-card { padding: 1rem 1.5rem; min-width: auto; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header>
    <nav>
        <a href="patient-dashboard.php" class="logo">Valora</a> 
        <div class="nav-right">
            <a href="profile.php" class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
            </a>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>
</header>

<div class="page-wrapper">

   <!-- TABS -->
    <div class="main-tabs">
        <a href="appointments.php"         class="tab"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
        <a href="myappointments.php" class="tab"><i class="fas fa-calendar-alt"></i> My Appointments</a>
        <a href="finddoc.php"      class="tab"><i class="fas fa-user-md"></i> Find Doctors</a>
        <a href="reports.php"      class="tab active"><i class="fas fa-file-medical"></i> Medical Records</a>
        <a href="billings.php"      class="tab"><i class="fas fa-file-invoice"></i> Billing</a>
        <a href="profile.php"      class="tab "><i class="fas fa-user"></i> Profile</a>
    </div>
    <div class="container">

        <!-- PAGE HEADER (ALIGNED LEFT) -->
        <div class="page-header">
            <h1>Medical Records</h1>
            <p>Your complete health history at a glance</p>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-prescription"></i></div>
                <div class="stat-info">
                    <p>Prescriptions</p>
                    <h3><?php echo $presc_count; ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-notes-medical"></i></div>
                <div class="stat-info">
                    <p>Doctor's Notes</p>
                    <h3><?php echo $notes_count; ?></h3>
                </div>
            </div>
        </div>

        <!-- ── PRESCRIPTIONS ─────────────────────────────────────────── -->
        <div class="section-header">
            <h2><i class="fas fa-prescription"></i> Prescriptions</h2>
            <?php if ($presc_count > 0): ?>
                <span class="section-count"><?php echo $presc_count; ?> records</span>
            <?php endif; ?>
        </div>

        <?php if ($presc_count > 0): ?>
        <div class="records-grid">
            <?php foreach ($prescriptions as $p): ?>
            <div class="record-card presc-card">
                <div class="card-top">
                    <div class="card-icon blue"><i class="fas fa-prescription"></i></div>
                    <span class="card-date"><?php echo date('M d, Y', strtotime($p['issued_date'])); ?></span>
                </div>
                <div class="card-title">Prescription</div>
                <div class="card-doctor">
                    <i class="fas fa-user-md"></i>
                    Dr. <?php echo htmlspecialchars($p['doctor_name']); ?> · <?php echo htmlspecialchars($p['specialization']); ?>
                </div>

                <?php if (!empty($p['diagnosis'])): ?>
                <div class="notes-box">
                    <p><strong><i class="fas fa-stethoscope"></i> Diagnosis:</strong> <?php echo htmlspecialchars($p['diagnosis']); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($p['items'])): ?>
                <div class="medicine-list">
                    <?php foreach ($p['items'] as $item): ?>
                    <div class="medicine-item">
                        <i class="fas fa-pills"></i>
                        <div>
                            <div class="med-name"><?php echo htmlspecialchars($item['medicine_name']); ?></div>
                            <div class="med-meta">
                                <?php if ($item['dosage']):    echo htmlspecialchars($item['dosage']) . ' · '; endif; ?>
                                <?php if ($item['frequency']): echo htmlspecialchars($item['frequency']) . ' · '; endif; ?>
                                <?php if ($item['duration']):  echo htmlspecialchars($item['duration']); endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($p['notes'])): ?>
                <div class="notes-box">
                    <p><strong><i class="fas fa-comment-medical"></i> Notes:</strong> <?php echo nl2br(htmlspecialchars($p['notes'])); ?></p>
                </div>
                <?php endif; ?>

                <div class="card-footer">
                    <span class="med-count"><i class="fas fa-pills"></i> <?php echo count($p['items']); ?> medication(s)</span>
                    <button onclick="window.print()" class="view-btn print-btn">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-prescription"></i>
            <h3>No prescriptions yet</h3>
            <p>Prescriptions from your consultations will appear here</p>
        </div>
        <?php endif; ?>

        <!-- ── DOCTOR'S NOTES ────────────────────────────────────────── -->
        <div class="section-header">
            <h2><i class="fas fa-notes-medical"></i> Doctor's Notes</h2>
            <?php if ($notes_count > 0): ?>
                <span class="section-count"><?php echo $notes_count; ?> records</span>
            <?php endif; ?>
        </div>

        <?php if ($notes_count > 0): ?>
        <div class="records-grid">
            <?php foreach ($apt_notes as $n): ?>
            <div class="record-card note-card">
                <div class="card-top">
                    <div class="card-icon orange"><i class="fas fa-notes-medical"></i></div>
                    <span class="card-date"><?php echo date('M d, Y', strtotime($n['appointment_date'])); ?></span>
                </div>
                <div class="card-title">Consultation Notes</div>
                <div class="card-doctor">
                    <i class="fas fa-user-md"></i>
                    Dr. <?php echo htmlspecialchars($n['doctor_name']); ?> · <?php echo htmlspecialchars($n['specialization']); ?>
                </div>

                <?php if (!empty($n['reason'])): ?>
                <div class="notes-box">
                    <p><strong><i class="fas fa-question-circle"></i> Reason for Visit:</strong> <?php echo nl2br(htmlspecialchars($n['reason'])); ?></p>
                </div>
                <?php endif; ?>

                <div class="notes-box">
                    <p><strong><i class="fas fa-stethoscope"></i> Doctor's Notes:</strong> <?php echo nl2br(htmlspecialchars($n['notes'])); ?></p>
                </div>

                <div class="card-footer">
                    <span class="med-count"><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($n['appointment_date'])); ?></span>
                    <a href="appointment-details.php?id=<?php echo $n['id']; ?>" class="view-btn">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-notes-medical"></i>
            <h3>No doctor's notes yet</h3>
            <p>Notes from completed appointments will appear here</p>
        </div>
        <?php endif; ?>

        <!-- ── TIMELINE ──────────────────────────────────────────────── -->
        <?php
        $timeline = [];
        foreach ($prescriptions as $p) {
            $timeline[] = ['date' => $p['issued_date'], 'label' => 'Prescription from Dr. ' . $p['doctor_name'], 'color' => 'blue', 'icon' => 'fa-prescription-bottle'];
        }
        foreach ($apt_notes as $n) {
            $timeline[] = ['date' => $n['appointment_date'], 'label' => 'Consultation with Dr. ' . $n['doctor_name'], 'color' => 'orange', 'icon' => 'fa-stethoscope'];
        }
        usort($timeline, fn($a,$b) => strtotime($b['date']) - strtotime($a['date']));
        $timeline = array_slice($timeline, 0, 5);
        ?>

        <?php if (!empty($timeline)): ?>
        <div class="timeline-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
            </div>
            <div class="timeline-wrap">
                <?php foreach ($timeline as $t): ?>
                <div class="tl-item">
                    <div class="tl-dot <?php echo $t['color']; ?>"></div>
                    <div class="tl-card">
                        <div class="tl-date"><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($t['date'])); ?></div>
                        <div class="tl-title">
                            <i class="fas <?php echo $t['icon']; ?>"></i>
                            <?php echo htmlspecialchars($t['label']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem; font-weight:600;">Valora Medical Center</p>
        <p style="color:var(--n4); font-size:0.9rem;">This is a university project for educational purposes; all hospital information & services is  fictional.</p>
        <p class="footer-copy">&copy; </p>
    </div>
</footer>

</body>
</html>