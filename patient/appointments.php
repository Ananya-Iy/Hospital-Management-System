<?php
require_once '../includes/config.php';
requireRole('patient');

$user_id = $_SESSION['user_id'];
$patient_query = "SELECT * FROM patients WHERE user_id = $user_id";
$patient = $conn->query($patient_query)->fetch_assoc();
$patient_id = $patient['id'];

if (isset($_GET['cancel']) && $_GET['cancel']) {
    $appointment_id = intval($_GET['cancel']);
    $update_query = "UPDATE appointments SET status = 'Cancelled' 
                     WHERE id = $appointment_id AND patient_id = $patient_id 
                     AND status IN ('Requested', 'Confirmed')";
    if ($conn->query($update_query)) {
        $success = 'Appointment cancelled successfully';
    }
}

$query = "SELECT a.*, u.name as doctor_name, d.specialization 
          FROM appointments a
          JOIN doctors doc ON a.doctor_id = doc.id
          JOIN users u ON doc.user_id = u.id
          JOIN doctors d ON doc.id = d.id
          WHERE a.patient_id = $patient_id
          ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointments = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Hospital Management System</title>
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
        <h1>My Appointments</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-header">All Appointments</h2>
            <?php if ($appointments->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($apt = $appointments->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $apt['id']; ?></td>
                                <td><?php echo formatDate($apt['appointment_date']); ?></td>
                                <td><?php echo formatTime($apt['appointment_time']); ?></td>
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
                                    <?php if (in_array($apt['status'], ['Requested', 'Confirmed'])): ?>
                                        <a href="?cancel=<?php echo $apt['id']; ?>" 
                                           class="btn btn-danger" 
                                           style="padding: 0.5rem 1rem; font-size: 0.875rem;"
                                           onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No appointments found. <a href="doctors.php">Book your first appointment</a></p>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>
