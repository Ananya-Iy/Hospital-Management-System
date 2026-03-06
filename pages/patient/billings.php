<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('patient');

$user_id    = $_SESSION['user_id'];
$patient    = $conn->query("SELECT * FROM patients WHERE user_id = $user_id")->fetch_assoc();
$patient_id = $patient['id'];

$payment_success = '';
$payment_error   = '';

// ── Handle payment ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice'])) {
    $invoice_id     = intval($_POST['invoice_id']);
    $payment_method = sanitize($_POST['payment_method'] ?? '');

    // Make sure the invoice belongs to this patient
    $check = $conn->query(
        "SELECT i.* FROM invoices i
         WHERE i.id = $invoice_id AND i.patient_id = $patient_id
         LIMIT 1"
    );

    if ($check && $check->num_rows > 0) {
        $inv = $check->fetch_assoc();

        if ($inv['status'] === 'Paid') {
            $payment_error = 'This invoice is already paid.';

        } elseif ($payment_method === 'card') {
            $card_number = sanitize($_POST['card_number'] ?? '');
            $card_expiry = sanitize($_POST['card_expiry'] ?? '');
            $card_cvv    = sanitize($_POST['card_cvv']    ?? '');
            $card_name   = sanitize($_POST['card_name']   ?? '');

            if (empty($card_number) || empty($card_expiry) || empty($card_cvv) || empty($card_name)) {
                $payment_error = 'Please fill in all card details.';
            } elseif (strlen(preg_replace('/\D/', '', $card_number)) !== 16) {
                $payment_error = 'Invalid card number. Must be 16 digits.';
            } elseif (strlen($card_cvv) < 3) {
                $payment_error = 'Invalid CVV.';
            } else {
                $stmt = $conn->prepare(
                   "UPDATE invoices SET status = 'Paid', paid_amount = total_amount, payment_method = 'Card'
 WHERE id = ? AND patient_id = ?"
                );
                $stmt->bind_param("ii", $invoice_id, $patient_id);
                if ($stmt->execute()) {
                    $payment_success = 'Payment successful! Your invoice has been marked as paid.';
                } else {
                    $payment_error = 'Payment failed. Please try again.';
                }
                $stmt->close();
            }

        } else {
            // Cash — mark as pending, they pay at reception
            $stmt = $conn->prepare(
                "UPDATE invoices SET payment_method = 'Cash'
 WHERE id = ? AND patient_id = ?"
            );
            $stmt->bind_param("ii", $invoice_id, $patient_id);
            if ($stmt->execute()) {
                $payment_success = 'Noted! Please arrive 15 minutes early to pay at the reception desk.';
            } else {
                $payment_error = 'Failed to process. Please try again.';
            }
            $stmt->close();
        }

    } else {
        $payment_error = 'Invalid invoice.';
    }
}

// ── Fetch all invoices ───────────────────────────────────────────────────────
// Your schema: invoices(id, patient_id, appointment_id, invoice_number,
//              total_amount, paid_amount, status, due_date, created_at)
//              + payment_method column we added via UPDATE above
$invoices_raw = [];
$inv_result = $conn->query(
    "SELECT i.*,
            a.appointment_date, a.appointment_time,
            u.name  AS doctor_name,
            s.name  AS specialization,
            d.consultation_fee
     FROM invoices i
     JOIN appointments a  ON i.appointment_id  = a.id
     JOIN doctors d       ON a.doctor_id       = d.id
     JOIN users u         ON d.user_id         = u.id
     JOIN specializations s ON d.specialization_id = s.id
     WHERE i.patient_id = $patient_id
     ORDER BY i.id DESC"
);

$total_paid    = 0;
$total_unpaid  = 0;
$total_overdue = 0;
$total_count   = 0;

