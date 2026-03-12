<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];
$d_result = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id");
$doctor = $d_result->fetch_assoc();
$doctor_id = $doctor['id'] ?? null;

$success = '';
$error = '';

// Handle adding unavailable dates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave'])) {
    $leave_date = trim($_POST['leave_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($leave_date)) {
        $error = 'Please select a date.';
    } elseif ($leave_date < date('Y-m-d')) {
        $error = 'Cannot set leave for past dates.';
    } else {
        // Check if already marked as unavailable
        $check = $conn->prepare("SELECT id FROM doctor_availability WHERE doctor_id = ? AND date = ? LIMIT 1");
        $check->bind_param("is", $doctor_id, $leave_date);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'This date is already marked as unavailable.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO doctor_availability (doctor_id, date, is_available, reason) 
                 VALUES (?, ?, 0, ?)"
            );
            $stmt->bind_param("iss", $doctor_id, $leave_date, $reason);
            
            if ($stmt->execute()) {
                $success = 'Leave date added successfully!';
            } else {
                $error = 'Failed to add leave date.';
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle removing leave
if (isset($_GET['remove_id'])) {
    $remove_id = intval($_GET['remove_id']);
    $stmt = $conn->prepare("DELETE FROM doctor_availability WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $remove_id, $doctor_id);
    if ($stmt->execute()) {
        $success = 'Leave date removed successfully.';
    }
    $stmt->close();
}

// Get unavailable dates
$unavailable_dates = [];
if ($doctor_id) {
    $av_result = $conn->query(
        "SELECT * FROM doctor_availability 
         WHERE doctor_id = $doctor_id AND is_available = 0
         ORDER BY date ASC"
    );
    while ($row = $av_result->fetch_assoc()) {
        $unavailable_dates[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule · Valora Medical Center</title>
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
            --warning-light: #E5D8C8;
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
        .container { max-width: 1000px; margin: 1rem auto 3rem; padding: 0 2rem; flex: 1; width: 100%; }
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: 2.2rem; font-weight: 700; color: var(--n8); position: relative; padding-bottom: 0.8rem; }
        .page-header h1::after { content: ''; position: absolute; bottom: 0; left: 0; width: 80px; height: 4px; background: linear-gradient(90deg, var(--maroon-300), var(--info-primary)); border-radius: 4px; }
        .page-header p { color: var(--n6); margin-top: 0.8rem; }
        .alert { padding: 1.2rem 1.5rem; border-radius: 16px; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: var(--success-light); color: var(--success-deep); border-left: 6px solid var(--success-primary); }
        .alert-error { background: var(--error-light); color: var(--error-deep); border-left: 6px solid var(--error-primary); }
        .alert i { font-size: 1.4rem; }
        .section { background: white; border-radius: 24px; padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-md); border: 1px solid var(--n3); }
        .section-title { font-size: 1.3rem; font-weight: 700; color: var(--maroon-300); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.6rem; color: var(--n7); }
        .form-group label i { color: var(--maroon-300); margin-right: 0.4rem; }
        .form-control { width: 100%; padding: 1rem 1.2rem; border: 2px solid var(--n3); border-radius: 16px; font-size: 1rem; transition: all 0.3s; background: var(--n1); font-family: inherit; }
        .form-control:focus { outline: none; border-color: var(--maroon-300); background: white; }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .btn { padding: 0.9rem 2rem; border-radius: 100px; font-weight: 700; font-size: 1rem; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.6rem; }
        .btn-primary { background: linear-gradient(135deg, var(--maroon-300), var(--maroon-400)); color: white; box-shadow: 0 8px 16px -4px var(--maroon-200); }
        .btn-primary:hover { background: linear-gradient(135deg, var(--maroon-400), var(--maroon-500)); transform: translateY(-2px); }
        .leave-card { background: var(--warning-light); border-radius: 16px; padding: 1.5rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .leave-info i { color: var(--maroon-300); margin-right: 0.6rem; }
        .leave-info strong { font-weight: 700; }
        .leave-reason { color: var(--n6); font-size: 0.9rem; margin-top: 0.5rem; }
        .btn-remove { background: var(--error-light); color: var(--error-deep); padding: 0.6rem 1.2rem; border-radius: 100px; font-size: 0.85rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-remove:hover { background: var(--error-primary); color: white; }
        .empty-state { text-align: center; padding: 3rem 2rem; color: var(--n5); }
        .empty-state i { font-size: 3rem; display: block; margin-bottom: 1rem; }
        footer { background: var(--n7); color: var(--n2); padding: 2rem; text-align: center; border-top: 5px solid var(--maroon-300); margin-top: auto; }
        .footer-content { max-width: 1400px; margin: 0 auto; }
        .footer-copy { color: var(--n5); font-size: 0.85rem; margin-top: 0.5rem; }
        @media (max-width: 768px) { nav { flex-direction: column; gap: 1rem; } .nav-links { flex-direction: column; text-align: center; gap: 1rem; } .leave-card { flex-direction: column; align-items: flex-start; } }
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
    <a href="prescriptions.php" class="tab"><i class="fas fa-prescription"></i> Prescriptions</a>
    <a href="availability.php" class="tab active"><i class="fas fa-calendar-times"></i> Schedule</a>
    <a href="profile.php" class="tab"><i class="fas fa-user-md"></i> Profile</a>
</div>
<div class="container">
    <div class="page-header">
        <h1>Manage Schedule</h1>
        <p>Set your unavailable dates and manage your availability</p>
    </div>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="section">
        <h2 class="section-title"><i class="fas fa-calendar-plus"></i> Add Leave / Unavailable Date</h2>
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Select Date</label>
                <input type="date" name="leave_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-comment"></i> Reason (optional)</label>
                <textarea name="reason" class="form-control" placeholder="e.g., Personal leave, Conference, Vacation..."></textarea>
            </div>
            <button type="submit" name="add_leave" class="btn btn-primary"><i class="fas fa-save"></i> Add Unavailable Date</button>
        </form>
    </div>
    <div class="section">
        <h2 class="section-title"><i class="fas fa-list"></i> Unavailable Dates</h2>
        <?php if (count($unavailable_dates) > 0): ?>
            <?php foreach ($unavailable_dates as $leave): ?>
            <div class="leave-card">
                <div>
                    <div class="leave-info">
                        <i class="fas fa-calendar-times"></i>
                        <strong><?php echo date('l, M d, Y', strtotime($leave['date'])); ?></strong>
                    </div>
                    <?php if (!empty($leave['reason'])): ?>
                        <div class="leave-reason">
                            <i class="fas fa-comment"></i> <?php echo htmlspecialchars($leave['reason']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="?remove_id=<?php echo $leave['id']; ?>" class="btn-remove" onclick="return confirm('Remove this unavailable date?');">
                    <i class="fas fa-trash"></i> Remove
                </a>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <p>No unavailable dates set. You are available all days!</p>
            </div>
        <?php endif; ?>
    </div>
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