<?php
require_once '../includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];
$doctor = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id")->fetch_assoc();
$doctor_id = $doctor['id'];

$success = '';

if (isset($_POST['update_status'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $new_status = sanitize($_POST['status']);
    $update_query = "UPDATE appointments SET status = '$new_status' 
                     WHERE id = $appointment_id AND doctor_id = $doctor_id";
    if ($conn->query($update_query)) {
        $success = 'Appointment status updated successfully';
    }
}

$query = "SELECT a.*, u.name as patient_name, p.phone, p.date_of_birth, p.blood_group 
          FROM appointments a
          JOIN patients pat ON a.patient_id = pat.id
          JOIN users u ON pat.user_id = u.id
          JOIN patients p ON pat.id = p.id
          WHERE a.doctor_id = $doctor_id
          ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointments = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Hospital Management System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="../index.php" class="logo">🏥 Hospital Management</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="appointments.php">Appointments</a></li>
                <li><a href="schedule.php">My Schedule</a></li>
                <li><a href="patients.php">Patients</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h1>Manage Appointments</h1>

        <?php if ($success): ?>
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
                            <th>Patient</th>
                            <th>Contact</th>
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
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['phone']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($apt['status']); ?>">
                                        <?php echo $apt['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="appointment_details.php?id=<?php echo $apt['id']; ?>" 
                                       class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Details</a>
                                    <?php if ($apt['status'] === 'Requested'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                            <input type="hidden" name="status" value="Confirmed">
                                            <button type="submit" name="update_status" class="btn btn-success" 
                                                    style="padding: 0.5rem 1rem; font-size: 0.875rem;">Confirm</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No appointments found.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>