while ($row = $inv_result->fetch_assoc()) {
    $invoices_raw[] = $row;
    $total_count++;
    if ($row['status'] === 'Paid') {
        $total_paid    += $row['total_amount'];
    } elseif ($row['status'] === 'Overdue') {
        $total_overdue += $row['total_amount'];
    } else {
        $total_unpaid  += $row['total_amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing · Valora Medical Center</title>
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
            --warning-light: #E5D8C8; --warning-primary: #F48B05; --warning-deep: #B36805;
            --error-light: #E2D0CD; --error-primary: #F04233; --error-deep: #B03125;
            --info-light: #C9D3E6; --info-primary: #0E3E9E; --info-deep: #082E73;
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

        /* ── HEADER ── */
        header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0; z-index: 50;
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
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .logout-btn:hover { background: var(--n3); }

        /* ── WRAPPER ── */
        .page-wrapper { flex: 1; display: flex; flex-direction: column; }

        .container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
        }

        /* ── TABS ── */
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

        /* ── PAGE HEADER ── */
        .page-header { margin-bottom: 2rem; }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            position: relative;
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

        /* ── ALERTS ── */
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
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

        /* ── STATS ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
            display: flex;
            align-items: center;
            gap: 1.2rem;
            transition: all 0.2s;
        }

        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-xl); }

        .stat-icon {
            width: 60px; height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
        }

        .stat-icon.green  { background: var(--success-light); color: var(--success-deep); }
        .stat-icon.orange { background: var(--warning-light); color: var(--warning-deep); }
        .stat-icon.red    { background: var(--error-light);   color: var(--error-deep); }
        .stat-icon.blue   { background: var(--info-light);    color: var(--info-primary); }

        .stat-info p  { font-size: 0.85rem; color: var(--n5); margin-bottom: 0.2rem; }
        .stat-info h3 { font-size: 1.8rem; font-weight: 700; }

        /* ── INVOICE GRID ── */
        .invoices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
        }

        .invoice-card {
            background: white;
            border-radius: 24px;
            padding: 1.8rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--n3);
            transition: all 0.25s;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .invoice-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 6px; height: 100%;
        }

        .invoice-card.paid::before    { background: linear-gradient(180deg, var(--success-primary), var(--success-deep)); }
        .invoice-card.unpaid::before  { background: linear-gradient(180deg, var(--warning-primary), var(--warning-deep)); }
        .invoice-card.overdue::before { background: linear-gradient(180deg, var(--error-primary),   var(--error-deep)); }

        .invoice-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-xl); border-color: var(--maroon-100); }

        /* Top row */
        .inv-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }

        .inv-number {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--n5);
            background: var(--n1);
            padding: 0.3rem 1rem;
            border-radius: 40px;
        }

        .inv-badge {
            padding: 0.35rem 1rem;
            border-radius: 100px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .inv-badge.paid    { background: var(--success-light); color: var(--success-deep); }
        .inv-badge.unpaid  { background: var(--warning-light); color: var(--warning-deep); }
        .inv-badge.overdue { background: var(--error-light);   color: var(--error-deep); }

        /* Doctor row */
        .inv-doctor {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            margin-bottom: 1.2rem;
        }

        .doc-avatar {
            width: 48px; height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--maroon-50), var(--info-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon-300);
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .doc-name { font-weight: 700; font-size: 1rem; margin-bottom: 0.1rem; }
        .doc-spec { color: var(--info-primary); font-size: 0.88rem; font-weight: 500; }

        /* Details box */
        .inv-details {
            background: var(--n1);
            border-radius: 16px;
            padding: 1.1rem 1.2rem;
            margin-bottom: 1.2rem;
            flex: 1;
        }

        .det-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px dashed var(--n3);
        }

        .det-row:last-child { border-bottom: none; padding-top: 0.7rem; }

        .det-label { color: var(--n6); font-size: 0.88rem; }
        .det-value { font-weight: 600; font-size: 0.9rem; }
        .det-total { font-size: 1.3rem; font-weight: 800; color: var(--maroon-300); }

        /* Footer */
        .inv-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--n2);
            margin-top: auto;
        }

        .paid-info { color: var(--success-deep); font-size: 0.88rem; display: flex; align-items: center; gap: 0.4rem; }
        .due-info  { color: var(--warning-deep); font-size: 0.88rem; display: flex; align-items: center; gap: 0.4rem; }

        .pay-btn {
            padding: 0.65rem 1.5rem;
            background: var(--maroon-300);
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-family: inherit;
        }

        .pay-btn:hover { background: var(--maroon-400); transform: translateY(-2px); }

        .pay-btn.disabled {
            background: var(--n3);
            color: var(--n5);
            cursor: not-allowed;
            transform: none;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 28px;
            border: 2px dashed var(--n4);
        }

        .empty-state i { font-size: 4rem; color: var(--n4); display: block; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.4rem; color: var(--n6); margin-bottom: 0.5rem; }
        .empty-state p  { color: var(--n5); }

        /* ── PAYMENT MODAL ── */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active { display: flex; }

        .modal-box {
            background: white;
            border-radius: 32px;
            padding: 2.5rem;
            max-width: 480px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            position: relative;
            animation: popIn 0.25s ease;
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.95) translateY(-10px); }
            to   { opacity: 1; transform: scale(1)    translateY(0); }
        }

        .modal-close {
            position: absolute;
            top: 1.5rem; right: 1.5rem;
            font-size: 1.4rem;
            color: var(--n5);
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close:hover { color: var(--n8); }

        .modal-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }

        .modal-amount {
            font-size: 2rem;
            font-weight: 800;
            color: var(--maroon-300);
            margin-bottom: 1.8rem;
        }

        /* Method selector */
        .method-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .method-opt {
            padding: 1.2rem;
            border: 2px solid var(--n3);
            border-radius: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .method-opt:hover { border-color: var(--maroon-200); }
        .method-opt.active { border-color: var(--maroon-300); background: var(--maroon-50); }
        .method-opt i { font-size: 2rem; color: var(--maroon-300); display: block; margin-bottom: 0.5rem; }
        .method-opt h4 { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.2rem; }
        .method-opt p  { font-size: 0.8rem; color: var(--n5); }

        /* Card form */
        .card-panel { display: none; }
        .card-panel.active { display: block; }

        .cf-group { margin-bottom: 1rem; }
        .cf-group label { display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--n7); }

        .cf-input {
            width: 100%;
            padding: 0.9rem 1.1rem;
            border: 2px solid var(--n3);
            border-radius: 14px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.2s;
            background: var(--n1);
        }

        .cf-input:focus { outline: none; border-color: var(--maroon-300); background: white; }

        .cf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .card-brands { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
        .card-brands i { font-size: 1.8rem; color: var(--n4); }

        /* Cash panel */
        .cash-panel {
            display: none;
            background: var(--info-light);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
        }

        .cash-panel.active { display: block; }
        .cash-panel i  { font-size: 2.5rem; color: var(--info-primary); display: block; margin-bottom: 0.8rem; }
        .cash-panel h3 { color: var(--info-deep); margin-bottom: 0.4rem; }
        .cash-panel p  { color: var(--info-primary); font-size: 0.9rem; }

        /* Modal actions */
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.8rem;
        }

        .mbtn {
            flex: 1;
            padding: 0.95rem;
            border: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }

        .mbtn-primary   { background: var(--maroon-300); color: white; }
        .mbtn-primary:hover { background: var(--maroon-400); }
        .mbtn-secondary { background: var(--n2); color: var(--n7); }
        .mbtn-secondary:hover { background: var(--n3); }

        /* ── FOOTER ── */
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
            .invoices-grid { grid-template-columns: 1fr; }
            .cf-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header>
    <nav>
        <a href="../../index.php" class="logo">Valora</a>
        <div class="nav-right">
            <a href="profile.php" class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
            </a>
            <a href="../../logout.php" class="logout-btn">
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
        <a href="reports.php"      class="tab"><i class="fas fa-file-medical"></i> Medical Records</a>
        <a href="billings.php"      class="tab active"><i class="fas fa-file-invoice"></i> Billing</a>
        <a href="profile.php"      class="tab"><i class="fas fa-user"></i> Profile</a>
    </div>

    <div class="container">

        <div class="page-header">
            <h1>Billing & Invoices</h1>
        </div>

        <!-- ALERTS -->
        <?php if ($payment_success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $payment_success; ?></div>
        <?php endif; ?>
        <?php if ($payment_error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $payment_error; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <p>Total Paid</p>
                    <h3>BD <?php echo number_format($total_paid, 2); ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <p>Pending</p>
                    <h3>BD <?php echo number_format($total_unpaid, 2); ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-info">
                    <p>Overdue</p>
                    <h3>BD <?php echo number_format($total_overdue, 2); ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-info">
                    <p>Total Invoices</p>
                    <h3><?php echo $total_count; ?></h3>
                </div>
            </div>
        </div>

        <!-- INVOICES -->
        <?php if (count($invoices_raw) > 0): ?>
        <div class="invoices-grid">
            <?php foreach ($invoices_raw as $inv): ?>
            <?php $status_lower = strtolower($inv['status']); ?>
            <div class="invoice-card <?php echo $status_lower; ?>">

                <!-- Top: number + badge -->
                <div class="inv-top">
                    <span class="inv-number">
                        <?php echo !empty($inv['invoice_number']) ? htmlspecialchars($inv['invoice_number']) : '#' . $inv['id']; ?>
                    </span>
                    <span class="inv-badge <?php echo $status_lower; ?>"><?php echo $inv['status']; ?></span>
                </div>

                <!-- Doctor -->
                <div class="inv-doctor">
                    <div class="doc-avatar"><i class="fas fa-user-md"></i></div>
                    <div>
                        <div class="doc-name">Dr. <?php echo htmlspecialchars($inv['doctor_name']); ?></div>
                        <div class="doc-spec"><?php echo htmlspecialchars($inv['specialization']); ?></div>
                    </div>
                </div>

                <!-- Details -->
                <div class="inv-details">
                    <div class="det-row">
                        <span class="det-label"><i class="fas fa-calendar"></i> Appointment</span>
                        <span class="det-value"><?php echo date('M d, Y', strtotime($inv['appointment_date'])); ?></span>
                    </div>
                    <div class="det-row">
                        <span class="det-label"><i class="fas fa-clock"></i> Time</span>
                        <span class="det-value"><?php echo date('h:i A', strtotime($inv['appointment_time'])); ?></span>
                    </div>
                    <div class="det-row">
                        <span class="det-label"><i class="fas fa-stethoscope"></i> Consultation</span>
                        <span class="det-value">BD <?php echo number_format($inv['consultation_fee'], 2); ?></span>
                    </div>
                    <div class="det-row">
                        <span class="det-label" style="font-weight:700;">Total</span>
                        <span class="det-total">BD <?php echo number_format($inv['total_amount'], 2); ?></span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="inv-footer">
                    <?php if ($inv['status'] === 'Paid'): ?>
                        <span class="paid-info">
                            <i class="fas fa-check-circle"></i>
                            <?php echo !empty($inv['payment_method']) ? htmlspecialchars($inv['payment_method']) : 'Paid'; ?>
                        </span>
                        <span class="paid-info"><i class="fas fa-calendar-check"></i> Paid</span>

                    <?php elseif ($inv['status'] === 'Overdue'): ?>
                        <span class="due-info">
                            <i class="fas fa-exclamation-triangle"></i> Overdue
                            <?php if (!empty($inv['due_date'])): ?>
                                · Due <?php echo date('M d', strtotime($inv['due_date'])); ?>
                            <?php endif; ?>
                        </span>
                        <button class="pay-btn"
                                onclick="openModal(<?php echo $inv['id']; ?>, <?php echo $inv['total_amount']; ?>)">
                            <i class="fas fa-credit-card"></i> Pay Now
                        </button>

                    <?php elseif (!empty($inv['payment_method']) && $inv['payment_method'] === 'Cash'): ?>
                        <span class="due-info"><i class="fas fa-clock"></i> Pay at reception</span>
                        <span class="pay-btn disabled"><i class="fas fa-hourglass-half"></i> Pending</span>

                    <?php else: ?>
                        <span class="due-info">
                            <?php if (!empty($inv['due_date'])): ?>
                                <i class="fas fa-calendar"></i> Due <?php echo date('M d, Y', strtotime($inv['due_date'])); ?>
                            <?php endif; ?>
                        </span>
                        <button class="pay-btn"
                                onclick="openModal(<?php echo $inv['id']; ?>, <?php echo $inv['total_amount']; ?>)">
                            <i class="fas fa-credit-card"></i> Pay Now
                        </button>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <h3>No invoices yet</h3>
            <p>Your invoices will appear here after your appointments</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- PAYMENT MODAL -->
<div id="payModal" class="modal">
    <div class="modal-box">
        <i class="fas fa-times modal-close" onclick="closeModal()"></i>
        <div class="modal-title">Complete Payment</div>
        <div class="modal-amount" id="modalAmt">BD 0.00</div>

        <form method="POST" action="">
            <input type="hidden" name="pay_invoice" value="1">
            <input type="hidden" name="invoice_id"  id="modalInvId">
            <input type="hidden" name="payment_method" id="modalMethod" value="card">

            <!-- Method selector -->
            <div class="method-options">
                <div class="method-opt active" id="optCard" onclick="switchMethod('card')">
                    <i class="fas fa-credit-card"></i>
                    <h4>Card</h4>
                    <p>Pay instantly</p>
                </div>
                <div class="method-opt" id="optCash" onclick="switchMethod('cash')">
                    <i class="fas fa-money-bill-wave"></i>
                    <h4>Cash</h4>
                    <p>Pay at reception</p>
                </div>
            </div>

            <!-- Card panel -->
            <div id="cardPanel" class="card-panel active">
                <div class="cf-group">
                    <label>Card Number</label>
                    <input type="text" name="card_number" class="cf-input"
                           placeholder="1234 5678 9012 3456" maxlength="19"
                           oninput="formatCard(this)">
                    <div class="card-brands">
                        <i class="fab fa-cc-visa"></i>
                        <i class="fab fa-cc-mastercard"></i>
                        <i class="fab fa-cc-amex"></i>
                    </div>
                </div>
                <div class="cf-row">
                    <div class="cf-group">
                        <label>Expiry (MM/YY)</label>
                        <input type="text" name="card_expiry" class="cf-input"
                               placeholder="MM/YY" maxlength="5"
                               oninput="formatExpiry(this)">
                    </div>
                    <div class="cf-group">
                        <label>CVV</label>
                        <input type="text" name="card_cvv" class="cf-input"
                               placeholder="123" maxlength="3"
                               oninput="this.value=this.value.replace(/\D/g,'')">
                    </div>
                </div>
                <div class="cf-group">
                    <label>Cardholder Name</label>
                    <input type="text" name="card_name" class="cf-input" placeholder="Full name on card">
                </div>
            </div>

            <!-- Cash panel -->
            <div id="cashPanel" class="cash-panel">
                <i class="fas fa-map-marker-alt"></i>
                <h3>Pay at Reception</h3>
                <p>Please arrive 15 minutes before your appointment. Our team will assist you with payment.</p>
            </div>

            <div class="modal-actions">
                <button type="button" class="mbtn mbtn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="mbtn mbtn-primary" id="confirmBtn">
                    <i class="fas fa-lock"></i> Confirm Payment
                </button>
            </div>
        </form>
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

<script>
    function openModal(id, amount) {
        document.getElementById('modalInvId').value = id;
        document.getElementById('modalAmt').textContent = 'BD ' + parseFloat(amount).toFixed(2);
        document.getElementById('payModal').classList.add('active');
        switchMethod('card');
    }

    function closeModal() {
        document.getElementById('payModal').classList.remove('active');
    }

    function switchMethod(method) {
        document.getElementById('modalMethod').value = method;

        document.getElementById('optCard').classList.toggle('active', method === 'card');
        document.getElementById('optCash').classList.toggle('active', method === 'cash');
        document.getElementById('cardPanel').classList.toggle('active', method === 'card');
        document.getElementById('cashPanel').classList.toggle('active', method === 'cash');

        const btn = document.getElementById('confirmBtn');
        btn.innerHTML = method === 'card'
            ? '<i class="fas fa-lock"></i> Confirm Payment'
            : '<i class="fas fa-check"></i> Confirm – Pay at Reception';
    }

    // Click outside to close
    document.getElementById('payModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Format card number with spaces
    function formatCard(el) {
        let v = el.value.replace(/\D/g, '').substring(0, 16);
        el.value = v.replace(/(.{4})/g, '$1 ').trim();
    }

    // Format expiry MM/YY
    function formatExpiry(el) {
        let v = el.value.replace(/\D/g, '').substring(0, 4);
        if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2);
        el.value = v;
    }
</script>

</body>
</html>