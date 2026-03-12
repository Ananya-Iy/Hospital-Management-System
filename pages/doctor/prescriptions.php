<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];
$d_result = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id");
$doctor = $d_result->fetch_assoc();
$doctor_id = $doctor['id'] ?? null;

// Get all prescriptions with medicines
$prescriptions = [];
if ($doctor_id) {
    $presc_result = $conn->query(
        "SELECT pr.*, u.name as patient_name, p.id as patient_id
         FROM prescriptions pr
         JOIN patients p ON pr.patient_id = p.id
         JOIN users u ON p.user_id = u.id
         WHERE pr.doctor_id = $doctor_id
         ORDER BY pr.issued_date DESC"
    );
    while ($row = $presc_result->fetch_assoc()) {
        // Get medicines for this prescription
        $medicines = [];
        $med_result = $conn->query(
            "SELECT * FROM prescription_items WHERE prescription_id = " . $row['id']
        );
        while ($med = $med_result->fetch_assoc()) {
            $medicines[] = $med;
        }
        $row['medicines'] = $medicines;
        $prescriptions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions · Valora Medical Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }
        :root {
            --n1: #F2F2F2; --n2: #E6E6E6; --n3: #DADADA; --n4: #C6C6C6;
            --n5: #9E9E9E; --n6: #6E6E6E; --n7: #3F3F3F; --n8: #1C1C1C;
            --maroon-50: #D8C9CE; --maroon-100: #C5A8B3; --maroon-200: #A56C7E;
            --maroon-300: #842646; --maroon-400: #7A2141; --maroon-500: #641732;
            --success-light: #C6D8D2; --success-primary: #39C37A;
            --info-light: #C9D3E6; --info-primary: #0E3E9E;
            --bg-body: #F8F9FC;
            --shadow-md: 0 8px 16px rgba(0,0,0,0.04);
            --shadow-xl: 0 24px 32px rgba(100,23,50,0.08);
        }
        html, body { height: 100%; }
        body { background-color: var(--bg-body); color: var(--n8); min-height: 100vh; display: flex; flex-direction: column; line-height: 1.5; }
        a { text-decoration: none; }
        header { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); box-shadow: var(--shadow-md); position: sticky; top: 0; z-index: 50; border-bottom: 1px solid rgba(218,218,218,0.3); }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1400px; margin: 0 auto; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--maroon-300); letter-spacing: -0.5px; position: relative; }
        .logo::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 40%; height: 3px; background: var(--maroon-300); border-radius: 4px; }
        .nav-links { display: flex; gap: 2rem; list-style: none; align-items: center; }
        .nav-links a { color: var(--n7); font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--maroon-300); }
        .logout-btn { background: var(--n2); padding: 0.5rem 1.2rem; border-radius: 40px; font-weight: 600; color: var(--n7) !important; }
        .logout-btn:hover { background: var(--n3); }
        .main-tabs { display: flex; justify-content: center; gap: 0.5rem; margin: 1.5rem auto; padding: 0.5rem; background: white; border-radius: 100px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); max-width: 960px; border: 1px solid var(--n3); flex-wrap: wrap; }
        .tab { padding: 0.7rem 1.4rem; border-radius: 100px; font-weight: 600; color: var(--n7); transition: all 0.25s; display: flex; align-items: center; gap: 0.45rem; font-size: 0.92rem; white-space: nowrap; }
        .tab i { color: var(--maroon-300); font-size: 0.95rem; transition: color 0.25s; }
        .tab:hover { background: var(--maroon-50); color: var(--maroon-300); }
        .tab.active { background: var(--maroon-300); color: white; box-shadow: 0 8px 16px -4px rgba(132,38,70,0.4); }
        .tab.active i { color: white; }
        .container { max-width: 1200px; margin: 1rem auto 3rem; padding: 0 2rem; flex: 1; width: 100%; }
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: 2.2rem; font-weight: 700; color: var(--n8); position: relative; padding-bottom: 0.8rem; }
        .page-header h1::after { content: ''; position: absolute; bottom: 0; left: 0; width: 80px; height: 4px; background: linear-gradient(90deg, var(--maroon-300), var(--info-primary)); border-radius: 4px; }
        .page-header p { color: var(--n6); margin-top: 0.8rem; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .btn-primary { background: linear-gradient(135deg, var(--maroon-300), var(--maroon-400)); color: white; padding: 0.9rem 2rem; border-radius: 100px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.6rem; box-shadow: 0 8px 16px -4px var(--maroon-200); transition: all 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); }
        .prescription-card { background: white; border-radius: 24px; padding: 1.8rem; margin-bottom: 1.5rem; border: 1px solid var(--n3); box-shadow: var(--shadow-md); transition: all 0.2s; }
        .prescription-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-xl); }
        .presc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 1rem; }
        .presc-patient { display: flex; align-items: center; gap: 1rem; }
        .patient-avatar { width: 60px; height: 60px; background: linear-gradient(135deg, var(--maroon-50), var(--info-light)); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: var(--maroon-300); flex-shrink: 0; }
        .patient-name { font-weight: 700; font-size: 1.2rem; margin-bottom: 0.2rem; }
        .patient-meta { color: var(--n6); font-size: 0.9rem; }
        .presc-details { padding: 1.2rem; background: var(--n1); border-radius: 16px; margin-bottom: 1rem; }
        .detail-item { margin-bottom: 0.8rem; }
        .detail-item:last-child { margin-bottom: 0; }
        .detail-item i { color: var(--maroon-300); margin-right: 0.6rem; }
        .detail-item strong { font-weight: 600; }
        .medicine-list { background: var(--info-light); padding: 1.2rem; border-radius: 12px; }
        .medicine-list strong { display: block; margin-bottom: 0.8rem; color: var(--info-primary); }
        .medicine-list p { margin-bottom: 0.5rem; white-space: pre-line; }
        .empty-state { text-align: center; padding: 4rem 2rem; background: white; border-radius: 24px; box-shadow: var(--shadow-md); }
        .empty-state i { font-size: 4rem; color: var(--n4); display: block; margin-bottom: 1.5rem; }
        .empty-state h3 { font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--n7); }
        .empty-state p { color: var(--n5); }
        footer { background: var(--n7); color: var(--n2); padding: 2rem; text-align: center; border-top: 5px solid var(--maroon-300); margin-top: auto; }
        .footer-content { max-width: 1400px; margin: 0 auto; }
        .footer-copy { color: var(--n5); font-size: 0.85rem; margin-top: 0.5rem; }
        @media (max-width: 768px) { nav { flex-direction: column; gap: 1rem; } .nav-links { flex-direction: column; text-align: center; gap: 1rem; } .presc-header { flex-direction: column; } }
    </style>
