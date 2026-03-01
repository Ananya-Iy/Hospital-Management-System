<?php
require_once '../includes/config.php';
requireRole('patient');

$user_id = $_SESSION['user_id'];
$patient = $conn->query("SELECT * FROM patients WHERE user_id = $user_id")->fetch_assoc();
$patient_id = $patient['id'];

$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$query = "SELECT a.*, u.name as doctor_name, d.specialization, d.consultation_fee, u.phone as doctor_phone
          FROM appointments a
          JOIN doctors doc ON a.doctor_id = doc.id
          JOIN users u ON doc.user_id = u.id
          JOIN doctors d ON doc.id = d.id
          WHERE a.id = $appointment_id AND a.patient_id = $patient_id";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    header('Location: appointments.php');
    exit();
}

$appointment = $result->fetch_assoc();

$prescription_query = "SELECT * FROM prescriptions WHERE appointment_id = $appointment_id";
$prescription = $conn->query($prescription_query);

$invoice_query = "SELECT * FROM invoices WHERE appointment_id = $appointment_id";
$invoice = $conn->query($invoice_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - Hospital Management System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="../index.php" class="logo">🏥 Hospital Management</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="doctors.php">Find Doctors</a></li>
                <li><a href="appointments.php">My Appointments</a></li>
                <li><a href="prescriptions.php">Prescriptions</a></li>
                <li><a href="invoices.php">Invoices</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h1>Appointment Details</h1>

        <div class="card">
            <h2 class="card-header">Appointment Information</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <p><strong>Appointment ID:</strong> #<?php echo $appointment['id']; ?></p>
                    <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
                    <p><strong>Time:</strong> <?php echo formatTime($appointment['appointment_time']); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                            <?php echo $appointment['status']; ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p><strong>Doctor:</strong> <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($appointment['doctor_phone']); ?></p>
                    <p><strong>Consultation Fee:</strong> $<?php echo number_format($appointment['consultation_fee'], 2); ?></p>
                </div>
            </div>

            <?php if (!empty($appointment['symptoms'])): ?>
                <div style="margin-top: 1.5rem;">
                    <strong>Symptoms / Reason for Visit:</strong>
                    <p style="margin-top: 0.5rem; padding: 1rem; background-color: #f8f9fa; border-radius: 4px;">
                        <?php echo nl2br(htmlspecialchars($appointment['symptoms'])); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($appointment['diagnosis_notes'])): ?>
                <div style="margin-top: 1.5rem;">
                    <strong>Doctor's Diagnosis:</strong>
                    <p style="margin-top: 0.5rem; padding: 1rem; background-color: #e8f5e9; border-radius: 4px;">
                        <?php echo nl2br(htmlspecialchars($appointment['diagnosis_notes'])); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($prescription->num_rows > 0): ?>
            <div class="card">
                <h2 class="card-header">Prescription</h2>
                <?php $presc = $prescription->fetch_assoc(); ?>
                <div style="padding: 1rem; background-color: #fff3cd; border-radius: 4px; margin-bottom: 1rem;">
                    <strong>Medication:</strong>
                    <p style="margin-top: 0.5rem;"><?php echo nl2br(htmlspecialchars($presc['medication'])); ?></p>
                </div>
                <div style="padding: 1rem; background-color: #d1ecf1; border-radius: 4px; margin-bottom: 1rem;">
                    <strong>Dosage:</strong>
                    <p style="margin-top: 0.5rem;"><?php echo nl2br(htmlspecialchars($presc['dosage'])); ?></p>
                </div>
                <?php if (!empty($presc['doctor_notes'])): ?>
                    <div style="padding: 1rem; background-color: #f8f9fa; border-radius: 4px;">
                        <strong>Additional Notes:</strong>
                        <p style="margin-top: 0.5rem;"><?php echo nl2br(htmlspecialchars($presc['doctor_notes'])); ?></p>
                    </div>
                <?php endif; ?>
                <div style="margin-top: 1rem;">
                    <button onclick="window.print()" class="btn btn-primary">Print Prescription</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($invoice->num_rows > 0): ?>
            <div class="card">
                <h2 class="card-header">Invoice</h2>
                <?php $inv = $invoice->fetch_assoc(); ?>
                <table>
                    <tr>
                        <td><strong>Invoice ID:</strong></td>
                        <td>#<?php echo $inv['id']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Amount:</strong></td>
                        <td style="font-size: 1.25rem; font-weight: bold; color: var(--success-color);">
                            $<?php echo number_format($inv['total_amount'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($inv['status']); ?>">
                                <?php echo $inv['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($inv['payment_method']): ?>
                        <tr>
                            <td><strong>Payment Method:</strong></td>
                            <td><?php echo htmlspecialchars($inv['payment_method']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($inv['payment_date']): ?>
                        <tr>
                            <td><strong>Payment Date:</strong></td>
                            <td><?php echo formatDate($inv['payment_date']); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
                <div style="margin-top: 1rem;">
                    <a href="download_invoice.php?id=<?php echo $inv['id']; ?>" class="btn btn-success">Download Invoice</a>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 2rem;">
            <a href="appointments.php" class="btn btn-secondary">Back to Appointments</a>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>
