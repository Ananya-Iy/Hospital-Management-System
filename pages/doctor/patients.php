<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];

// Get doctor record
$d_result  = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id");
$doctor    = $d_result->fetch_assoc();
$doctor_id = $doctor['id'] ?? null;

// Get all patients who have had appointments with this doctor
$patients = [];
if ($doctor_id) {
    $patient_result = $conn->query(
        "SELECT DISTINCT p.id, u.name, u.phone, p.date_of_birth as dob, p.gender, p.blood_group, p.address,
         (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND doctor_id = $doctor_id) as total_visits,
         (SELECT MAX(appointment_date) FROM appointments WHERE patient_id = p.id AND doctor_id = $doctor_id) as last_visit
         FROM patients p
         JOIN users u ON p.user_id = u.id
         JOIN appointments a ON a.patient_id = p.id
         WHERE a.doctor_id = $doctor_id
         ORDER BY last_visit DESC"
    );
    while ($row = $patient_result->fetch_assoc()) {
        $patients[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records · Valora Medical Center</title>
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
            --error-light: #E2D0CD; --error-primary: #F04233; --error-deep: #B03125;
            --info-light: #C9D3E6; --info-primary: #0E3E9E;
            --bg-body: #F8F9FC;
            --shadow-md: 0 8px 16px rgba(0,0,0,0.04);
            --shadow-xl: 0 24px 32px rgba(100,23,50,0.08);
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
            bottom: -4px;
            left: 0;
            width: 40%;
            height: 3px;
            background: var(--maroon-300);
            border-radius: 4px;
        }

        .nav-links { display: flex; gap: 2rem; list-style: none; align-items: center; }
        .nav-links a { color: var(--n7); font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--maroon-300); }

        .logout-btn {
            background: var(--n2);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: var(--n7) !important;
        }

        .logout-btn:hover { background: var(--n3); }

        /* ===== TABS ===== */
        .main-tabs {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 1.5rem auto;
            padding: 0.5rem;
            background: white;
            border-radius: 100px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            max-width: 960px;
            border: 1px solid var(--n3);
            flex-wrap: wrap;
        }

        .tab {
            padding: 0.7rem 1.4rem;
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

        /* ===== CONTAINER ===== */
        .container {
            max-width: 1200px;
            margin: 1rem auto 3rem;
            padding: 0 2rem;
            flex: 1;
            width: 100%;
        }

        /* ===== PAGE HEADER ===== */
        .page-header { margin-bottom: 2rem; }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--n8);
            position: relative;
            padding-bottom: 0.8rem;
        }

        .page-header h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--maroon-300), var(--info-primary));
            border-radius: 4px;
        }

        .page-header p { color: var(--n6); margin-top: 0.8rem; }

        /* ===== SEARCH BOX ===== */
        .search-box {
            background: white;
            border-radius: 100px;
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--n3);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-md);
        }

        .search-box i { color: var(--maroon-300); font-size: 1.2rem; }

        .search-box input {
            border: none;
            outline: none;
            flex: 1;
            font-size: 1rem;
            font-family: inherit;
        }

        /* ===== PATIENT CARD ===== */
        .patient-card {
            background: white;
            border-radius: 24px;
            padding: 1.8rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--n3);
            box-shadow: var(--shadow-md);
            transition: all 0.2s;
        }

        .patient-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-xl); }

        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .patient-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .patient-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--maroon-50), var(--info-light));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--maroon-300);
            flex-shrink: 0;
        }

        .patient-name { font-weight: 700; font-size: 1.3rem; margin-bottom: 0.3rem; }
        .patient-meta { color: var(--n6); font-size: 0.9rem; }

        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            padding: 1.2rem;
            background: var(--n1);
            border-radius: 16px;
            margin-bottom: 1rem;
        }

        .detail-item i { color: var(--maroon-300); margin-right: 0.5rem; }
        .detail-item strong { font-weight: 600; }

        .patient-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border-radius: 100px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary { background: var(--maroon-300); color: white; }
        .btn-primary:hover { background: var(--maroon-400); }

        .btn-secondary { background: var(--info-light); color: var(--info-primary); }
        .btn-secondary:hover { background: var(--info-primary); color: white; }

        /* ===== STATS BADGE ===== */
        .stats-badge {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background: white;
            border-radius: 100px;
            border: 1px solid var(--n3);
        }

        .stat-item i { color: var(--maroon-300); }
        .stat-item strong { font-weight: 700; color: var(--n8); }
        .stat-item span { color: var(--n6); font-size: 0.9rem; }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-md);
        }

        .empty-state i { font-size: 4rem; color: var(--n4); display: block; margin-bottom: 1.5rem; }
        .empty-state h3 { font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--n7); }
        .empty-state p { color: var(--n5); }

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
            nav { flex-direction: column; gap: 1rem; }
            .nav-links { flex-direction: column; text-align: center; gap: 1rem; }
            .patient-header { flex-direction: column; }
            .patient-details { grid-template-columns: 1fr; }
            .patient-actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <nav>
        <a href="../../index.php" class="logo">Valora</a>
        <ul class="nav-links">
            <li><a href="doctor-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</header>

