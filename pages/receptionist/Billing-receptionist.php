<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('receptionist');

$receptionist_name = $_SESSION['name'];
$success = '';
$error   = '';

// ── Handle payment processing ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $invoice_id     = intval($_POST['invoice_id']);
    $payment_method = sanitize($_POST['payment_method']);
    $paid_amount    = floatval($_POST['paid_amount']);

    $inv = $conn->query("SELECT * FROM invoices WHERE id = $invoice_id")->fetch_assoc();

    if ($inv) {
        $new_status = ($paid_amount >= $inv['total_amount']) ? 'Paid' : 'Partial';
        $stmt = $conn->prepare(
            "UPDATE invoices SET status = ?, paid_amount = ?, payment_method = ? WHERE id = ?"
        );
        $stmt->bind_param("sdsi", $new_status, $paid_amount, $payment_method, $invoice_id);
        if ($stmt->execute()) {
            // Also mark appointment as Completed if fully paid
            if ($new_status === 'Paid') {
                $conn->query("UPDATE appointments SET status = 'Completed' WHERE id = {$inv['appointment_id']} AND status = 'Confirmed'");
            }
            header("Location: billing-receptionist.php?paid=1"); exit;
        } else {
            $error = 'Failed to process payment.';
        }
        $stmt->close();
    }
}

// ── Handle generate invoice ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invoice'])) {
    $apt_id          = intval($_POST['apt_id']);
    $extra_charges   = floatval($_POST['extra_charges'] ?? 0);
    $notes           = sanitize($_POST['notes'] ?? '');

    // Check if invoice already exists
    $existing = $conn->query("SELECT id FROM invoices WHERE appointment_id = $apt_id")->fetch_assoc();
    if ($existing) {
        $error = 'An invoice already exists for this appointment.';
    } else {
        $apt = $conn->query(
            "SELECT a.patient_id, d.consultation_fee, a.appointment_date
             FROM appointments a JOIN doctors d ON a.doctor_id = d.id
             WHERE a.id = $apt_id"
        )->fetch_assoc();

        if ($apt) {
            $total      = $apt['consultation_fee'] + $extra_charges;
            $inv_number = 'INV-' . str_pad($apt_id, 5, '0', STR_PAD_LEFT);
            $due_date   = $apt['appointment_date'];

            $stmt = $conn->prepare(
                "INSERT INTO invoices (patient_id, appointment_id, invoice_number, total_amount, paid_amount, status, due_date, notes)
                 VALUES (?, ?, ?, ?, 0.00, 'Unpaid', ?, ?)"
            );
            $stmt->bind_param("iidsss", $apt['patient_id'], $apt_id, $inv_number, $total, $due_date, $notes);
            if ($stmt->execute()) {
                header("Location: billing-receptionist.php?generated=1"); exit;
            } else {
                $error = 'Failed to generate invoice.';
            }
            $stmt->close();
        }
    }
}

// ── Fetch invoices ────────────────────────────────────────────────────────────
$tab    = $_GET['tab']    ?? 'invoices';
$filter = $_GET['filter'] ?? 'all';

$where = "WHERE 1=1";
if ($filter === 'unpaid')  $where .= " AND i.status = 'Unpaid'";
if ($filter === 'paid')    $where .= " AND i.status = 'Paid'";
if ($filter === 'partial') $where .= " AND i.status = 'Partial'";

$invoices = [];
$inv_res = $conn->query(
    "SELECT i.*, u.name AS patient_name, du.name AS doctor_name, s.name AS specialization,
            a.appointment_date, a.appointment_time, a.status AS apt_status
     FROM invoices i
     JOIN patients p  ON i.patient_id     = p.id
     JOIN users u     ON p.user_id        = u.id
     JOIN appointments a ON i.appointment_id = a.id
     JOIN doctors d   ON a.doctor_id      = d.id
     JOIN users du    ON d.user_id        = du.id
     JOIN specializations s ON d.specialization_id = s.id
     $where
     ORDER BY i.id DESC"
);
while ($row = $inv_res->fetch_assoc()) $invoices[] = $row;

