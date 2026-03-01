<?php
require_once '../includes/config.php';
requireRole('patient');

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$specialization = isset($_GET['specialization']) ? sanitize($_GET['specialization']) : '';

$query = "SELECT d.*, u.name, u.email, u.phone 
          FROM doctors d
          JOIN users u ON d.user_id = u.id
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND u.name LIKE '%$search%'";
}

if (!empty($specialization)) {
    $query .= " AND d.specialization = '$specialization'";
}

$query .= " ORDER BY u.name";
$doctors = $conn->query($query);

$spec_query = "SELECT DISTINCT specialization FROM doctors ORDER BY specialization";
$specializations = $conn->query($spec_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Doctors - Hospital Management System</title>
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
        <h1>Find Doctors</h1>

        <div class="card">
            <h2 class="card-header">Search & Filter</h2>
            <form method="GET" action="" class="search-filter">
                <input type="text" name="search" placeholder="Search by doctor name..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="specialization">
                    <option value="">All Specializations</option>
                    <?php while ($spec = $specializations->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($spec['specialization']); ?>"
                                <?php echo ($specialization === $spec['specialization']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec['specialization']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="doctors.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <div class="doctor-grid">
            <?php if ($doctors->num_rows > 0): ?>
                <?php while ($doctor = $doctors->fetch_assoc()): ?>
                    <div class="doctor-card">
                        <div style="font-size: 3rem; text-align: center; margin-bottom: 1rem;">👨‍⚕️</div>
                        <h3><?php echo htmlspecialchars($doctor['name']); ?></h3>
                        <div class="specialization"><?php echo htmlspecialchars($doctor['specialization']); ?></div>
                        
                        <?php if (!empty($doctor['qualification'])): ?>
                            <div class="info">📜 <?php echo htmlspecialchars($doctor['qualification']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($doctor['experience']): ?>
                            <div class="info">⏱️ <?php echo $doctor['experience']; ?> years experience</div>
                        <?php endif; ?>
                        
                        <div class="info">💰 Consultation Fee: $<?php echo number_format($doctor['consultation_fee'], 2); ?></div>
                        
                        <?php if (!empty($doctor['bio'])): ?>
                            <p style="margin-top: 1rem; color: var(--light-text); font-size: 0.875rem;">
                                <?php echo htmlspecialchars(substr($doctor['bio'], 0, 100)); ?>
                                <?php echo (strlen($doctor['bio']) > 100) ? '...' : ''; ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                            <a href="book_appointment.php?doctor_id=<?php echo $doctor['id']; ?>" 
                               class="btn btn-primary" style="flex: 1;">Book Appointment</a>
                            <a href="doctor_schedule.php?doctor_id=<?php echo $doctor['id']; ?>" 
                               class="btn btn-secondary" style="flex: 1;">View Schedule</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="card" style="grid-column: 1 / -1;">
                    <p>No doctors found matching your criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>