<!-- ===== TABS ===== -->
<div class="main-tabs">
    <a href="appointments.php" class="tab">
        <i class="fas fa-calendar-alt"></i> Appointments
    </a>
    <a href="patients.php" class="tab active">
        <i class="fas fa-users"></i> Patients
    </a>
    <a href="prescriptions.php" class="tab">
        <i class="fas fa-prescription"></i> Prescriptions
    </a>
    <a href="availability.php" class="tab">
        <i class="fas fa-calendar-times"></i> Schedule
    </a>
    <a href="profile.php" class="tab">
        <i class="fas fa-user-md"></i> Profile
    </a>
</div>

<div class="container">
    <div class="page-header">
        <h1>Patient Records</h1>
        <p>View and manage your patient medical histories</p>
    </div>

    <!-- SEARCH BOX -->
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Search patients by name..." onkeyup="searchPatients()">
    </div>

    <!-- PATIENTS LIST -->
    <div id="patientsList">
    <?php if (count($patients) > 0): ?>
        <?php foreach ($patients as $patient): 
            $age = $patient['dob'] ? date_diff(date_create($patient['dob']), date_create('today'))->y : 'N/A';
        ?>
        <div class="patient-card" data-name="<?php echo strtolower($patient['name']); ?>">
            <div class="patient-header">
                <div class="patient-info">
                    <div class="patient-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></div>
                        <div class="patient-meta">
                            Patient ID: #P-<?php echo str_pad($patient['id'], 5, '0', STR_PAD_LEFT); ?>
                        </div>
                    </div>
                </div>
                <div class="stats-badge">
                    <div class="stat-item">
                        <i class="fas fa-calendar-check"></i>
                        <strong><?php echo $patient['total_visits']; ?></strong>
                        <span>Visits</span>
                    </div>
                </div>
            </div>

            <div class="patient-details">
                <div class="detail-item">
                    <i class="fas fa-birthday-cake"></i>
                    <strong>Age:</strong> <?php echo $age; ?> years
                </div>
                <div class="detail-item">
                    <i class="fas fa-venus-mars"></i>
                    <strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?>
                </div>
                <div class="detail-item">
                    <i class="fas fa-tint"></i>
                    <strong>Blood Group:</strong> <?php echo htmlspecialchars($patient['blood_group'] ?? 'N/A'); ?>
                </div>
                <div class="detail-item">
                    <i class="fas fa-phone"></i>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?>
                </div>
                <div class="detail-item">
                    <i class="fas fa-calendar"></i>
                    <strong>Last Visit:</strong> <?php echo $patient['last_visit'] ? date('M d, Y', strtotime($patient['last_visit'])) : 'N/A'; ?>
                </div>
            </div>

            <div class="patient-actions">
                <a href="view-patient.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-folder-open"></i> View Full Record
                </a>
                <a href="add-prescription.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-prescription"></i> New Prescription
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>No patients found</h3>
            <p>You don't have any patient records yet. They will appear here after appointments.</p>
        </div>
    <?php endif; ?>
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

<script>
function searchPatients() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const cards = document.querySelectorAll('.patient-card');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        if (name.includes(filter)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

</body>
</html>