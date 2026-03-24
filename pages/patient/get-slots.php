<?php
/**
 * get-slots.php
 * Returns available time slots for a doctor on a given date as JSON.
 * Used by the patient booking page (home.php) to dynamically load slots.
 *
 * GET params:
 *   doctor_id  (int)
 *   date       (Y-m-d)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';

header('Content-Type: application/json');

// Must be logged in as patient
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    echo json_encode(['error' => 'Unauthorized', 'slots' => []]);
    exit;
}

$doctor_id = intval($_GET['doctor_id'] ?? 0);
$date      = sanitize($_GET['date'] ?? '');

if (!$doctor_id || !$date) {
    echo json_encode(['error' => 'Missing parameters', 'slots' => []]);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
    echo json_encode(['error' => 'Invalid date', 'slots' => []]);
    exit;
}

// ── Get doctor schedule ───────────────────────────────────────────────────────
$doc = $conn->query(
    "SELECT available_days, available_from, available_to FROM doctors WHERE id = $doctor_id"
)->fetch_assoc();

if (!$doc) {
    echo json_encode(['error' => 'Doctor not found', 'slots' => []]);
    exit;
}

// Check if this date is a working day
$day_of_week  = date('D', strtotime($date)); // Mon, Tue, etc.
$working_days = array_map('trim', explode(',', $doc['available_days']));

if (!in_array($day_of_week, $working_days)) {
    echo json_encode([
        'error' => "Dr. is not available on " . date('l', strtotime($date)) . "s.",
        'slots' => []
    ]);
    exit;
}

// ── Check if doctor has a day off on this date ────────────────────────────────
// Table may not exist yet — handle gracefully
$leave_check = $conn->query(
    "SELECT id FROM doctor_leave WHERE doctor_id = $doctor_id AND leave_date = '$date' LIMIT 1"
);
if ($leave_check && $leave_check->num_rows > 0) {
    echo json_encode(['error' => 'Doctor is on leave on this date.', 'slots' => []]);
    exit;
}

// ── Build all 30-min slots from available_from to available_to ───────────────
$from = strtotime(substr($doc['available_from'], 0, 5));
$to   = strtotime(substr($doc['available_to'],   0, 5));

$all_slots = [];
for ($t = $from; $t < $to; $t = strtotime('+30 minutes', $t)) {
    $all_slots[] = date('H:i', $t);
}

if (empty($all_slots)) {
    echo json_encode(['error' => 'No slots configured for this doctor.', 'slots' => []]);
    exit;
}

// ── Get already-booked slots ──────────────────────────────────────────────────
$booked = [];
$res = $conn->query(
    "SELECT appointment_time FROM appointments
     WHERE doctor_id = $doctor_id AND appointment_date = '$date'
     AND status NOT IN ('Cancelled')"
);
while ($row = $res->fetch_assoc()) {
    $booked[] = substr($row['appointment_time'], 0, 5);
}

// ── Build response — exclude already-booked slots entirely ───────────────────
$slots = [];
foreach ($all_slots as $slot) {
    if (in_array($slot, $booked)) continue; // skip booked — don't even show them

    $start_ts = strtotime($slot);
    $end_ts   = strtotime('+30 minutes', $start_ts);
    $slots[]  = [
        'value' => $slot,
        'label' => date('h:i A', $start_ts) . ' – ' . date('h:i A', $end_ts),
    ];
}

echo json_encode(['slots' => $slots, 'working_day' => $day_of_week]);