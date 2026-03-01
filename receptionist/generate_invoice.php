<?php
require_once '../includes/config.php';
requireRole('receptionist');

$success = '';
$error = '';

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

$query = "SELECT a.*, 
          u_patient.name as patient_name, p.phone as patient_phone,
          u_doctor.name as doctor_name, d.specialization, d.consultation_fee
          FROM appointments a
          JOIN patients pat ON a.patient_id = pat.id
          JOIN users u_patient ON pat.user_id = u_patient.id
          JOIN patients p ON pat.id = p.id
          JOIN doctors doc ON a.doctor_id = doc.id
          JOIN users u_doctor ON doc.user_id = u_doctor.id
          JOIN doctors d ON doc.id = d.id
          WHERE a.id = $appointment_id";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    header('Location: appointments.php');
    exit();
}

$appointment = $result->fetch_assoc();

$check_invoice = "SELECT * FROM invoices WHERE appointment_id = $appointment_id";
$existing_invoice = $conn->query($check_invoice);

if (isset($_POST['generate_invoice']) && $existing_invoice->num_rows === 0) {
    $total_amount = floatval($_POST['total_amount']);
    $payment_method = sanitize($_POST['payment_method']);
    $status = sanitize($_POST['status']);
    
    $patient_id = $appointment['patient_id'];
    
    $insert_query = "INSERT INTO invoices (patient_id, appointment_id, total_amount, payment_method, status";
    
    if ($status === 'Paid') {
        $insert_query .= ", payment_date) VALUES ($patient_id, $appointment_id, $total_amount, '$payment_method', '$status', NOW())";
    } else {
        $insert_query .= ") VALUES ($patient_id, $appointment_id, $total_amount, '$payment_method', '$status')";
    }
    
    if ($conn->query($insert_query)) {
        if ($status === 'Paid') {
            $conn->query("UPDATE appointments SET status = 'Paid' WHERE id = $appointment_id");
        }
        $success = 'Invoice generated successfully';
        $existing_invoice = $conn->query($check_invoice);
    } else {
        $error = 'Failed to generate invoice';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Invoice - Hospital Management System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="../index.php" class="logo">🏥 Hospital Management</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="appointments.php">Appointments</a></li>
                <li><a href="patients.php">Patients</a></li>
                <li><a href="walkin.php">Walk-in Registration</a></li>
                <li><a href="invoices.php">Invoices</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h1>Generate Invoice</h1>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-header">Appointment Details</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <p><strong>Appointment ID:</strong> #<?php echo $appointment['id']; ?></p>
                    <p><strong>Patient:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                </div>
                <div>
                    <p><strong>Doctor:</strong> <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization']); ?></p>
                    <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
                </div>
            </div>
        </div>

        <?php if ($existing_invoice->num_rows > 0): ?>
            <?php $invoice = $existing_invoice->fetch_assoc(); ?>
            <div class="card" id="invoice-content">
                <h2 class="card-header">Invoice Details</h2>
                <div style="border: 2px solid #ddd; padding: 2rem; border-radius: 8px;">
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <h2 style="color: var(--primary-color);">🏥 HOSPITAL MANAGEMENT SYSTEM</h2>
                        <h3>INVOICE</h3>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                        <div>
                            <h4>Bill To:</h4>
                            <p><strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong></p>
                            <p>Phone: <?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                        </div>
                        <div style="text-align: right;">
                            <p><strong>Invoice #:</strong> INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></p>
                            <p><strong>Date:</strong> <?php echo formatDate($invoice['created_at']); ?></p>
                            <p><strong>Appointment ID:</strong> #<?php echo $appointment['id']; ?></p>
                        </div>
                    </div>

                    <table style="margin-bottom: 2rem;">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($appointment['specialization']); ?> Consultation</td>
                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                <td style="text-align: right;">$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: bold; font-size: 1.2rem;">
                                <td colspan="3" style="text-align: right;">Total Amount:</td>
                                <td style="text-align: right; color: var(--success-color);">
                                    $<?php echo number_format($invoice['total_amount'], 2); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <div style="background-color: #f8f9fa; padding: 1rem; border-radius: 4px;">
                        <p><strong>Payment Status:</strong> 
                            <span class="status-badge status-<?php echo strtolower($invoice['status']); ?>">
                                <?php echo $invoice['status']; ?>
                            </span>
                        </p>
                        <?php if ($invoice['payment_method']): ?>
                            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($invoice['payment_method']); ?></p>
                        <?php endif; ?>
                        <?php if ($invoice['payment_date']): ?>
                            <p><strong>Payment Date:</strong> <?php echo formatDate($invoice['payment_date']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 2rem; text-align: center; color: var(--light-text);">
                        <p>Thank you for choosing our hospital!</p>
                        <p style="font-size: 0.875rem;">For queries, contact: info@hospital.com</p>
                    </div>
                </div>

                <div class="no-print" style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                    <button onclick="window.print()" class="btn btn-primary">Print Invoice</button>
                    <a href="invoices.php" class="btn btn-secondary">View All Invoices</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <h2 class="card-header">Generate Invoice</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="total_amount">Total Amount ($) *</label>
                        <input type="number" id="total_amount" name="total_amount" step="0.01" required
                               value="<?php echo $appointment['consultation_fee']; ?>">
                        <small>Default consultation fee: $<?php echo number_format($appointment['consultation_fee'], 2); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Insurance">Insurance</option>
                            <option value="Online Payment">Online Payment</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Payment Status *</label>
                        <select id="status" name="status" required>
                            <option value="Paid">Paid</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" name="generate_invoice" class="btn btn-success">Generate Invoice</button>
                        <a href="appointments.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>
