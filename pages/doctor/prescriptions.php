<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id   = $_SESSION['user_id'];
$doc_row   = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id")->fetch_assoc();
$doctor_id = $doc_row['id'] ?? null;

$success = '';
$error   = '';

// ── Delete prescription ───────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM prescriptions WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $del_id, $doctor_id);
    $stmt->execute();
    $stmt->close();
    header("Location: prescriptions.php?deleted=1");
    exit;
}

// ── Write / Edit prescription ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prescription'])) {
    $edit_id    = intval($_POST['edit_id']    ?? 0);
    $apt_id     = intval($_POST['apt_id']);
    $patient_id = intval($_POST['patient_id']);
    $diagnosis  = sanitize($_POST['diagnosis']  ?? '');
    $notes      = sanitize($_POST['notes']      ?? '');
    $issued_date = sanitize($_POST['issued_date'] ?? date('Y-m-d'));

    // Medicines arrays
    $med_names  = $_POST['med_name']  ?? [];
    $med_dos    = $_POST['med_dos']   ?? [];
    $med_freq   = $_POST['med_freq']  ?? [];
    $med_dur    = $_POST['med_dur']   ?? [];
    $med_inst   = $_POST['med_inst']  ?? [];

    // Filter out empty medicine rows
    $medicines = [];
    foreach ($med_names as $i => $name) {
        $name = sanitize(trim($name));
        if (!empty($name)) {
            $medicines[] = [
                'name'  => $name,
                'dos'   => sanitize(trim($med_dos[$i]  ?? '')),
                'freq'  => sanitize(trim($med_freq[$i] ?? '')),
                'dur'   => sanitize(trim($med_dur[$i]  ?? '')),
                'inst'  => sanitize(trim($med_inst[$i] ?? '')),
            ];
        }
    }

    if (empty($medicines)) {
        $error = 'Please add at least one medicine.';
    } elseif (!$apt_id || !$patient_id) {
        $error = 'Please select a patient and appointment.';
    } else {
        if ($edit_id) {
            // UPDATE existing
            $stmt = $conn->prepare(
                "UPDATE prescriptions SET appointment_id=?, patient_id=?, diagnosis=?, notes=?, issued_date=?
                 WHERE id=? AND doctor_id=?"
            );
            $stmt->bind_param("iisssi i", $apt_id, $patient_id, $diagnosis, $notes, $issued_date, $edit_id, $doctor_id);
            $stmt->bind_param("iisssii", $apt_id, $patient_id, $diagnosis, $notes, $issued_date, $edit_id, $doctor_id);
            $stmt->execute();
            $stmt->close();
            // Delete old items and re-insert
            $conn->query("DELETE FROM prescription_items WHERE prescription_id = $edit_id");
            $presc_id = $edit_id;
            $success  = 'Prescription updated successfully.';
        } else {
            // INSERT new
            $stmt = $conn->prepare(
                "INSERT INTO prescriptions (appointment_id, patient_id, doctor_id, diagnosis, notes, issued_date)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiisss", $apt_id, $patient_id, $doctor_id, $diagnosis, $notes, $issued_date);
            $stmt->execute();
            $presc_id = $conn->insert_id;
            $stmt->close();
            $success = 'Prescription created successfully.';
        }

        // Insert medicine items
        $item_stmt = $conn->prepare(
            "INSERT INTO prescription_items (prescription_id, medicine_name, dosage, frequency, duration, instructions)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($medicines as $m) {
            $item_stmt->bind_param("isssss", $presc_id, $m['name'], $m['dos'], $m['freq'], $m['dur'], $m['inst']);
            $item_stmt->execute();
        }
        $item_stmt->close();

        header("Location: prescriptions.php?saved=1");
        exit;
    }
}

