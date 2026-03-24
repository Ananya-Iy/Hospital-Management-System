<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('patient');

$user_id = $_SESSION['user_id'];

// Get real patient_id from DB
$patient_result = $conn->query("SELECT id FROM patients WHERE user_id = $user_id");
$patient        = $patient_result->fetch_assoc();
$patient_id     = $patient['id'];

// Get all doctors with their specialization name
$doctors_result = $conn->query(
    "SELECT d.id, u.name, s.name AS specialization, d.consultation_fee 
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     JOIN specializations s ON d.specialization_id = s.id
     ORDER BY u.name"
);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id        = intval($_POST['doctor_id']        ?? 0);
    $appointment_date = trim($_POST['appointment_date']   ?? '');
    $appointment_time = trim($_POST['appointment_time']   ?? '');
    $reason           = trim($_POST['reason']             ?? '');

    if (!$doctor_id || empty($appointment_date) || empty($appointment_time)) {
        $error = 'Please fill in all required fields.';
    } elseif ($appointment_date < date('Y-m-d')) {
        $error = 'Please select a future date.';
    } else {
        // Check if this slot is already taken
        $check = $conn->prepare(
            "SELECT id FROM appointments 
             WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
             AND status NOT IN ('Cancelled') LIMIT 1"
        );
        $check->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'This time slot is already booked. Please choose another time.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status) 
                 VALUES (?, ?, ?, ?, ?, 'Requested')"
            );
            $stmt->bind_param("iisss", $patient_id, $doctor_id, $appointment_date, $appointment_time, $reason);

            if ($stmt->execute()) {
                $appointment_id = $conn->insert_id;

                // Get the doctor's consultation fee
                $fee_result = $conn->query("SELECT consultation_fee FROM doctors WHERE id = $doctor_id");
                $fee_row    = $fee_result->fetch_assoc();
                $fee        = $fee_row['consultation_fee'] ?? 0;

                // Generate a unique invoice number e.g. INV-00042
                $inv_number = 'INV-' . str_pad($appointment_id, 5, '0', STR_PAD_LEFT);

                // Due date = appointment date
                $due_date = $appointment_date;

                // Create the invoice automatically
                $inv_stmt = $conn->prepare(
                    "INSERT INTO invoices (patient_id, appointment_id, invoice_number, total_amount, paid_amount, status, due_date)
                     VALUES (?, ?, ?, ?, 0.00, 'Unpaid', ?)"
                );
                $inv_stmt->bind_param("iisds", $patient_id, $appointment_id, $inv_number, $fee, $due_date);
                $inv_stmt->execute();
                $inv_stmt->close();

                $success = 'Appointment booked successfully! We will confirm your appointment shortly.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment · Valora Medical Center</title>
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
            max-width: 900px;
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

        /* ===== ALERTS ===== */
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-success { background: var(--success-light); color: var(--success-deep); border-left: 6px solid var(--success-primary); }
        .alert-error   { background: var(--error-light);   color: var(--error-deep);   border-left: 6px solid var(--error-primary); }
        .alert i { font-size: 1.4rem; }

        /* ===== BOOKING CARD ===== */
        .booking-card {
            background: white;
            border-radius: 40px;
            padding: 3rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--n3);
            position: relative;
            overflow: hidden;
        }

        .booking-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--maroon-300), var(--info-primary), var(--success-primary));
        }

        .booking-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--maroon-50), #F2F2F2);
            border-radius: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.4rem;
            color: var(--maroon-300);
            box-shadow: var(--shadow-md);
        }

        .booking-card h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--n8);
            font-size: 1.8rem;
            font-weight: 700;
        }

        /* ===== STEP INDICATOR ===== */
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0;
            margin-bottom: 2.5rem;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--n5);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .step.active { color: var(--maroon-300); }
        .step.done   { color: var(--success-deep); }

        .step .num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--n3);
            color: var(--n6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .step.active .num { background: var(--maroon-300); color: white; }
        .step.done .num   { background: var(--success-primary); color: white; }

        .step-line {
            width: 40px;
            height: 2px;
            background: var(--n3);
            margin: 0 0.5rem;
        }

        /* ===== SECTION TITLE ===== */
        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--maroon-300);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ===== DOCTOR CARDS ===== */
        .doctors-list { display: flex; flex-direction: column; gap: 0.8rem; }

        .doctor-card {
            border: 2px solid var(--n3);
            border-radius: 20px;
            padding: 1.2rem 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .doctor-card:hover { border-color: var(--maroon-200); background: var(--maroon-50); }
        .doctor-card.selected { border-color: var(--maroon-300); background: var(--maroon-50); box-shadow: 0 4px 16px rgba(132,38,70,0.15); }
        .doctor-card input[type="radio"] { display: none; }

        .doctor-left { display: flex; align-items: center; gap: 1rem; }

        .doctor-avatar {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--maroon-50), var(--info-light));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon-300);
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .doctor-name { font-weight: 700; font-size: 1rem; margin-bottom: 0.2rem; }
        .doctor-spec { color: var(--info-primary); font-size: 0.9rem; font-weight: 500; }
        .doctor-fee  { font-weight: 700; color: var(--success-deep); font-size: 1.1rem; }

        .no-doctors {
            text-align: center;
            padding: 3rem;
            color: var(--n5);
        }

        .no-doctors i { font-size: 3rem; display: block; margin-bottom: 1rem; }

        /* ===== FORM STYLES ===== */
        .form-group { margin-bottom: 1.5rem; }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.6rem;
            color: var(--n7);
            font-size: 0.95rem;
        }

        .form-group label i { color: var(--maroon-300); margin-right: 0.4rem; }

        .form-control {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid var(--n3);
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s;
            background: var(--n1);
            color: var(--n8);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--maroon-300);
            background: white;
            box-shadow: 0 8px 16px -8px var(--maroon-200);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23842646' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1.2rem center;
            background-size: 1.2rem;
        }

        textarea.form-control { min-height: 130px; resize: vertical; }

        /* ===== BUTTONS ===== */
        .btn-row { display: flex; gap: 1rem; margin-top: 2rem; }

        .btn {
            flex: 1;
            padding: 1rem;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--maroon-300), var(--maroon-400));
            color: white;
            box-shadow: 0 8px 16px -4px var(--maroon-200);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--maroon-400), var(--maroon-500));
            transform: translateY(-2px);
            box-shadow: 0 16px 24px -8px var(--maroon-200);
        }

        .btn-secondary {
            background: var(--n2);
            color: var(--n7);
        }

        .btn-secondary:hover { background: var(--n3); }

        /* ===== SUCCESS SCREEN ===== */
        .success-screen {
            text-align: center;
            padding: 2rem;
        }

        .success-screen .check-icon {
            width: 100px;
            height: 100px;
            background: var(--success-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: var(--success-deep);
        }

        .success-screen h3 { font-size: 1.8rem; margin-bottom: 1rem; color: var(--n8); }
        .success-screen p  { color: var(--n6); margin-bottom: 2rem; }

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
            .booking-card { padding: 1.5rem; }
            .step-line { width: 20px; }
            .step span:last-child { display: none; }
            .btn-row { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <nav>
       <a href="patient-dashboard.php" class="logo">Valora</a> 
        <ul class="nav-links">
           
            <li><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</header>

<!-- ===== TABS ===== -->
<div class="main-tabs">
    <a href="appointments.php" class="tab active">
        <i class="fas fa-calendar-plus"></i> Book Appointment
    </a>
    <a href="finddoc.php" class="tab">
        <i class="fas fa-user-md"></i> Find Doctors
    </a>
    <a href="myappointments.php" class="tab">
        <i class="fas fa-calendar-alt"></i> My Appointments
    </a>
    <a href="reports.php" class="tab">
        <i class="fas fa-file-medical"></i> Medical Records
    </a>
    <a href="billings.php" class="tab">
        <i class="fas fa-file-invoice"></i> Billing
    </a>
    <a href="profile.php" class="tab">
        <i class="fas fa-user"></i> Profile
    </a>
</div>

<div class="container">
    <div class="page-header">
        <h1>Book Appointment</h1>
        <p>Schedule your visit with our specialist doctors</p>
    </div>

    <?php if ($success): ?>
    <!-- SUCCESS SCREEN -->
    <div class="booking-card">
        <div class="success-screen">
            <div class="check-icon"><i class="fas fa-check"></i></div>
            <h3>Appointment Booked!</h3>
            <p><?php echo htmlspecialchars($success); ?></p>
            <div class="btn-row" style="max-width: 500px; margin: 0 auto;">
                <a href="billings.php" class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Pay Now
                </a>
                <a href="myappointments.php" class="btn btn-secondary">
                    <i class="fas fa-calendar-alt"></i> My Appointments
                </a>
                <a href="appointments.php" class="btn btn-secondary">
                    <i class="fas fa-plus"></i> Book Another
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- BOOKING CARD -->
    <div class="booking-card">
        <div class="booking-icon"><i class="fas fa-calendar-check"></i></div>
        <h2>Schedule Your Visit</h2>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="ind1">
                <span class="num">1</span>
                <span>Select Doctor</span>
            </div>
            <div class="step-line"></div>
            <div class="step" id="ind2">
                <span class="num">2</span>
                <span>Date & Time</span>
            </div>
            <div class="step-line"></div>
            <div class="step" id="ind3">
                <span class="num">3</span>
                <span>Confirm</span>
            </div>
        </div>

        <form method="POST" action="" id="bookingForm">

            <!-- STEP 1: SELECT DOCTOR -->
            <div id="step1">
                <p class="section-title"><i class="fas fa-user-md"></i> Choose a Doctor</p>

                <?php if ($doctors_result && $doctors_result->num_rows > 0): ?>
                    <div class="doctors-list">
                        <?php while ($doc = $doctors_result->fetch_assoc()): ?>
                        <label class="doctor-card">
                            <input type="radio" name="doctor_id" value="<?php echo $doc['id']; ?>" required>
                            <div class="doctor-left">
                                <div class="doctor-avatar"><i class="fas fa-user-md"></i></div>
                                <div>
                                    <div class="doctor-name">Dr. <?php echo htmlspecialchars($doc['name']); ?></div>
                                    <div class="doctor-spec"><?php echo htmlspecialchars($doc['specialization']); ?></div>
                                </div>
                            </div>
                            <div class="doctor-fee">BD <?php echo number_format($doc['consultation_fee'], 2); ?></div>
                        </label>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-doctors">
                        <i class="fas fa-user-md"></i>
                        <p>No doctors available at the moment.</p>
                    </div>
                <?php endif; ?>

                <div class="btn-row">
                    <button type="button" class="btn btn-primary" onclick="goToStep(2)">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: DATE & TIME -->
            <div id="step2" style="display:none;">
                <p class="section-title"><i class="fas fa-calendar"></i> Pick Date & Time</p>

                <div class="form-group">
                    <label><i class="fas fa-calendar-day"></i> Appointment Date</label>
                    <input type="date" name="appointment_date" class="form-control"
                           min="<?php echo date('Y-m-d'); ?>" required id="appointmentDateInput"
                           onchange="loadSlots()">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Preferred Time Slot <span style="color:var(--n5);font-weight:400;font-size:.85rem">(30 min consultations)</span></label>
                    <select name="appointment_time" class="form-control" required id="timeSlotSelect">
                        <option value="">Select a date first</option>
                    </select>
                    <p id="slotNote" style="font-size:.85rem;color:var(--n5);margin-top:.5rem;display:none">
                        <i class="fas fa-info-circle"></i> Showing available slots · <span style="color:var(--ed)">🔒 Booked</span> slots cannot be selected.
                    </p>
                </div>

                <div class="btn-row">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 3: REASON & CONFIRM -->
            <div id="step3" style="display:none;">
                <p class="section-title"><i class="fas fa-notes-medical"></i> Additional Info</p>

                <div class="form-group">
                    <label><i class="fas fa-comment-medical"></i> Reason for Visit <span style="color:var(--n5); font-weight:400;">(optional)</span></label>
                    <textarea name="reason" class="form-control"
                              placeholder="Describe your symptoms or reason for the appointment..."></textarea>
                </div>

                <div class="btn-row">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Confirm Booking
                    </button>
                </div>
            </div>

        </form>
    </div>
    <?php endif; ?>
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
    // ===== STEP NAVIGATION =====
    function goToStep(step) {
        // Validate step 1 (must select a doctor)
        if (step === 2) {
            const selected = document.querySelector('input[name="doctor_id"]:checked');
            if (!selected) {
                alert('Please select a doctor to continue.');
                return;
            }
        }

        // Validate step 2 (must pick date and time)
        if (step === 3) {
            const date = document.querySelector('input[name="appointment_date"]').value;
            const time = document.querySelector('select[name="appointment_time"]').value;
            if (!date || !time) {
                alert('Please select both a date and a time slot.');
                return;
            }
        }

        // Hide all steps
        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step3').style.display = 'none';

        // Show target step
        document.getElementById('step' + step).style.display = 'block';

        // Update indicators
        for (let i = 1; i <= 3; i++) {
            const ind = document.getElementById('ind' + i);
            ind.classList.remove('active', 'done');
            if (i < step)  ind.classList.add('done');
            if (i === step) ind.classList.add('active');
        }

        // Scroll to top of card
        document.querySelector('.booking-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ===== DOCTOR CARD SELECTION =====
    document.querySelectorAll('.doctor-card').forEach(card => {
        card.addEventListener('click', function () {
            document.querySelectorAll('.doctor-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
            // Reset slots when doctor changes
            const sel = document.getElementById('timeSlotSelect');
            sel.innerHTML = '<option value="">Select a date first</option>';
            document.getElementById('slotNote').style.display = 'none';
            const di = document.getElementById('appointmentDateInput');
            if (di && di.value) loadSlots();
        });
    });

    // ===== DYNAMIC TIME SLOTS =====
    function loadSlots() {
        const dateVal   = document.getElementById('appointmentDateInput').value;
        const docRadio  = document.querySelector('input[name="doctor_id"]:checked');
        const sel       = document.getElementById('timeSlotSelect');
        const note      = document.getElementById('slotNote');

        if (!dateVal || !docRadio) {
            sel.innerHTML = '<option value="">Select a doctor first</option>';
            return;
        }

        sel.innerHTML = '<option value="">Loading available slots...</option>';
        sel.disabled = true;

        fetch(`get-slots.php?doctor_id=${docRadio.value}&date=${dateVal}`)
            .then(r => r.json())
            .then(data => {
                sel.disabled = false;
                if (data.error) {
                    sel.innerHTML = `<option value="">${data.error}</option>`;
                    note.style.display = 'none';
                } else if (data.slots.length === 0) {
                    sel.innerHTML = '<option value="">No available slots on this date</option>';
                    note.style.display = 'none';
                } else {
                    sel.innerHTML = '<option value="">-- Select a time slot --</option>';
                    data.slots.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.value;
                        opt.textContent = s.label; // already "9:00 AM – 9:30 AM"
                        sel.appendChild(opt);
                    });
                    note.style.display = 'block';
                    note.innerHTML = `<i class="fas fa-check-circle" style="color:var(--success-primary)"></i> ${data.slots.length} slot${data.slots.length !== 1 ? 's' : ''} available — booked slots are hidden.`;
                }
            })
            .catch(() => {
                sel.disabled = false;
                sel.innerHTML = '<option value="">Could not load slots. Try again.</option>';
            });
    }
</script>
</body>
</html>