// Revenue stats
$today_rev  = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as t FROM invoices WHERE DATE(updated_at)=CURDATE() AND status='Paid'")->fetch_assoc()['t'];
$total_unpaid = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM invoices WHERE status='Unpaid'")->fetch_assoc()['t'];
$total_paid   = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as t FROM invoices WHERE status='Paid'")->fetch_assoc()['t'];
$total_inv    = $conn->query("SELECT COUNT(*) as c FROM invoices")->fetch_assoc()['c'];

// Appointments without invoices (for generate invoice form)
$no_invoice_apts = [];
$nia_res = $conn->query(
    "SELECT a.id, a.appointment_date, a.appointment_time, u.name AS patient_name, du.name AS doctor_name, d.consultation_fee
     FROM appointments a
     JOIN patients p  ON a.patient_id = p.id
     JOIN users u     ON p.user_id    = u.id
     JOIN doctors d   ON a.doctor_id  = d.id
     JOIN users du    ON d.user_id    = du.id
     WHERE a.id NOT IN (SELECT appointment_id FROM invoices WHERE appointment_id IS NOT NULL)
     AND a.status != 'Cancelled'
     ORDER BY a.appointment_date DESC LIMIT 50"
);
while ($r = $nia_res->fetch_assoc()) $no_invoice_apts[] = $r;