// ── Fetch all patients of this doctor (for the form) ─────────────────────────
$all_patients = [];
$pr = $conn->query(
    "SELECT DISTINCT p.id, u.name FROM patients p
     JOIN users u ON p.user_id = u.id
     JOIN appointments a ON a.patient_id = p.id
     WHERE a.doctor_id = $doctor_id ORDER BY u.name"
);
while ($r = $pr->fetch_assoc()) $all_patients[] = $r;

// ── Fetch appointments per patient for the form (via AJAX below) ──────────────
// Handled by inline JS using PHP-injected JSON

// ── Build appointments map: patient_id => [{id, date, time}] ─────────────────
$apt_map = [];
$ar = $conn->query(
    "SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time, a.status
     FROM appointments a
     WHERE a.doctor_id = $doctor_id AND a.status IN ('Confirmed','Completed')
     ORDER BY a.appointment_date DESC"
);
while ($r = $ar->fetch_assoc()) {
    $apt_map[$r['patient_id']][] = $r;
}

// ── Fetch all prescriptions ───────────────────────────────────────────────────
$prescriptions = [];
if ($doctor_id) {
    $res = $conn->query(
        "SELECT pr.*, u.name AS patient_name, p.id AS pat_id,
                a.appointment_date, a.appointment_time
         FROM prescriptions pr
         JOIN patients p ON pr.patient_id = p.id
         JOIN users u    ON p.user_id     = u.id
         JOIN appointments a ON pr.appointment_id = a.id
         WHERE pr.doctor_id = $doctor_id
         ORDER BY pr.issued_date DESC, pr.id DESC"
    );
    while ($row = $res->fetch_assoc()) {
        $meds = [];
        $mr = $conn->query("SELECT * FROM prescription_items WHERE prescription_id = {$row['id']}");
        while ($m = $mr->fetch_assoc()) $meds[] = $m;
        $row['medicines'] = $meds;
        $prescriptions[] = $row;
    }
}