</head>
<body>
<header>
    <nav>
        <a href="../../index.php" class="logo">Valora</a>
        <ul class="nav-links">
            <li><a href="doctor-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</header>
<div class="main-tabs">
    <a href="appointments.php" class="tab"><i class="fas fa-calendar-alt"></i> Appointments</a>
    <a href="patients.php" class="tab"><i class="fas fa-users"></i> Patients</a>
    <a href="prescriptions.php" class="tab active"><i class="fas fa-prescription"></i> Prescriptions</a>
    <a href="availability.php" class="tab"><i class="fas fa-calendar-times"></i> Schedule</a>
    <a href="profile.php" class="tab"><i class="fas fa-user-md"></i> Profile</a>
</div>
<div class="container">
    <div class="page-header">
        <h1>Prescriptions</h1>
        <p>View and manage patient prescriptions</p>
    </div>
    <div class="action-bar">
        <div></div>
        <a href="add-prescription.php" class="btn-primary"><i class="fas fa-plus"></i> New Prescription</a>
    </div>
    <?php if (count($prescriptions) > 0): ?>
        <?php foreach ($prescriptions as $presc): ?>
        <div class="prescription-card">
            <div class="presc-header">
                <div class="presc-patient">
                    <div class="patient-avatar"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="patient-name"><?php echo htmlspecialchars($presc['patient_name']); ?></div>
                        <div class="patient-meta">Prescription #<?php echo str_pad($presc['id'], 5, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
            </div>
            <div class="presc-details">
                <div class="detail-item">
                    <i class="fas fa-calendar"></i>
                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($presc['issued_date'])); ?>
                </div>
                <?php if (!empty($presc['diagnosis'])): ?>
                <div class="detail-item">
                    <i class="fas fa-stethoscope"></i>
                    <strong>Diagnosis:</strong> <?php echo htmlspecialchars($presc['diagnosis']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($presc['notes'])): ?>
                <div class="detail-item">
                    <i class="fas fa-sticky-note"></i>
                    <strong>Notes:</strong> <?php echo htmlspecialchars($presc['notes']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (count($presc['medicines']) > 0): ?>
            <div class="medicine-list">
                <strong><i class="fas fa-pills"></i> Medications:</strong>
                <?php foreach ($presc['medicines'] as $med): ?>
                    <p style="margin-top: 0.5rem; padding: 0.5rem; background: white; border-radius: 8px;">
                        <strong><?php echo htmlspecialchars($med['medicine_name']); ?></strong>
                        <?php if ($med['dosage']): ?> - <?php echo htmlspecialchars($med['dosage']); ?><?php endif; ?>
                        <?php if ($med['frequency']): ?><br><em>Frequency:</em> <?php echo htmlspecialchars($med['frequency']); ?><?php endif; ?>
                        <?php if ($med['duration']): ?> for <?php echo htmlspecialchars($med['duration']); ?><?php endif; ?>
                        <?php if ($med['instructions']): ?><br><em><?php echo htmlspecialchars($med['instructions']); ?></em><?php endif; ?>
                    </p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-prescription"></i>
            <h3>No prescriptions found</h3>
            <p>You haven't created any prescriptions yet.</p>
        </div>
    <?php endif; ?>
</div>
<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem; font-weight:600;">Valora Medical Center</p>
        <p style="color:var(--n4); font-size:0.9rem;">University Project · All information is fictional</p>
        <p class="footer-copy">&copy; 2026 Valora HMS. All rights reserved.</p>
    </div>
</footer>
</body>
</html>