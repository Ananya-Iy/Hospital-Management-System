<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('patient');

$search      = trim($_GET['search'] ?? '');
$spec_filter = trim($_GET['specialization'] ?? '');
$searched    = isset($_GET['search']) || isset($_GET['specialization']);

// Only query if user has searched
$doctors = [];
if ($searched) {
    $where = "WHERE 1=1";

    if (!empty($search)) {
        $s = $conn->real_escape_string($search);
        $where .= " AND (u.name LIKE '%$s%' OR d.qualification LIKE '%$s%' OR d.bio LIKE '%$s%')";
    }

    if (!empty($spec_filter)) {
        $sf = $conn->real_escape_string($spec_filter);
        $where .= " AND s.name = '$sf'";
    }

    $result = $conn->query(
        "SELECT d.id, d.consultation_fee, d.experience_years, d.qualification, d.bio,
                u.name, u.phone,
                s.name AS specialization
         FROM doctors d
         JOIN users u ON d.user_id = u.id
         JOIN specializations s ON d.specialization_id = s.id
         $where
         ORDER BY u.name ASC"
    );

    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

// Get all specializations for the dropdown
$specs = [];
$spec_result = $conn->query("SELECT DISTINCT name FROM specializations ORDER BY name");
while ($row = $spec_result->fetch_assoc()) {
    $specs[] = $row['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Doctors · Valora Medical Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }

        :root {
            --n1: #F2F2F2; --n2: #E6E6E6; --n3: #DADADA; --n4: #C6C6C6;
            --n5: #9E9E9E; --n6: #6E6E6E; --n7: #3F3F3F; --n8: #1C1C1C;
            --maroon-50: #D8C9CE; --maroon-100: #C5A8B3; --maroon-200: #A56C7E;
            --maroon-300: #842646; --maroon-400: #7A2141; --maroon-500: #641732;
            --success-light: #C6D8D2; --success-deep: #2E955C;
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
            box-shadow: var(--shadow-sm);
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
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .logout-btn:hover { background: var(--n3); }

        /* ===== PAGE WRAPPER ===== */
        .page-wrapper { flex: 1; display: flex; flex-direction: column; }

        .container {
            max-width: 1200px;
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

        /* ===== PAGE HEADER ===== */
        .page-header { margin-bottom: 2rem; }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            position: relative;
            padding-bottom: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .page-header h1::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0;
            width: 80px; height: 4px;
            background: linear-gradient(90deg, var(--maroon-300), var(--info-primary));
            border-radius: 4px;
        }

        .page-header p { color: var(--n6); margin-top: 0.8rem; }

        /* ===== SEARCH SECTION ===== */
        .search-section {
            background: linear-gradient(135deg, var(--maroon-300), var(--maroon-500));
            border-radius: 32px;
            padding: 3rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .search-section::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .search-section::after {
            content: '';
            position: absolute;
            bottom: -60px; left: -60px;
            width: 250px; height: 250px;
            background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .search-section h2 {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .search-section p {
            color: rgba(255,255,255,0.8);
            margin-bottom: 2rem;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        .search-box {
            background: white;
            border-radius: 20px;
            padding: 0.6rem;
            display: flex;
            gap: 0.6rem;
            max-width: 800px;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }

        .search-field {
            flex: 1;
            min-width: 180px;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0 1rem;
            border-right: 1px solid var(--n3);
        }

        .search-field:last-of-type { border-right: none; }

        .search-field i { color: var(--maroon-300); font-size: 1rem; flex-shrink: 0; }

        .search-field input,
        .search-field select {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            outline: none;
            font-family: inherit;
            color: var(--n8);
            padding: 0.7rem 0;
        }

        .search-field select option { color: var(--n8); }

        .search-submit {
            padding: 0.9rem 2rem;
            background: var(--maroon-300);
            color: white;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .search-submit:hover { background: var(--maroon-400); }

        .clear-link {
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
            display: inline-block;
            transition: color 0.2s;
        }

        .clear-link:hover { color: white; }

        /* ===== SPECIALITY CHIPS (quick filter) ===== */
        .spec-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            justify-content: center;
            margin-top: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .spec-chip {
            padding: 0.45rem 1.1rem;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            backdrop-filter: blur(4px);
        }

        .spec-chip:hover,
        .spec-chip.active {
            background: white;
            color: var(--maroon-300);
            border-color: white;
            font-weight: 700;
        }

        /* ===== RESULTS HEADER ===== */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .results-count {
            font-size: 1rem;
            color: var(--n6);
            font-weight: 500;
        }

        .results-count strong { color: var(--maroon-300); }

        /* ===== DOCTOR CARDS ===== */
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.5rem;
        }

        .doctor-card {
            background: white;
            border-radius: 28px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
            transition: all 0.25s;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .doctor-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 5px;
            background: linear-gradient(90deg, var(--maroon-300), var(--info-primary));
        }

        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--maroon-100);
        }

        .doctor-top {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .doctor-avatar {
            width: 68px; height: 68px;
            border-radius: 22px;
            background: linear-gradient(135deg, var(--maroon-50), var(--info-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--maroon-300);
            flex-shrink: 0;
        }

        .doctor-name { font-size: 1.15rem; font-weight: 700; color: var(--n8); margin-bottom: 0.4rem; }

        .doctor-spec {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--info-light);
            color: var(--info-primary);
            padding: 0.3rem 0.9rem;
            border-radius: 40px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .doctor-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
            margin-bottom: 1.2rem;
        }

        .meta-item {
            background: var(--n1);
            border-radius: 14px;
            padding: 0.8rem;
            text-align: center;
        }

        .meta-label { font-size: 0.75rem; color: var(--n5); margin-bottom: 0.2rem; }
        .meta-value { font-weight: 700; color: var(--n8); font-size: 0.9rem; }

        .doctor-bio {
            color: var(--n6);
            font-size: 0.88rem;
            line-height: 1.7;
            margin-bottom: 1.2rem;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .doctor-contact {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--n6);
            font-size: 0.88rem;
            margin-bottom: 1.2rem;
        }

        .doctor-contact i { color: var(--maroon-300); }

        .doctor-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1.2rem;
            border-top: 1px solid var(--n3);
            margin-top: auto;
        }

        .fee-label { font-size: 0.82rem; color: var(--n5); margin-bottom: 0.1rem; }
        .fee-value { font-size: 1.4rem; font-weight: 800; color: var(--success-deep); }

        .book-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--maroon-300);
            color: white;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .book-btn:hover { background: var(--maroon-400); transform: translateY(-2px); box-shadow: 0 8px 16px -4px var(--maroon-200); }

        /* ===== LANDING / NO SEARCH STATE ===== */
        .landing-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .landing-state i { font-size: 5rem; color: var(--n3); display: block; margin-bottom: 1.5rem; }
        .landing-state h3 { font-size: 1.5rem; color: var(--n5); margin-bottom: 0.5rem; }
        .landing-state p  { color: var(--n5); }

        /* ===== EMPTY SEARCH RESULTS ===== */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 28px;
            border: 2px dashed var(--n4);
            grid-column: 1 / -1;
        }

        .empty-state i { font-size: 4rem; color: var(--n4); display: block; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.4rem; color: var(--n6); margin-bottom: 0.5rem; }
        .empty-state p  { color: var(--n5); }

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
            .tab { padding: 0.6rem 1rem; font-size: 0.85rem; }
            .doctors-grid { grid-template-columns: 1fr; }
            .search-box { flex-direction: column; }
            .search-field { border-right: none; border-bottom: 1px solid var(--n3); padding: 0 0.5rem; }
            .search-field:last-of-type { border-bottom: none; }
            .search-section { padding: 2rem 1.2rem; }
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
        <a href="home.php"         class="tab"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
        <a href="appointments.php" class="tab"><i class="fas fa-calendar-alt"></i> My Appointments</a>
        <a href="doctors.php"      class="tab active"><i class="fas fa-user-md"></i> Find Doctors</a>
        <a href="records.php"      class="tab"><i class="fas fa-file-medical"></i> Medical Records</a>
        <a href="billing.php"      class="tab"><i class="fas fa-file-invoice"></i> Billing</a>
        <a href="profile.php"      class="tab"><i class="fas fa-user"></i> Profile</a>
    </div>

    <div class="container">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1>Find Doctors</h1>
            <p>Search by name or browse by specialization</p>
        </div>

        <!-- SEARCH SECTION -->
        <div class="search-section">
            <h2><i class="fas fa-stethoscope"></i> Find the Right Doctor</h2>
            <p>Search by name, or pick a specialization below</p>

            <form method="GET" action="">
                <div class="search-box">
                    <!-- Name search -->
                    <div class="search-field">
                        <i class="fas fa-search"></i>
                        <input type="text"
                               name="search"
                               placeholder="Doctor name..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <!-- Specialization dropdown -->
                    <div class="search-field">
                        <i class="fas fa-stethoscope"></i>
                        <select name="specialization" id="specSelect">
                            <option value="">All Specializations</option>
                            <?php foreach ($specs as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec); ?>"
                                    <?php echo $spec_filter === $spec ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="search-submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>

                <?php if ($searched): ?>
                    <a href="doctors.php" class="clear-link">
                        <i class="fas fa-times-circle"></i> Clear search
                    </a>
                <?php endif; ?>
            </form>

            <!-- Quick specialty chips -->
            <div class="spec-chips">
                <?php foreach ($specs as $spec): ?>
                <a href="?specialization=<?php echo urlencode($spec); ?>"
                   class="spec-chip <?php echo $spec_filter === $spec ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($spec); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RESULTS -->
        <?php if ($searched): ?>

            <div class="results-header">
                <div class="results-count">
                    Found <strong><?php echo count($doctors); ?></strong> doctor<?php echo count($doctors) !== 1 ? 's' : ''; ?>
                    <?php if (!empty($search)): ?>
                        matching "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    <?php endif; ?>
                    <?php if (!empty($spec_filter)): ?>
                        in <strong><?php echo htmlspecialchars($spec_filter); ?></strong>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($doctors) > 0): ?>
            <div class="doctors-grid">
                <?php foreach ($doctors as $doc): ?>
                <div class="doctor-card">
                    <div class="doctor-top">
                        <div class="doctor-avatar"><i class="fas fa-user-md"></i></div>
                        <div>
                            <div class="doctor-name">Dr. <?php echo htmlspecialchars($doc['name']); ?></div>
                            <div class="doctor-spec">
                                <i class="fas fa-stethoscope"></i>
                                <?php echo htmlspecialchars($doc['specialization']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="doctor-meta">
                        <div class="meta-item">
                            <div class="meta-label">Experience</div>
                            <div class="meta-value">
                                <?php echo $doc['experience_years'] ? $doc['experience_years'] . ' yrs' : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Qualification</div>
                            <div class="meta-value" style="font-size:0.8rem;">
                                <?php echo $doc['qualification'] ? htmlspecialchars($doc['qualification']) : 'N/A'; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($doc['bio'])): ?>
                    <div class="doctor-bio"><?php echo htmlspecialchars($doc['bio']); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($doc['phone'])): ?>
                    <div class="doctor-contact">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($doc['phone']); ?>
                    </div>
                    <?php endif; ?>

                    <div class="doctor-footer">
                        <div>
                            <div class="fee-label">Consultation Fee</div>
                            <div class="fee-value">BD <?php echo number_format($doc['consultation_fee'], 2); ?></div>
                        </div>
                        <a href="home.php?doctor_id=<?php echo $doc['id']; ?>" class="book-btn">
                            <i class="fas fa-calendar-plus"></i> Book Now
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-md"></i>
                <h3>No doctors found</h3>
                <p>Try a different name or specialization</p>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Nothing searched yet -->
            <div class="landing-state">
                <i class="fas fa-user-md"></i>
                <h3>Search for a doctor above</h3>
                <p>Type a name, or tap a specialization to browse doctors</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem; font-weight:600;">Valora Medical Center</p>
        <p style="color:var(--n4); font-size:0.9rem;">University Project · All information is fictional</p>
        <p class="footer-copy">&copy; 2026 Valora HMS. All rights reserved.</p>
    </div>
</footer>

</body>
</html>