<?php
require_once '../includes/config.php';
requireRole('receptionist');

$total_patients_query = "SELECT COUNT(*) as total FROM patients";
$total_patients = $conn->query($total_patients_query)->fetch_assoc()['total'];

$today_appointments_query = "SELECT COUNT(*) as total FROM appointments WHERE appointment_date = CURDATE()";
$today_appointments = $conn->query($today_appointments_query)->fetch_assoc()['total'];

$unpaid_invoices_query = "SELECT COUNT(*) as total FROM invoices WHERE status = 'Unpaid'";
$unpaid_invoices = $conn->query($unpaid_invoices_query)->fetch_assoc()['total'];

$appointments_query = "SELECT a.*, 
                       u_patient.name as patient_name, 
                       u_doctor.name as doctor_name, 
                       d.specialization
                       FROM appointments a
                       JOIN patients p ON a.patient_id = p.id
                       JOIN users u_patient ON p.user_id = u_patient.id
                       JOIN doctors doc ON a.doctor_id = doc.id
                       JOIN users u_doctor ON doc.user_id = u_doctor.id
                       JOIN doctors d ON doc.id = d.id
                       WHERE a.appointment_date = CURDATE()
                       ORDER BY a.appointment_time";
$today_apt_result = $conn->query($appointments_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard - Hospital Management System</title>
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
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Total Patients</h3>
                <div class="number"><?php echo $total_patients; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Today's Appointments</h3>
                <div class="number" style="color: var(--warning-color);"><?php echo $today_appointments; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Unpaid Invoices</h3>
                <div class="number" style="color: var(--danger-color);"><?php echo $unpaid_invoices; ?></div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header">Quick Actions</h2>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="walkin.php" class="btn btn-primary">Register Walk-in Patient</a>
                <a href="appointments.php" class="btn btn-secondary">View Appointments</a>
                <a href="invoices.php" class="btn btn-success">Generate Invoice</a>
                <a href="patients.php" class="btn btn-warning">View Patients</a>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header">Today's Appointments</h2>
            <?php if ($today_apt_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($apt = $today_apt_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['specialization']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($apt['status']); ?>">
                                        <?php echo $apt['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="appointment_details.php?id=<?php echo $apt['id']; ?>" 
                                       class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">View</a>
                                    <?php if ($apt['status'] === 'Completed'): ?>
                                        <a href="generate_invoice.php?appointment_id=<?php echo $apt['id']; ?>" 
                                           class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Invoice</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No appointments scheduled for today.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>