// ── Load prescription for editing ────────────────────────────────────────────
$edit_presc = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $er = $conn->query(
        "SELECT pr.* FROM prescriptions pr WHERE pr.id = $eid AND pr.doctor_id = $doctor_id"
    )->fetch_assoc();
    if ($er) {
        $meds = [];
        $mr = $conn->query("SELECT * FROM prescription_items WHERE prescription_id = $eid");
        while ($m = $mr->fetch_assoc()) $meds[] = $m;
        $er['medicines'] = $meds;
        $edit_presc = $er;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions · Valora</title>
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
            --wl:#E5D8C8;--wp:#F48B05;--wd:#B36805;
            --bg:#F8F9FC;
            --sh-md:0 8px 16px rgba(0,0,0,.04);
            --sh-lg:0 16px 24px rgba(0,0,0,.04);
            --sh-xl:0 24px 32px rgba(100,23,50,.08);
        }
        html,body{height:100%}
        body{background:var(--bg);color:var(--n8);min-height:100vh;display:flex;flex-direction:column;line-height:1.5}
        a{text-decoration:none}

        header{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);box-shadow:var(--sh-md);position:sticky;top:0;z-index:100;border-bottom:1px solid rgba(218,218,218,.3)}
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
        .container{max-width:1100px;margin:1rem auto 3rem;padding:0 2rem;flex:1;width:100%}

        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
        .page-header h1{font-size:2.2rem;font-weight:700;position:relative;padding-bottom:.8rem}
        .page-header h1::after{content:'';position:absolute;bottom:0;left:0;width:80px;height:4px;background:linear-gradient(90deg,var(--m300),var(--ip));border-radius:4px}

        .alert{padding:1rem 1.5rem;border-radius:14px;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:var(--sl);color:var(--sd);border-left:6px solid var(--sp)}
        .alert-error{background:var(--el);color:var(--ed);border-left:6px solid var(--ep)}

        /* NEW PRESCRIPTION BUTTON */
        .btn-new{padding:.85rem 1.8rem;background:linear-gradient(135deg,var(--m300),var(--m400));color:white;border-radius:40px;font-weight:700;font-size:.95rem;display:inline-flex;align-items:center;gap:.6rem;box-shadow:0 8px 16px -4px var(--m200);transition:.2s;border:none;cursor:pointer;font-family:inherit}
        .btn-new:hover{background:linear-gradient(135deg,var(--m400),var(--m500));transform:translateY(-2px)}

        /* WRITE FORM */
        .write-form{background:white;border-radius:28px;padding:2.5rem;box-shadow:var(--sh-xl);border:1px solid var(--n3);margin-bottom:2.5rem;position:relative;overflow:hidden;display:none}
        .write-form.open{display:block}
        .write-form::before{content:'';position:absolute;top:0;left:0;width:100%;height:5px;background:linear-gradient(90deg,var(--m300),var(--ip),var(--sp))}
        .form-title{font-size:1.4rem;font-weight:700;margin-bottom:1.8rem;display:flex;align-items:center;gap:.6rem;color:var(--n8)}
        .form-title i{color:var(--m300)}

        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}
        .fg{margin-bottom:0}
        .fg.full{grid-column:span 2}
        .fg label{display:block;font-weight:600;font-size:.88rem;margin-bottom:.4rem;color:var(--n7)}
        .fg label i{color:var(--m300);margin-right:.3rem}
        .fc{width:100%;padding:.9rem 1.1rem;border:2px solid var(--n3);border-radius:14px;font-size:.92rem;background:var(--n1);font-family:inherit;transition:.2s;color:var(--n8)}
        .fc:focus{outline:none;border-color:var(--m300);background:white}
        select.fc{appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23842646' stroke-width='2'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right 1rem center;background-size:1rem;background-color:var(--n1)}
        textarea.fc{resize:vertical;min-height:80px}

        /* MEDICINES TABLE */
        .meds-section{margin-bottom:1.5rem}
        .meds-label{font-weight:700;font-size:.95rem;color:var(--n8);margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
        .meds-label i{color:var(--m300)}

        .med-row{display:grid;grid-template-columns:2fr 1fr 1.5fr 1fr 2fr auto;gap:.6rem;align-items:start;margin-bottom:.6rem;background:var(--n1);border-radius:14px;padding:.8rem 1rem}
        .med-row input{padding:.6rem .8rem;border:2px solid var(--n3);border-radius:10px;font-size:.85rem;font-family:inherit;background:white;transition:.2s;width:100%}
        .med-row input:focus{outline:none;border-color:var(--m300)}
        .med-row input::placeholder{color:var(--n4)}

        .med-header{display:grid;grid-template-columns:2fr 1fr 1.5fr 1fr 2fr auto;gap:.6rem;padding:0 1rem;margin-bottom:.3rem}
        .med-header span{font-size:.75rem;font-weight:600;color:var(--n5);text-transform:uppercase}

        .btn-remove-med{width:32px;height:32px;background:var(--el);color:var(--ed);border:none;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;transition:.2s;flex-shrink:0;margin-top:2px}
        .btn-remove-med:hover{background:var(--ep);color:white}

        .btn-add-med{padding:.6rem 1.3rem;background:var(--il);color:var(--ip);border:none;border-radius:40px;font-size:.85rem;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:.4rem;transition:.2s}
        .btn-add-med:hover{background:var(--ip);color:white}

        /* FORM ACTIONS */
        .form-actions{display:flex;gap:1rem;margin-top:1.5rem}
        .btn-submit{padding:.9rem 2.5rem;background:var(--m300);color:white;border:none;border-radius:40px;font-weight:700;font-size:.95rem;cursor:pointer;font-family:inherit;transition:.2s;display:inline-flex;align-items:center;gap:.6rem}
        .btn-submit:hover{background:var(--m400)}
        .btn-cancel-form{padding:.9rem 1.5rem;background:var(--n2);color:var(--n7);border:none;border-radius:40px;font-weight:600;font-size:.9rem;cursor:pointer;font-family:inherit;transition:.2s}
        .btn-cancel-form:hover{background:var(--n3)}

        /* PRESCRIPTION CARDS */
        .presc-card{background:white;border-radius:24px;padding:1.8rem;margin-bottom:1.5rem;border:1px solid var(--n3);box-shadow:var(--sh-md);transition:.2s;position:relative;overflow:hidden}
        .presc-card::before{content:'';position:absolute;top:0;left:0;width:5px;height:100%;background:linear-gradient(180deg,var(--m300),var(--ip))}
        .presc-card:hover{transform:translateY(-2px);box-shadow:var(--sh-xl)}

        .pc-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem}
        .pc-patient{display:flex;align-items:center;gap:1rem}
        .pc-av{width:56px;height:56px;background:linear-gradient(135deg,var(--m50),var(--il));border-radius:18px;display:flex;align-items:center;justify-content:center;color:var(--m300);font-size:1.6rem;flex-shrink:0}
        .pc-name{font-weight:700;font-size:1.1rem;margin-bottom:.2rem}
        .pc-meta{font-size:.82rem;color:var(--n5)}

        .pc-actions{display:flex;gap:.5rem}
        .btn-edit-presc{padding:.45rem 1rem;background:var(--il);color:var(--ip);border:none;border-radius:40px;font-size:.8rem;font-weight:600;cursor:pointer;font-family:inherit;transition:.2s;display:inline-flex;align-items:center;gap:.3rem}
        .btn-edit-presc:hover{background:var(--ip);color:white}
        .btn-del-presc{padding:.45rem 1rem;background:var(--el);color:var(--ed);border:none;border-radius:40px;font-size:.8rem;font-weight:600;cursor:pointer;font-family:inherit;transition:.2s;display:inline-flex;align-items:center;gap:.3rem}
        .btn-del-presc:hover{background:var(--ep);color:white}
        .btn-print-presc{padding:.45rem 1rem;background:var(--n2);color:var(--n7);border:none;border-radius:40px;font-size:.8rem;font-weight:600;cursor:pointer;font-family:inherit;transition:.2s;display:inline-flex;align-items:center;gap:.3rem}
        .btn-print-presc:hover{background:var(--n3)}

        .pc-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.8rem;margin-bottom:1.2rem}
        .pi-box{background:var(--n1);border-radius:12px;padding:.7rem 1rem}
        .pi-box .lbl{font-size:.72rem;color:var(--n5);text-transform:uppercase;font-weight:600;margin-bottom:.2rem}
        .pi-box .val{font-weight:700;font-size:.88rem}

        .diag-box{background:var(--il);border-radius:12px;padding:.8rem 1rem;margin-bottom:1rem;font-size:.88rem;color:var(--ip)}
        .diag-box strong{font-weight:700;margin-right:.4rem}

        .meds-grid{display:flex;flex-direction:column;gap:.5rem}
        .med-card{background:var(--n1);border-radius:12px;padding:.8rem 1.1rem;display:flex;align-items:flex-start;gap:.8rem}
        .med-card i{color:var(--m300);margin-top:.15rem;flex-shrink:0}
        .med-card-name{font-weight:700;font-size:.9rem;margin-bottom:.2rem}
        .med-card-detail{font-size:.8rem;color:var(--n6)}

        .notes-badge{margin-top:.8rem;font-size:.85rem;color:var(--n6);display:flex;align-items:flex-start;gap:.5rem}
        .notes-badge i{color:var(--m300);margin-top:.1rem;flex-shrink:0}

        /* EMPTY */
        .empty{text-align:center;padding:4rem 2rem;background:white;border-radius:24px;border:2px dashed var(--n4)}
        .empty i{font-size:4rem;color:var(--n4);display:block;margin-bottom:1rem}
        .empty h3{color:var(--n6);margin-bottom:.5rem}
        .empty p{color:var(--n5)}

        /* PRINT */
        @media print{
            header,.main-tabs,.write-form,.pc-actions,footer{display:none!important}
            .presc-card{box-shadow:none;border:1px solid #ccc;break-inside:avoid}
        }

        footer{background:var(--n7);color:var(--n2);padding:2rem;text-align:center;border-top:5px solid var(--m300);margin-top:auto}
        .footer-content{max-width:1400px;margin:0 auto}
        .footer-copy{color:var(--n5);font-size:.85rem;margin-top:.5rem}

        @media(max-width:768px){
            nav{flex-direction:column;gap:.8rem;padding:1rem}
            .container{padding:0 1rem}
            .form-grid{grid-template-columns:1fr}
            .fg.full{grid-column:span 1}
            .med-row{grid-template-columns:1fr;gap:.4rem}
            .med-header{display:none}
            .page-header{flex-direction:column;align-items:flex-start}
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
        <a href="patients.php"      class="tab"><i class="fas fa-users"></i> Patients</a>
        <a href="prescriptions.php" class="tab active"><i class="fas fa-prescription"></i> Prescriptions</a>
        <a href="availability.php"  class="tab"><i class="fas fa-calendar-times"></i> Schedule</a>
        <a href="profile.php"       class="tab"><i class="fas fa-user-md"></i> Profile</a>
    </div>

    <div class="container">

        <div class="page-header">
            <h1>Prescriptions</h1>
            <button class="btn-new" onclick="openForm()">
                <i class="fas fa-plus"></i> Write Prescription
            </button>
        </div>

        <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Prescription saved successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Prescription deleted.</div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ══════════ WRITE / EDIT FORM ══════════ -->
        <div class="write-form <?php echo ($edit_presc || $error) ? 'open' : ''; ?>" id="writeForm">
            <div class="form-title">
                <i class="fas fa-prescription"></i>
                <?php echo $edit_presc ? 'Edit Prescription' : 'New Prescription'; ?>
            </div>

            <form method="POST">
                <input type="hidden" name="save_prescription" value="1">
                <input type="hidden" name="edit_id" value="<?php echo $edit_presc['id'] ?? 0; ?>">

                <div class="form-grid">
                    <div class="fg">
                        <label><i class="fas fa-user-injured"></i> Patient</label>
                        <select name="patient_id" class="fc" id="patientSelect"
                                onchange="loadApts(this.value)" required>
                            <option value="">Select patient...</option>
                            <?php foreach ($all_patients as $p): ?>
                            <option value="<?php echo $p['id']; ?>"
                                <?php echo ($edit_presc && $edit_presc['patient_id'] == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-calendar-alt"></i> Appointment</label>
                        <select name="apt_id" class="fc" id="aptSelect" required>
                            <option value="">Select patient first...</option>
                            <?php if ($edit_presc): ?>
                            <option value="<?php echo $edit_presc['appointment_id']; ?>" selected>
                                <?php echo date('M d, Y', strtotime($edit_presc['appointment_date'] ?? '')); ?>
                            </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-calendar"></i> Issue Date</label>
                        <input type="date" name="issued_date" class="fc"
                               value="<?php echo $edit_presc['issued_date'] ?? date('Y-m-d'); ?>" required>
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-diagnoses"></i> Diagnosis</label>
                        <input type="text" name="diagnosis" class="fc"
                               placeholder="e.g. Upper respiratory infection"
                               value="<?php echo htmlspecialchars($edit_presc['diagnosis'] ?? ''); ?>">
                    </div>
                    <div class="fg full">
                        <label><i class="fas fa-sticky-note"></i> Additional Notes <span style="color:var(--n5);font-weight:400">(optional)</span></label>
                        <textarea name="notes" class="fc"
                                  placeholder="Follow-up instructions, rest recommendations, dietary advice..."><?php echo htmlspecialchars($edit_presc['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- MEDICINES -->
                <div class="meds-section">
                    <div class="meds-label"><i class="fas fa-pills"></i> Medications</div>
                    <div class="med-header">
                        <span>Medicine Name *</span>
                        <span>Dosage</span>
                        <span>Frequency</span>
                        <span>Duration</span>
                        <span>Instructions</span>
                        <span></span>
                    </div>
                    <div id="medRows">
                        <?php
                        $init_meds = $edit_presc['medicines'] ?? [[]];
                        if (empty($init_meds)) $init_meds = [[]];
                        foreach ($init_meds as $m):
                        ?>
                        <div class="med-row">
                            <input type="text"   name="med_name[]" placeholder="Medicine name" required
                                   value="<?php echo htmlspecialchars($m['medicine_name'] ?? ''); ?>">
                            <input type="text"   name="med_dos[]"  placeholder="e.g. 500mg"
                                   value="<?php echo htmlspecialchars($m['dosage'] ?? ''); ?>">
                            <input type="text"   name="med_freq[]" placeholder="e.g. Twice daily"
                                   value="<?php echo htmlspecialchars($m['frequency'] ?? ''); ?>">
                            <input type="text"   name="med_dur[]"  placeholder="e.g. 7 days"
                                   value="<?php echo htmlspecialchars($m['duration'] ?? ''); ?>">
                            <input type="text"   name="med_inst[]" placeholder="e.g. After meals"
                                   value="<?php echo htmlspecialchars($m['instructions'] ?? ''); ?>">
                            <button type="button" class="btn-remove-med" onclick="removeMed(this)" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-add-med" onclick="addMed()">
                        <i class="fas fa-plus"></i> Add Medicine
                    </button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i>
                        <?php echo $edit_presc ? 'Update Prescription' : 'Save Prescription'; ?>
                    </button>
                    <button type="button" class="btn-cancel-form" onclick="closeForm()">Cancel</button>
                </div>
            </form>
        </div>

        <!-- ══════════ PRESCRIPTIONS LIST ══════════ -->
        <?php if (count($prescriptions) > 0): ?>
            <?php foreach ($prescriptions as $pr): ?>
            <div class="presc-card" id="presc-<?php echo $pr['id']; ?>">

                <div class="pc-head">
                    <div class="pc-patient">
                        <div class="pc-av"><i class="fas fa-user-injured"></i></div>
                        <div>
                            <div class="pc-name"><?php echo htmlspecialchars($pr['patient_name']); ?></div>
                            <div class="pc-meta">
                                Rx #<?php echo str_pad($pr['id'], 5, '0', STR_PAD_LEFT); ?>
                                &nbsp;·&nbsp;
                                <?php echo date('M d, Y', strtotime($pr['appointment_date'])); ?>
                                <?php echo date('h:i A', strtotime($pr['appointment_time'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="pc-actions">
                        <button class="btn-edit-presc" onclick="location.href='?edit=<?php echo $pr['id']; ?>'">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-print-presc" onclick="printPresc(<?php echo $pr['id']; ?>)">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="btn-del-presc"
                                onclick="if(confirm('Delete this prescription?')) location.href='?delete=<?php echo $pr['id']; ?>'">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <div class="pc-info">
                    <div class="pi-box">
                        <div class="lbl">Issue Date</div>
                        <div class="val"><?php echo date('M d, Y', strtotime($pr['issued_date'])); ?></div>
                    </div>
                    <div class="pi-box">
                        <div class="lbl">Medicines</div>
                        <div class="val"><?php echo count($pr['medicines']); ?> item<?php echo count($pr['medicines']) !== 1 ? 's' : ''; ?></div>
                    </div>
                </div>

                <?php if (!empty($pr['diagnosis'])): ?>
                <div class="diag-box">
                    <i class="fas fa-stethoscope"></i>
                    <strong>Diagnosis:</strong><?php echo htmlspecialchars($pr['diagnosis']); ?>
                </div>
                <?php endif; ?>

                <?php if (count($pr['medicines']) > 0): ?>
                <div class="meds-grid">
                    <?php foreach ($pr['medicines'] as $m): ?>
                    <div class="med-card">
                        <i class="fas fa-pills"></i>
                        <div>
                            <div class="med-card-name"><?php echo htmlspecialchars($m['medicine_name']); ?></div>
                            <div class="med-card-detail">
                                <?php
                                $parts = array_filter([
                                    $m['dosage']    ? $m['dosage']                          : '',
                                    $m['frequency'] ? $m['frequency']                       : '',
                                    $m['duration']  ? 'for ' . $m['duration']               : '',
                                    $m['instructions'] ? '— ' . $m['instructions']          : '',
                                ]);
                                echo htmlspecialchars(implode(' · ', $parts));
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($pr['notes'])): ?>
                <div class="notes-badge">
                    <i class="fas fa-sticky-note"></i>
                    <span><?php echo htmlspecialchars($pr['notes']); ?></span>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="empty">
            <i class="fas fa-prescription-bottle-alt"></i>
            <h3>No prescriptions yet</h3>
            <p>Click "Write Prescription" to create your first one.</p>
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

<script>
// ── Appointment map from PHP ──────────────────────────────────────────────────
const aptMap = <?php echo json_encode($apt_map); ?>;

function loadApts(patientId) {
    const sel  = document.getElementById('aptSelect');
    sel.innerHTML = '<option value="">Select appointment...</option>';
    const apts = aptMap[patientId] || [];
    if (apts.length === 0) {
        sel.innerHTML = '<option value="">No confirmed appointments</option>';
        return;
    }
    apts.forEach(a => {
        const d    = new Date(a.appointment_date);
        const opts = { year:'numeric', month:'short', day:'numeric' };
        const opt  = document.createElement('option');
        opt.value  = a.id;
        opt.textContent = d.toLocaleDateString('en-US', opts) + ' — ' + a.status;
        sel.appendChild(opt);
    });
}

// If editing, pre-load the apt list
<?php if ($edit_presc): ?>
loadApts(<?php echo $edit_presc['patient_id']; ?>);
document.getElementById('aptSelect').value = '<?php echo $edit_presc['appointment_id']; ?>';
<?php endif; ?>

// ── Form open/close ───────────────────────────────────────────────────────────
function openForm() {
    document.getElementById('writeForm').classList.add('open');
    document.getElementById('writeForm').scrollIntoView({ behavior:'smooth', block:'start' });
}

function closeForm() {
    document.getElementById('writeForm').classList.remove('open');
    window.scrollTo({ top:0, behavior:'smooth' });
}

// ── Add / remove medicine rows ────────────────────────────────────────────────
function addMed() {
    const row = document.createElement('div');
    row.className = 'med-row';
    row.innerHTML = `
        <input type="text"  name="med_name[]" placeholder="Medicine name" required>
        <input type="text"  name="med_dos[]"  placeholder="e.g. 500mg">
        <input type="text"  name="med_freq[]" placeholder="e.g. Twice daily">
        <input type="text"  name="med_dur[]"  placeholder="e.g. 7 days">
        <input type="text"  name="med_inst[]" placeholder="e.g. After meals">
        <button type="button" class="btn-remove-med" onclick="removeMed(this)" title="Remove">
            <i class="fas fa-times"></i>
        </button>`;
    document.getElementById('medRows').appendChild(row);
    row.querySelector('input').focus();
}

function removeMed(btn) {
    const rows = document.querySelectorAll('.med-row');
    if (rows.length <= 1) { alert('At least one medicine is required.'); return; }
    btn.closest('.med-row').remove();
}

// ── Print single prescription ─────────────────────────────────────────────────
function printPresc(id) {
    // Hide all cards except the target
    document.querySelectorAll('.presc-card').forEach(c => {
        c.style.display = c.id === 'presc-' + id ? '' : 'none';
    });
    window.print();
    document.querySelectorAll('.presc-card').forEach(c => c.style.display = '');
}

<?php if ($edit_presc || $error): ?>
// Auto-scroll to form on edit/error
document.getElementById('writeForm').scrollIntoView({ behavior:'smooth', block:'start' });
<?php endif; ?>
</script>

</body>
</html>