// Pre-select appointment if coming from check-in
$preselect_apt = intval($_GET['apt_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing · Valora Receptionist</title>
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
            --sh-md:0 8px 16px rgba(0,0,0,0.04);
            --sh-lg:0 16px 24px rgba(0,0,0,0.04);
            --sh-xl:0 24px 32px rgba(100,23,50,0.08);
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

        .alert{padding:1rem 1.5rem;border-radius:14px;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:var(--sl);color:var(--sd);border-left:6px solid var(--sp)}
        .alert-error{background:var(--el);color:var(--ed);border-left:6px solid var(--ep)}

        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
        .page-header h1{font-size:2rem;font-weight:700;position:relative;padding-bottom:.8rem}
        .page-header h1::after{content:'';position:absolute;bottom:0;left:0;width:80px;height:4px;background:linear-gradient(90deg,var(--m300),var(--ip));border-radius:4px}

        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;margin-bottom:2rem}
        .stat-card{background:white;border-radius:24px;padding:1.5rem;box-shadow:var(--sh-md);border:1px solid var(--n3);display:flex;align-items:center;gap:1rem;transition:.2s}
        .stat-card:hover{transform:translateY(-3px);box-shadow:var(--sh-xl)}
        .stat-icon{width:56px;height:56px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
        .si-green{background:var(--sl);color:var(--sd)}
        .si-orange{background:var(--wl);color:var(--wd)}
        .si-blue{background:var(--il);color:var(--ip)}
        .si-maroon{background:var(--m50);color:var(--m300)}
        .stat-info p{font-size:.85rem;color:var(--n5);margin-bottom:.2rem}
        .stat-info h3{font-size:1.6rem;font-weight:700}

        .btn-primary{padding:.8rem 1.8rem;background:var(--m300);color:white;border-radius:40px;font-weight:700;font-size:.9rem;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;font-family:inherit;transition:.2s}
        .btn-primary:hover{background:var(--m400);transform:translateY(-1px)}

        .filter-row{display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .filter-btn{padding:.55rem 1.3rem;border-radius:40px;font-weight:600;font-size:.88rem;border:1px solid var(--n3);background:white;color:var(--n7);transition:.2s}
        .filter-btn:hover{border-color:var(--m200);color:var(--m300);background:var(--m50)}
        .filter-btn.active{background:var(--m300);color:white;border-color:var(--m300)}

        .table-box{background:white;border-radius:24px;padding:1.5rem;box-shadow:var(--sh-md);border:1px solid var(--n3);overflow-x:auto;margin-bottom:2rem}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{background:linear-gradient(135deg,var(--m300),var(--m400));color:white;padding:.9rem 1rem;text-align:left;font-weight:600;font-size:.88rem}
        .data-table th:first-child{border-radius:12px 0 0 0}
        .data-table th:last-child{border-radius:0 12px 0 0}
        .data-table td{padding:.9rem 1rem;border-bottom:1px solid var(--n2);color:var(--n7);font-size:.9rem;vertical-align:middle}
        .data-table tr:last-child td{border-bottom:none}
        .data-table tr:hover td{background:var(--n1)}

        .badge{padding:.3rem .9rem;border-radius:100px;font-size:.78rem;font-weight:600;display:inline-block}
        .b-unpaid{background:var(--wl);color:var(--wd)}
        .b-paid{background:var(--sl);color:var(--sd)}
        .b-partial{background:var(--il);color:var(--ip)}
        .b-cancelled{background:var(--el);color:var(--ed)}

        .btn-sm{padding:.4rem .9rem;border-radius:40px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;font-family:inherit;transition:.2s;display:inline-flex;align-items:center;gap:.3rem}
        .btn-pay{background:var(--sp);color:white}
        .btn-pay:hover{background:var(--sd)}
        .btn-print{background:var(--il);color:var(--ip)}
        .btn-print:hover{background:var(--ip);color:white}

        .empty{text-align:center;padding:3rem 2rem}
        .empty i{font-size:3rem;color:var(--n4);display:block;margin-bottom:.8rem}
        .empty p{color:var(--n5)}

        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center}
        .modal.active{display:flex}
        .modal-box{background:white;border-radius:28px;padding:2.5rem;max-width:480px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:var(--sh-xl);position:relative;animation:popIn .25s ease}
        @keyframes popIn{from{opacity:0;transform:scale(.95) translateY(-10px)}to{opacity:1;transform:scale(1) translateY(0)}}
        .modal-close{position:absolute;top:1.2rem;right:1.2rem;font-size:1.3rem;color:var(--n5);cursor:pointer}
        .modal-close:hover{color:var(--n8)}
        .modal-title{font-size:1.4rem;font-weight:700;margin-bottom:.3rem}
        .modal-sub{color:var(--n5);font-size:.9rem;margin-bottom:1.5rem}
        .fg{margin-bottom:1.2rem}
        .fg label{display:block;font-weight:600;font-size:.88rem;margin-bottom:.4rem;color:var(--n7)}
        .fc{width:100%;padding:.9rem 1.1rem;border:2px solid var(--n3);border-radius:14px;font-size:.95rem;background:var(--n1);font-family:inherit;transition:.2s}
        .fc:focus{outline:none;border-color:var(--m300);background:white}
        select.fc{appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23842646' stroke-width='2'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right 1rem center;background-size:1rem;background-color:var(--n1)}
        .modal-actions{display:flex;gap:1rem;margin-top:1.5rem}
        .mbtn{flex:1;padding:.9rem;border:none;border-radius:40px;font-weight:700;font-size:.9rem;cursor:pointer;font-family:inherit;transition:.2s}
        .mbtn-primary{background:var(--m300);color:white}
        .mbtn-primary:hover{background:var(--m400)}
        .mbtn-secondary{background:var(--n2);color:var(--n7)}
        .mbtn-secondary:hover{background:var(--n3)}

        /* PRINTABLE INVOICE */
        @media print {
            header, .nav-tabs, .page-header, .stats-grid, .filter-row, footer, .btn-sm, .modal { display: none !important; }
            .print-invoice { display: block !important; }
            body { background: white; }
        }
        .print-invoice { display: none; }

        footer{background:var(--n7);color:var(--n2);padding:2rem;text-align:center;border-top:5px solid var(--m300);margin-top:auto}
        .footer-content{max-width:1400px;margin:0 auto}
        .footer-copy{color:var(--n5);font-size:.85rem;margin-top:.5rem}
        @media(max-width:768px){nav{flex-direction:column;gap:.8rem;padding:1rem}.container{padding:1rem}}
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
        <a href="records-receptionist.php"                class="ntab"><i class="fas fa-user-check"></i> Records</a>
        <a href="billing-receptionist.php"   class="ntab active"><i class="fas fa-file-invoice-dollar"></i> Billing</a>
        <a href="register-patient.php"       class="ntab"><i class="fas fa-user-plus"></i> Register Patient</a>
    </div>

    <div class="container">

        <?php if (isset($_GET['paid'])):      ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Payment recorded successfully.</div><?php endif; ?>
        <?php if (isset($_GET['generated'])): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Invoice generated successfully.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>

        <div class="page-header">
            <h1>Billing & Payments</h1>
            <button class="btn-primary" onclick="document.getElementById('genModal').classList.add('active')">
                <i class="fas fa-plus"></i> Generate Invoice
            </button>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon si-green"><i class="fas fa-money-bill-wave"></i></div><div class="stat-info"><p>Today's Revenue</p><h3>BD <?php echo number_format($today_rev, 2); ?></h3></div></div>
            <div class="stat-card"><div class="stat-icon si-green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><p>Total Collected</p><h3>BD <?php echo number_format($total_paid, 2); ?></h3></div></div>
            <div class="stat-card"><div class="stat-icon si-orange"><i class="fas fa-clock"></i></div><div class="stat-info"><p>Pending Amount</p><h3>BD <?php echo number_format($total_unpaid, 2); ?></h3></div></div>
            <div class="stat-card"><div class="stat-icon si-blue"><i class="fas fa-file-invoice"></i></div><div class="stat-info"><p>Total Invoices</p><h3><?php echo $total_inv; ?></h3></div></div>
        </div>

        <!-- FILTER -->
        <div class="filter-row">
            <?php foreach (['all'=>'All','unpaid'=>'Unpaid','paid'=>'Paid','partial'=>'Partial'] as $val=>$label): ?>
            <a href="?filter=<?php echo $val; ?>" class="filter-btn <?php echo $filter===$val?'active':''; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <!-- INVOICES TABLE -->
        <div class="table-box">
            <?php if (count($invoices) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr><th>Invoice</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Total</th><th>Paid</th><th>Status</th><th>Method</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td style="color:var(--n5);font-size:.82rem"><?php echo htmlspecialchars($inv['invoice_number'] ?? '#'.$inv['id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($inv['patient_name']); ?></strong></td>
                        <td>Dr. <?php echo htmlspecialchars($inv['doctor_name']); ?><br><span style="color:var(--ip);font-size:.8rem"><?php echo htmlspecialchars($inv['specialization']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($inv['appointment_date'])); ?></td>
                        <td><strong>BD <?php echo number_format($inv['total_amount'], 2); ?></strong></td>
                        <td>BD <?php echo number_format($inv['paid_amount'], 2); ?></td>
                        <td><span class="badge b-<?php echo strtolower($inv['status']); ?>"><?php echo $inv['status']; ?></span></td>
                        <td><?php echo htmlspecialchars($inv['payment_method'] ?? '—'); ?></td>
                        <td style="white-space:nowrap">
                            <?php if ($inv['status'] !== 'Paid'): ?>
                            <button class="btn-sm btn-pay"
                                onclick="openPayment(<?php echo $inv['id']; ?>, <?php echo $inv['total_amount']; ?>, <?php echo $inv['paid_amount']; ?>, '<?php echo htmlspecialchars($inv['patient_name']); ?>')">
                                <i class="fas fa-credit-card"></i> Pay
                            </button>
                            <?php endif; ?>
                            <button class="btn-sm btn-print" onclick="printInvoice(<?php echo $inv['id']; ?>)">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty"><i class="fas fa-file-invoice"></i><p>No invoices found.</p></div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- PAYMENT MODAL -->
<div id="payModal" class="modal">
    <div class="modal-box">
        <i class="fas fa-times modal-close" onclick="document.getElementById('payModal').classList.remove('active')"></i>
        <div class="modal-title">Process Payment</div>
        <div class="modal-sub" id="paySubtitle"></div>
        <form method="POST">
            <input type="hidden" name="invoice_id" id="payInvId">
            <div class="fg">
                <label>Payment Method</label>
                <select name="payment_method" class="fc" required>
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="Insurance">Insurance</option>
                    <option value="Online">Online</option>
                </select>
            </div>
            <div class="fg">
                <label>Amount Paid (BD)</label>
                <input type="number" name="paid_amount" id="payAmount" class="fc" step="0.01" min="0" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="mbtn mbtn-secondary" onclick="document.getElementById('payModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="process_payment" class="mbtn mbtn-primary"><i class="fas fa-check"></i> Record Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- GENERATE INVOICE MODAL -->
<div id="genModal" class="modal">
    <div class="modal-box">
        <i class="fas fa-times modal-close" onclick="document.getElementById('genModal').classList.remove('active')"></i>
        <div class="modal-title">Generate Invoice</div>
        <div class="modal-sub">Create invoice for an appointment that doesn't have one yet.</div>
        <form method="POST">
            <div class="fg">
                <label>Appointment</label>
                <select name="apt_id" class="fc" required id="aptSelect" onchange="updateFee(this)">
                    <option value="">Select appointment...</option>
                    <?php foreach ($no_invoice_apts as $a): ?>
                    <option value="<?php echo $a['id']; ?>"
                            data-fee="<?php echo $a['consultation_fee']; ?>"
                            <?php echo ($preselect_apt === $a['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['patient_name']); ?> →
                        Dr. <?php echo htmlspecialchars($a['doctor_name']); ?> |
                        <?php echo date('M d, Y', strtotime($a['appointment_date'])); ?> <?php echo date('h:i A', strtotime($a['appointment_time'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Consultation Fee (BD) — auto-filled</label>
                <input type="text" id="consultFee" class="fc" readonly style="background:var(--n2);color:var(--n6)">
            </div>
            <div class="fg">
                <label>Additional Charges (BD) <span style="color:var(--n5);font-weight:400">(optional)</span></label>
                <input type="number" name="extra_charges" class="fc" step="0.01" min="0" value="0" id="extraCharges" oninput="calcTotal()">
            </div>
            <div class="fg">
                <label>Total (BD)</label>
                <input type="text" id="totalDisplay" class="fc" readonly style="background:var(--n2);color:var(--n6);font-weight:700">
            </div>
            <div class="fg">
                <label>Notes <span style="color:var(--n5);font-weight:400">(optional)</span></label>
                <input type="text" name="notes" class="fc" placeholder="e.g. Follow-up required">
            </div>
            <div class="modal-actions">
                <button type="button" class="mbtn mbtn-secondary" onclick="document.getElementById('genModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="generate_invoice" class="mbtn mbtn-primary"><i class="fas fa-file-invoice"></i> Generate</button>
            </div>
        </form>
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
function openPayment(id, total, paid, name) {
    document.getElementById('payInvId').value    = id;
    document.getElementById('payAmount').value   = (total - paid).toFixed(2);
    document.getElementById('paySubtitle').textContent = name + ' · BD ' + parseFloat(total).toFixed(2) + ' total';
    document.getElementById('payModal').classList.add('active');
}

function updateFee(sel) {
    const fee = parseFloat(sel.options[sel.selectedIndex].dataset.fee || 0);
    document.getElementById('consultFee').value  = fee.toFixed(2);
    document.getElementById('extraCharges').value = '0';
    document.getElementById('totalDisplay').value = fee.toFixed(2);
}

function calcTotal() {
    const fee   = parseFloat(document.getElementById('consultFee').value  || 0);
    const extra = parseFloat(document.getElementById('extraCharges').value || 0);
    document.getElementById('totalDisplay').value = (fee + extra).toFixed(2);
}

function printInvoice(id) {
    window.open('print-invoice.php?id=' + id, '_blank', 'width=800,height=600');
}

document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); });
});

// Auto-open generate modal if coming from check-in with apt_id
<?php if ($preselect_apt): ?>
document.getElementById('genModal').classList.add('active');
updateFee(document.getElementById('aptSelect'));
<?php endif; ?>
<?php if (isset($_GET['tab']) && $_GET['tab'] === 'payments'): ?>
// scroll to table — already visible
<?php endif; ?>
</script>

</body>
</html>