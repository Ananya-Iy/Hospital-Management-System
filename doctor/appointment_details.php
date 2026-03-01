<?php
require_once '../includes/config.php';
requireRole('doctor');

$user_id = $_SESSION['user_id'];
$doctor = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id")->fetch_assoc();
$doctor_id = $doctor['id'];

$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$success = '';
$error = '';

$query = "SELECT a.*, u.name as patient_name, p.* 
          FROM appointments a
          JOIN patients pat ON a.patient_id = pat.id
          JOIN users u ON pat.user_id = u.id
          JOIN patients p ON pat.id = p.id
          WHERE a.id = $appointment_id AND a.doctor_id = $doctor_id";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    header('Location: appointments.php');
    exit();
}

$appointment = $result->fetch_assoc();

if (isset($_POST['update_diagnosis'])) {
    $diagnosis_notes = sanitize($_POST['diagnosis_notes']);
    $status = sanitize($_POST['status']);
    
    $update_query = "UPDATE appointments 
                     SET diagnosis_notes = '$diagnosis_notes', status = '$status' 
                     WHERE id = $appointment_id";
    if ($conn->query($update_query)) {
        $success = 'Diagnosis updated successfully';
        $appointment['diagnosis_notes'] = $diagnosis_notes;
        $appointment['status'] = $status;
    }
}

if (isset($_POST['create_prescription'])) {
    $medication = sanitize($_POST['medication']);
    $dosage = sanitize($_POST['dosage']);
    $doctor_notes = sanitize($_POST['doctor_notes']);
    
    $insert_query = "INSERT INTO prescriptions (appointment_id, medication, dosage, doctor_notes) 
                     VALUES ($appointment_id, '$medication', '$dosage', '$doctor_notes')";
    if ($conn->query($insert_query)) {
        $success = 'Prescription created successfully';
    } else {
        $error = 'Failed to create prescription';
    }
}

$prescription_query = "SELECT * FROM prescriptions WHERE appointment_id = $appointment_id";
$prescription = $conn->query($prescription_query);
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
                <li><a href="appointments.php">Appointments</a></li>
                <li><a href="schedule.php">My Schedule</a></li>
                <li><a href="patients.php">Patients</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h1>Appointment Details</h1>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-header">Patient Information</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo $appointment['date_of_birth'] ? formatDate($appointment['date_of_birth']) : 'N/A'; ?></p>
                    <p><strong>Gender:</strong> <?php echo ucfirst($appointment['gender'] ?: 'N/A'); ?></p>
                    <p><strong>Blood Group:</strong> <?php echo htmlspecialchars($appointment['blood_group'] ?: 'N/A'); ?></p>
                </div>
                <div>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($appointment['phone']); ?></p>
                    <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($appointment['emergency_contact'] ?: 'N/A'); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($appointment['address'] ?: 'N/A'); ?></p>
                </div>
            </div>

            <?php if (!empty($appointment['medical_history'])): ?>
                <div style="margin-top: 1.5rem;">
                    <strong>Medical History:</strong>
                    <p style="margin-top: 0.5rem; padding: 1rem; background-color: #fff3cd; border-radius: 4px;">
                        <?php echo nl2br(htmlspecialchars($appointment['medical_history'])); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-header">Appointment Information</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <p><strong>Appointment ID:</strong> #<?php echo $appointment['id']; ?></p>
                    <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
                    <p><strong>Time:</strong> <?php echo formatTime($appointment['appointment_time']); ?></p>
                </div>
                <div>
                    <p><strong>Status:</strong> 
                        <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                            <?php echo $appointment['status']; ?>
                        </span>
                    </p>
                    <p><strong>Type:</strong> <?php echo $appointment['is_walkin'] ? 'Walk-in' : 'Online'; ?></p>
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
        </div>

        <div class="card">
            <h2 class="card-header">Diagnosis</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="diagnosis_notes">Diagnosis Notes</label>
                    <textarea id="diagnosis_notes" name="diagnosis_notes" rows="6" required><?php echo htmlspecialchars($appointment['diagnosis_notes']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Update Status</label>
                    <select id="status" name="status" required>
                        <option value="Confirmed" <?php echo ($appointment['status'] === 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="Completed" <?php echo ($appointment['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo ($appointment['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <button type="submit" name="update_diagnosis" class="btn btn-primary">Update Diagnosis</button>
            </form>
        </div>

        <?php if ($prescription->num_rows > 0): ?>
            <div class="card">
                <h2 class="card-header">Prescription (Already Created)</h2>
                <?php $presc = $prescription->fetch_assoc(); ?>
                <div style="padding: 1rem; background-color: #d4edda; border-radius: 4px; margin-bottom: 1rem;">
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
            </div>
        <?php else: ?>
            <div class="card">
                <h2 class="card-header">Create Prescription</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="medication">Medication *</label>
                        <textarea id="medication" name="medication" rows="4" required 
                                  placeholder="List medications (e.g., Amoxicillin 500mg, Ibuprofen 200mg)"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="dosage">Dosage Instructions *</label>
                        <textarea id="dosage" name="dosage" rows="4" required 
                                  placeholder="Specify dosage and frequency (e.g., Take 1 tablet twice daily after meals for 7 days)"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="doctor_notes">Additional Notes</label>
                        <textarea id="doctor_notes" name="doctor_notes" rows="3" 
                                  placeholder="Any additional instructions or precautions"></textarea>
                    </div>

                    <button type="submit" name="create_prescription" class="btn btn-success">Create Prescription</button>
                </form>
            </div>
        <?php endif; ?>

        <div style="margin-top: 2rem;">
            <a href="appointments.php" class="btn btn-secondary">Back to Appointments</a>
        </div>
    </div>

    <script src="../js/main.js"></script>
</body>
</html>
