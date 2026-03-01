<?php
require_once '../includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];
$doctor_query = "SELECT * FROM doctors WHERE user_id = $user_id";
$doctor = $conn->query($doctor_query)->fetch_assoc();
$doctor_id = $doctor['id'];

$total_query = "SELECT COUNT(*) as total FROM appointments WHERE doctor_id = $doctor_id";
$total_appointments = $conn->query($total_query)->fetch_assoc()['total'];

$today_query = "SELECT COUNT(*) as total FROM appointments 
                WHERE doctor_id = $doctor_id AND appointment_date = CURDATE()";
$today_appointments = $conn->query($today_query)->fetch_assoc()['total'];

$pending_query = "SELECT COUNT(*) as total FROM appointments 
                  WHERE doctor_id = $doctor_id AND status = 'Requested'";
$pending_appointments = $conn->query($pending_query)->fetch_assoc()['total'];

$today_apt_query = "SELECT a.*, u.name as patient_name, p.phone, p.blood_group 
                    FROM appointments a
                    JOIN patients pat ON a.patient_id = pat.id
                    JOIN users u ON pat.user_id = u.id
                    JOIN patients p ON pat.id = p.id
                    WHERE a.doctor_id = $doctor_id 
                    AND a.appointment_date = CURDATE()
                    ORDER BY a.appointment_time";
$today_apt_result = $conn->query($today_apt_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Hospital Management System</title>
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
        <h1>Welcome, Dr. <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Total Appointments</h3>
                <div class="number"><?php echo $total_appointments; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Today's Appointments</h3>
                <div class="number" style="color: var(--warning-color);"><?php echo $today_appointments; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Pending Requests</h3>
                <div class="number" style="color: var(--danger-color);"><?php echo $pending_appointments; ?></div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header">Quick Actions</h2>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="appointments.php" class="btn btn-primary">View All Appointments</a>
                <a href="schedule.php" class="btn btn-success">Manage Schedule</a>
                <a href="patients.php" class="btn btn-secondary">View Patients</a>
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
                            <th>Contact</th>
                            <th>Blood Group</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($apt = $today_apt_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['phone']); ?></td>
                                <td><?php echo htmlspecialchars($apt['blood_group'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($apt['status']); ?>">
                                        <?php echo $apt['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="appointment_details.php?id=<?php echo $apt['id']; ?>" 
                                       class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">View</a>
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
