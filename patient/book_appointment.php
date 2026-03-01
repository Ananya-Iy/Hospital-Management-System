<?php
require_once '../includes/config.php';
requireRole('patient');

$error = '';
$success = '';

$user_id = $_SESSION['user_id'];
$patient_query = "SELECT * FROM patients WHERE user_id = $user_id";
$patient_result = $conn->query($patient_query);
$patient = $patient_result->fetch_assoc();
$patient_id = $patient['id'];

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;

if ($doctor_id === 0) {
    header('Location: doctors.php');
    exit();
}

$doctor_query = "SELECT d.*, u.name, u.email, u.phone 
                 FROM doctors d
                 JOIN users u ON d.user_id = u.id
                 WHERE d.id = $doctor_id";
$doctor_result = $conn->query($doctor_query);

if ($doctor_result->num_rows === 0) {
    header('Location: doctors.php');
    exit();
}

$doctor = $doctor_result->fetch_assoc();

$schedule_query = "SELECT * FROM doctor_schedules WHERE doctor_id = $doctor_id AND is_available = 1";
$schedules = $conn->query($schedule_query);

$leave_query = "SELECT leave_date FROM doctor_leaves WHERE doctor_id = $doctor_id";
$leaves = $conn->query($leave_query);
$leave_dates = [];
while ($leave = $leaves->fetch_assoc()) {
    $leave_dates[] = $leave['leave_date'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_date = sanitize($_POST['appointment_date']);
    $appointment_time = sanitize($_POST['appointment_time']);
    $symptoms = sanitize($_POST['symptoms']);

    if (empty($appointment_date) || empty($appointment_time)) {
        $error = 'Please select date and time for appointment';
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'Cannot book appointment for past dates';
    } elseif (in_array($appointment_date, $leave_dates)) {
        $error = 'Doctor is not available on selected date';
    } else {
        $check_query = "SELECT id FROM appointments 
                       WHERE doctor_id = $doctor_id 
                       AND appointment_date = '$appointment_date' 
                       AND appointment_time = '$appointment_time'
                       AND status NOT IN ('Cancelled')";
        $check_result = $conn->query($check_query);

        if ($check_result->num_rows > 0) {
            $error = 'This time slot is already booked. Please select another time.';
        } else {
            $insert_query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, status) 
                            VALUES ($patient_id, $doctor_id, '$appointment_date', '$appointment_time', '$symptoms', 'Requested')";

            if ($conn->query($insert_query)) {
                $success = 'Appointment booked successfully! The doctor will confirm your appointment soon.';
                $_POST = array(); 
            } else {
                $error = 'Failed to book appointment. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Hospital Management System</title>
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
        <h1>Book Appointment</h1>

        <div class="card">
            <h2 class="card-header">Doctor Information</h2>
            <div style="display: grid; grid-template-columns: auto 1fr; gap: 1rem;">
                <div style="font-size: 4rem;">👨‍⚕️</div>
                <div>
                    <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($doctor['name']); ?></h3>
                    <p style="color: var(--secondary-color); font-weight: 500; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($doctor['specialization']); ?>
                    </p>
                    <?php if (!empty($doctor['qualification'])): ?>
                        <p style="margin-bottom: 0.25rem;">📜 <?php echo htmlspecialchars($doctor['qualification']); ?></p>
                    <?php endif; ?>
                    <?php if ($doctor['experience']): ?>
                        <p style="margin-bottom: 0.25rem;">⏱️ <?php echo $doctor['experience']; ?> years experience</p>
                    <?php endif; ?>
                    <p style="font-weight: bold; color: var(--success-color);">
                        💰 Consultation Fee: $<?php echo number_format($doctor['consultation_fee'], 2); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header">Doctor's Available Schedule</h2>
            <?php if ($schedules->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Available Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($schedule = $schedules->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $schedule['day_of_week']; ?></td>
                                <td><?php echo formatTime($schedule['start_time']) . ' - ' . formatTime($schedule['end_time']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No schedule information available.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-header">Book Your Appointment</h2>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?><br>
                    <a href="appointments.php">View your appointments</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="appointment_date">Appointment Date *</label>
                        <input type="date" id="appointment_date" name="appointment_date" required
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo isset($_POST['appointment_date']) ? htmlspecialchars($_POST['appointment_date']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="appointment_time">Appointment Time *</label>
                        <input type="time" id="appointment_time" name="appointment_time" required
                               value="<?php echo isset($_POST['appointment_time']) ? htmlspecialchars($_POST['appointment_time']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="symptoms">Symptoms / Reason for Visit</label>
                    <textarea id="symptoms" name="symptoms" rows="4" placeholder="Describe your symptoms or reason for consultation..."><?php echo isset($_POST['symptoms']) ? htmlspecialchars($_POST['symptoms']) : ''; ?></textarea>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Book Appointment</button>
                    <a href="doctors.php" class="btn btn-secondary">Back to Doctors</a>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/main.js"></script>
    <script>
        const leaveDates = <?php echo json_encode($leave_dates); ?>;
        const dateInput = document.getElementById('appointment_date');
        
        dateInput.addEventListener('input', function() {
            const selectedDate = this.value;
            if (leaveDates.includes(selectedDate)) {
                alert('Doctor is not available on this date. Please select another date.');
                this.value = '';
            }
        });
    </script>
</body>
</html>
