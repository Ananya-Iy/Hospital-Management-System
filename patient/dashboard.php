<?php
require_once '../includes/config.php';
requireRole('patient');

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM patients WHERE user_id = $user_id";
$result = $conn->query($query);
$patient = $result->fetch_assoc();
$patient_id = $patient['id'];

$total_appointments_query = "SELECT COUNT(*) as total FROM appointments WHERE patient_id = $patient_id";
$total_appointments = $conn->query($total_appointments_query)->fetch_assoc()['total'];

$upcoming_query = "SELECT COUNT(*) as total FROM appointments 
                   WHERE patient_id = $patient_id 
                   AND appointment_date >= CURDATE() 
                   AND status NOT IN ('Cancelled', 'Completed')";
$upcoming_appointments = $conn->query($upcoming_query)->fetch_assoc()['total'];

$completed_query = "SELECT COUNT(*) as total FROM appointments 
                    WHERE patient_id = $patient_id AND status = 'Completed'";
$completed_appointments = $conn->query($completed_query)->fetch_assoc()['total'];

$recent_query = "SELECT a.*, u.name as doctor_name, d.specialization 
                 FROM appointments a
                 JOIN doctors doc ON a.doctor_id = doc.id
                 JOIN users u ON doc.user_id = u.id
                 JOIN doctors d ON doc.id = d.id
                 WHERE a.patient_id = $patient_id
                 ORDER BY a.appointment_date DESC, a.appointment_time DESC
                 LIMIT 5";
$recent_appointments = $conn->query($recent_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Hospital Management System</title>
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
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Total Appointments</h3>
                <div class="number"><?php echo $total_appointments; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Upcoming Appointments</h3>
                <div class="number" style="color: var(--warning-color);"><?php echo $upcoming_appointments; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Completed</h3>
                <div class="number" style="color: var(--success-color);"><?php echo $completed_appointments; ?></div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header">Quick Actions</h2>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="doctors.php" class="btn btn-primary">Book Appointment</a>
                <a href="appointments.php" class="btn btn-secondary">View Appointments</a>
                <a href="prescriptions.php" class="btn btn-success">View Prescriptions</a>
                <a href="profile.php" class="btn btn-warning">Update Profile</a>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header">Recent Appointments</h2>
            <?php if ($recent_appointments->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($appointment = $recent_appointments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                <td><?php echo formatTime($appointment['appointment_time']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo $appointment['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="appointment_details.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="appointments.php" class="btn btn-secondary">View All Appointments</a>
                </div>
            <?php else: ?>
                <p>No appointments yet. <a href="doctors.php">Book your first appointment</a></p>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>
