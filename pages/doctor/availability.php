<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Hospital-Management-System/includes/config.php';
requireRole('doctor');

$user_id   = $_SESSION['user_id'];
$doc_row   = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id")->fetch_assoc();
$doctor_id = $doc_row['id'] ?? null;

$success = '';
$error   = '';

// ── Ensure leave table exists (safe to run every time) ────────────────────────
$conn->query(
    "CREATE TABLE IF NOT EXISTS doctor_leave (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id   INT  NOT NULL,
        leave_date  DATE NOT NULL,
        reason      VARCHAR(255) DEFAULT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_leave (doctor_id, leave_date),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )"
);

// ── Handle weekly schedule save ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $days  = $_POST['days'] ?? [];
    $from  = sanitize($_POST['available_from'] ?? '09:00');
    $to    = sanitize($_POST['available_to']   ?? '17:00');

    $allowed_days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $days = array_filter($days, fn($d) => in_array($d, $allowed_days));
    $days_str = implode(',', $days);

    if (empty($days)) {
        $error = 'Please select at least one working day.';
    } elseif ($from >= $to) {
        $error = 'End time must be after start time.';
    } else {
        $stmt = $conn->prepare(
            "UPDATE doctors SET available_days = ?, available_from = ?, available_to = ? WHERE id = ?"
        );
        $stmt->bind_param("sssi", $days_str, $from, $to, $doctor_id);
        if ($stmt->execute()) {
            $success = 'Weekly schedule updated successfully!';
            $doc_row['available_days'] = $days_str;
            $doc_row['available_from'] = $from;
            $doc_row['available_to']   = $to;
        } else {
            $error = 'Failed to update schedule.';
        }
        $stmt->close();
    }
}

// ── Handle add leave ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave'])) {
    $leave_date = sanitize($_POST['leave_date'] ?? '');
    $reason     = sanitize($_POST['reason']     ?? '');

    if (empty($leave_date)) {
        $error = 'Please select a date.';
    } elseif ($leave_date < date('Y-m-d')) {
        $error = 'Cannot add leave for past dates.';
    } else {
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO doctor_leave (doctor_id, leave_date, reason) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("iss", $doctor_id, $leave_date, $reason);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = 'Day off added: ' . date('M d, Y', strtotime($leave_date)) . '.';
        } elseif ($stmt->affected_rows === 0) {
            $error = 'This date is already marked as a day off.';
        } else {
            $error = 'Failed to add day off.';
        }
        $stmt->close();
    }
}

// ── Handle remove leave ───────────────────────────────────────────────────────
if (isset($_GET['remove'])) {
    $rid = intval($_GET['remove']);
    $stmt = $conn->prepare("DELETE FROM doctor_leave WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $rid, $doctor_id);
    $stmt->execute();
    $stmt->close();
    header("Location: availability.php?removed=1"); exit;
}

// ── Load leave dates ──────────────────────────────────────────────────────────
$leaves = [];
$lr = $conn->query(
    "SELECT * FROM doctor_leave WHERE doctor_id = $doctor_id AND leave_date >= CURDATE()
     ORDER BY leave_date ASC"
);
while ($r = $lr->fetch_assoc()) $leaves[] = $r;

// Leave dates as JSON for calendar highlighting
$leave_dates_json = json_encode(array_column($leaves, 'leave_date'));

// Parse current schedule
$current_days = array_map('trim', explode(',', $doc_row['available_days'] ?? 'Mon,Tue,Wed,Thu,Fri'));
$from_time    = substr($doc_row['available_from'] ?? '09:00:00', 0, 5);
$to_time      = substr($doc_row['available_to']   ?? '17:00:00', 0, 5);

// Time slots derived from schedule (for display in booking)
$all_slots = [];
if ($from_time && $to_time) {
    $t = strtotime($from_time);
    $end = strtotime($to_time);
    while ($t < $end) {
        $all_slots[] = date('H:i', $t);
        $t = strtotime('+1 hour', $t);
    }
}

// How many upcoming appointments this week
$week_apts = $conn->query(
    "SELECT COUNT(*) as c FROM appointments
     WHERE doctor_id = $doctor_id
     AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     AND status NOT IN ('Cancelled')"
)->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule · Valora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif}
        :root{
            --n1:#F2F2F2;--n2:#E6E6E6;--n3:#DADADA;--n4:#C6C6C6;--n5:#9E9E9E;--n6:#6E6E6E;--n7:#3F3F3F;--n8:#1C1C1C;
            --m50:#D8C9CE;--m100:#C5A8B3;--m200:#A56C7E;--m300:#842646;--m400:#7A2141;--m500:#641732;
            --sl:#C6D8D2;--sp:#39C37A;--sd:#2E955C;
            --wl:#E5D8C8;--wp:#F48B05;--wd:#B36805;
            --el:#E2D0CD;--ep:#F04233;--ed:#B03125;
            --il:#C9D3E6;--ip:#0E3E9E;
            --bg:#F8F9FC;
            --sh-md:0 8px 16px rgba(0,0,0,.04);
            --sh-lg:0 16px 24px rgba(0,0,0,.04);
            --sh-xl:0 24px 32px rgba(100,23,50,.08);
        }
        html,body{height:100%}
        body{background:var(--bg);color:var(--n8);min-height:100vh;display:flex;flex-direction:column;line-height:1.5}
        a{text-decoration:none}
        header{background:rgba(255,255,255,.95);backdrop-filter:blur(10px);box-shadow:var(--sh-md);position:sticky;top:0;z-index:50;border-bottom:1px solid rgba(218,218,218,.3)}
        nav{display:flex;justify-content:space-between;align-items:center;padding:1rem 2rem;max-width:1400px;margin:0 auto}
        .logo{font-size:1.8rem;font-weight:800;color:var(--m300);letter-spacing:-.5px;position:relative}
        .logo::after{content:'';position:absolute;bottom:-4px;left:0;width:40%;height:3px;background:var(--m300);border-radius:4px}
        .nav-right{display:flex;align-items:center;gap:1rem}
        .user-pill{display:flex;align-items:center;gap:.8rem;padding:.5rem 1.2rem;background:white;border-radius:100px;border:1px solid var(--n3);color:var(--n8);font-weight:600}
        .user-pill i{color:var(--m300)}
        .logout-btn{padding:.5rem 1.2rem;background:var(--n2);border-radius:100px;font-weight:600;color:var(--n7);font-size:.9rem}
        .logout-btn:hover{background:var(--n3)}
        .main-tabs{display:flex;justify-content:center;gap:.5rem;margin:1.5rem auto;padding:.5rem;background:white;border-radius:100px;box-shadow:var(--sh-lg);max-width:960px;border:1px solid var(--n3);flex-wrap:wrap}
        .tab{padding:.7rem 1.4rem;border-radius:100px;font-weight:600;color:var(--n7);transition:.25s;display:flex;align-items:center;gap:.45rem;font-size:.9rem;white-space:nowrap}
        .tab i{color:var(--m300);font-size:.95rem;transition:color .25s}
        .tab:hover{background:var(--m50);color:var(--m300)}
        .tab.active{background:var(--m300);color:white;box-shadow:0 8px 16px -4px rgba(132,38,70,.4)}
        .tab.active i{color:white}
        .page-wrapper{flex:1;display:flex;flex-direction:column}
        .container{max-width:1100px;margin:1rem auto 3rem;padding:0 2rem;flex:1;width:100%}
        .page-header{margin-bottom:2rem}
        .page-header h1{font-size:2.2rem;font-weight:700;position:relative;padding-bottom:.8rem}
        .page-header h1::after{content:'';position:absolute;bottom:0;left:0;width:80px;height:4px;background:linear-gradient(90deg,var(--m300),var(--ip));border-radius:4px}
        .page-header p{color:var(--n6);margin-top:.8rem}

        .alert{padding:1.2rem 1.5rem;border-radius:16px;margin-bottom:2rem;display:flex;align-items:center;gap:1rem;animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:var(--sl);color:var(--sd);border-left:6px solid var(--sp)}
        .alert-error{background:var(--el);color:var(--ed);border-left:6px solid var(--ep)}

        /* GRID LAYOUT */
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:2rem}
        .full-col{margin-bottom:2rem}

        /* SECTION CARD */
        .section{background:white;border-radius:24px;padding:2rem;box-shadow:var(--sh-md);border:1px solid var(--n3);position:relative;overflow:hidden}
        .section::before{content:'';position:absolute;top:0;left:0;width:5px;height:100%;background:linear-gradient(180deg,var(--m300),var(--ip))}
        .sec-title{font-size:1.1rem;font-weight:700;color:var(--n8);margin-bottom:1.5rem;display:flex;align-items:center;gap:.6rem}
        .sec-title i{color:var(--m300)}

        /* DAY SELECTOR */
        .days-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:.5rem;margin-bottom:1.5rem}
        .day-toggle{position:relative}
        .day-toggle input{display:none}
        .day-toggle label{
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:.7rem .3rem;border-radius:14px;border:2px solid var(--n3);
            font-weight:700;font-size:.8rem;cursor:pointer;transition:.2s;
            background:var(--n1);color:var(--n6);gap:.3rem;
        }
        .day-toggle label .day-letter{font-size:1rem;font-weight:800}
        .day-toggle input:checked + label{
            background:var(--m300);color:white;border-color:var(--m300);
            box-shadow:0 4px 12px rgba(132,38,70,.3);
        }
        .day-toggle label:hover{border-color:var(--m200);background:var(--m50);color:var(--m300)}
        .day-toggle input:checked + label:hover{background:var(--m400)}

        /* TIME INPUTS */
        .time-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem}
        .fg label{display:block;font-weight:600;font-size:.88rem;margin-bottom:.5rem;color:var(--n7)}
        .fg label i{color:var(--m300);margin-right:.3rem}
        .fc{width:100%;padding:.9rem 1.1rem;border:2px solid var(--n3);border-radius:14px;font-size:.95rem;background:var(--n1);font-family:inherit;transition:.2s}
        .fc:focus{outline:none;border-color:var(--m300);background:white}

        /* SAVE BUTTON */
        .btn-save{padding:.9rem 2rem;background:linear-gradient(135deg,var(--m300),var(--m400));color:white;border:none;border-radius:40px;font-weight:700;font-size:.95rem;cursor:pointer;font-family:inherit;transition:.2s;display:flex;align-items:center;gap:.6rem;box-shadow:0 8px 16px -4px var(--m200)}
        .btn-save:hover{background:linear-gradient(135deg,var(--m400),var(--m500));transform:translateY(-2px)}

        /* SCHEDULE PREVIEW */
        .preview-box{background:var(--n1);border-radius:16px;padding:1.2rem 1.4rem;margin-top:1.5rem}
        .preview-title{font-size:.8rem;color:var(--n5);text-transform:uppercase;font-weight:600;margin-bottom:.8rem}
        .slots-wrap{display:flex;flex-wrap:wrap;gap:.5rem}
        .slot-pill{padding:.3rem .8rem;border-radius:40px;background:white;border:1px solid var(--n3);font-size:.8rem;font-weight:600;color:var(--n7)}
        .slot-pill.active-slot{background:var(--m50);border-color:var(--m200);color:var(--m300)}

        /* LEAVE CALENDAR */
        .calendar{background:var(--n1);border-radius:20px;padding:1.2rem;margin-bottom:1.5rem}
        .cal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
        .cal-header h3{font-weight:700;font-size:1rem}
        .cal-nav{padding:.3rem .7rem;background:white;border-radius:8px;border:1px solid var(--n3);cursor:pointer;font-size:.85rem;font-weight:600;color:var(--n7);transition:.2s}
        .cal-nav:hover{background:var(--m50);color:var(--m300);border-color:var(--m200)}
        .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:.3rem}
        .cal-day-name{text-align:center;font-size:.72rem;font-weight:600;color:var(--n5);padding:.3rem 0}
        .cal-cell{aspect-ratio:1;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:600;cursor:default;position:relative}
        .cal-cell.today{background:var(--m50);color:var(--m300);font-weight:800}
        .cal-cell.has-leave{background:var(--el);color:var(--ed)}
        .cal-cell.off-day{background:var(--n2);color:var(--n4)}
        .cal-cell.available{color:var(--n7)}
        .cal-cell.past{color:var(--n4)}
        .cal-cell.empty{background:transparent}

        /* LEAVE FORM */
        .leave-add-row{display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap}
        .leave-add-row .fg{flex:1;min-width:150px}
        .btn-add-leave{padding:.9rem 1.5rem;background:var(--ep);color:white;border:none;border-radius:40px;font-weight:700;font-size:.88rem;cursor:pointer;font-family:inherit;transition:.2s;display:flex;align-items:center;gap:.5rem;white-space:nowrap}
        .btn-add-leave:hover{background:var(--ed)}

        /* LEAVE LIST */
        .leave-item{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.2rem;background:var(--wl);border-radius:14px;margin-bottom:.7rem;flex-wrap:wrap;gap:.8rem}
        .leave-item:last-child{margin-bottom:0}
        .leave-date-badge{font-weight:700;font-size:.95rem;color:var(--wd);display:flex;align-items:center;gap:.5rem}
        .leave-reason-text{font-size:.85rem;color:var(--n6);margin-top:.2rem}
        .btn-remove-leave{padding:.4rem .9rem;background:var(--el);color:var(--ed);border-radius:40px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;transition:.2s}
        .btn-remove-leave:hover{background:var(--ep);color:white}

        /* INFO BANNER */
        .info-banner{background:var(--il);border-radius:16px;padding:1.2rem 1.4rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.8rem;color:var(--ip);font-size:.9rem}
        .info-banner i{font-size:1.2rem;flex-shrink:0}

        .empty-leave{text-align:center;padding:2rem;color:var(--n5)}
        .empty-leave i{font-size:2.5rem;display:block;margin-bottom:.8rem;color:var(--n4)}

        footer{background:var(--n7);color:var(--n2);padding:2rem;text-align:center;border-top:5px solid var(--m300);margin-top:auto}
        .footer-content{max-width:1400px;margin:0 auto}
        .footer-copy{color:var(--n5);font-size:.85rem;margin-top:.5rem}

        @media(max-width:900px){
            .two-col{grid-template-columns:1fr}
            nav{flex-direction:column;gap:.8rem;padding:1rem}
            .container{padding:0 1rem}
            .days-grid{grid-template-columns:repeat(7,1fr)}
        }
        @media(max-width:500px){
            .days-grid{grid-template-columns:repeat(4,1fr)}
            .time-row{grid-template-columns:1fr}
        }
    </style>
</head>
<body>

<header>
    <nav>
        <a href="dashboard-doctor.php" class="logo">Valora</a>
        <div class="nav-right">
            <span class="user-pill"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
</header>

<div class="page-wrapper">

    <div class="main-tabs">
        <a href="appointments.php"  class="tab"><i class="fas fa-calendar-alt"></i> Appointments</a>
        <a href="patients.php"      class="tab"><i class="fas fa-users"></i> Patients</a>
        <a href="prescriptions.php" class="tab"><i class="fas fa-prescription"></i> Prescriptions</a>
        <a href="availability.php"  class="tab active"><i class="fas fa-calendar-times"></i> Schedule</a>
        <a href="profile.php"       class="tab"><i class="fas fa-user-md"></i> Profile</a>
    </div>

    <div class="container">

        <div class="page-header">
            <h1>Manage Schedule</h1>
            <p>Set your weekly working hours and mark days off — patients only see available slots when booking.</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['removed'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Day off removed successfully.</div>
        <?php endif; ?>

        <?php if ($week_apts > 0): ?>
        <div class="info-banner">
            <i class="fas fa-info-circle"></i>
            You have <strong><?php echo $week_apts; ?> upcoming appointment<?php echo $week_apts > 1 ? 's' : ''; ?></strong> this week. Changes to your schedule won't affect already-booked appointments.
        </div>
        <?php endif; ?>

        <div class="two-col">

            <!-- ── WEEKLY SCHEDULE ── -->
            <div class="section">
                <div class="sec-title"><i class="fas fa-calendar-week"></i> Weekly Working Days & Hours</div>

                <form method="POST" id="scheduleForm">
                    <p style="font-size:.88rem;color:var(--n6);margin-bottom:1rem">Select which days you work and your hours for each day.</p>

                    <?php
                    $days_map = [
                        'Mon' => 'Monday',
                        'Tue' => 'Tuesday',
                        'Wed' => 'Wednesday',
                        'Thu' => 'Thursday',
                        'Fri' => 'Friday',
                        'Sat' => 'Saturday',
                        'Sun' => 'Sunday',
                    ];
                    ?>

                    <div class="days-grid">
                        <?php foreach ($days_map as $code => $full): ?>
                        <div class="day-toggle">
                            <input type="checkbox" name="days[]" value="<?php echo $code; ?>"
                                   id="day_<?php echo $code; ?>"
                                   <?php echo in_array($code, $current_days) ? 'checked' : ''; ?>
                                   onchange="updatePreview()">
                            <label for="day_<?php echo $code; ?>">
                                <span class="day-letter"><?php echo $code[0]; ?></span>
                                <span><?php echo $code; ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="time-row">
                        <div class="fg">
                            <label><i class="fas fa-clock"></i> Start Time</label>
                            <input type="time" name="available_from" class="fc"
                                   value="<?php echo $from_time; ?>" required onchange="updatePreview()">
                        </div>
                        <div class="fg">
                            <label><i class="fas fa-clock"></i> End Time</label>
                            <input type="time" name="available_to" class="fc"
                                   value="<?php echo $to_time; ?>" required onchange="updatePreview()">
                        </div>
                    </div>

                    <!-- PREVIEW -->
                    <div class="preview-box">
                        <div class="preview-title"><i class="fas fa-eye"></i> Preview — Available Time Slots</div>
                        <div class="slots-wrap" id="slotsPreview">
                            <?php foreach ($all_slots as $slot): ?>
                            <span class="slot-pill active-slot"><?php echo date('h:i A', strtotime($slot)); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div id="noSlots" style="display:<?php echo empty($all_slots)?'block':'none';?>;color:var(--n5);font-size:.88rem;margin-top:.5rem">
                            No slots — adjust your hours.
                        </div>
                    </div>

                    <button type="submit" name="save_schedule" class="btn-save" style="margin-top:1.5rem">
                        <i class="fas fa-save"></i> Save Weekly Schedule
                    </button>
                </form>
            </div>

            <!-- ── CALENDAR VIEW ── -->
            <div class="section">
                <div class="sec-title"><i class="fas fa-calendar-alt"></i> This Month's Availability</div>
                <div class="calendar">
                    <div class="cal-header">
                        <button class="cal-nav" onclick="prevMonth()">‹</button>
                        <h3 id="calTitle"></h3>
                        <button class="cal-nav" onclick="nextMonth()">›</button>
                    </div>
                    <div class="cal-grid" id="calDayNames"></div>
                    <div class="cal-grid" id="calCells"></div>
                </div>

                <!-- LEGEND -->
                <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.8rem;color:var(--n6);margin-bottom:1.5rem">
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--m50);vertical-align:middle;margin-right:.3rem"></span>Today</span>
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--el);vertical-align:middle;margin-right:.3rem"></span>Day Off</span>
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--n2);vertical-align:middle;margin-right:.3rem"></span>Non-working day</span>
                </div>

                <!-- ADD DAY OFF -->
                <div class="sec-title" style="margin-top:0"><i class="fas fa-calendar-times"></i> Add Day Off</div>
                <form method="POST">
                    <div class="leave-add-row">
                        <div class="fg">
                            <label>Date</label>
                            <input type="date" name="leave_date" class="fc"
                                   min="<?php echo date('Y-m-d'); ?>" id="leaveDateInput" required>
                        </div>
                        <div class="fg" style="flex:2">
                            <label>Reason <span style="color:var(--n5);font-weight:400">(optional)</span></label>
                            <input type="text" name="reason" class="fc" placeholder="e.g. Conference, Vacation, Personal...">
                        </div>
                        <button type="submit" name="add_leave" class="btn-add-leave">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- ── UPCOMING DAYS OFF ── -->
        <div class="section full-col">
            <div class="sec-title"><i class="fas fa-list"></i> Upcoming Days Off</div>

            <?php if (count($leaves) > 0): ?>
                <?php foreach ($leaves as $lv): ?>
                <div class="leave-item">
                    <div>
                        <div class="leave-date-badge">
                            <i class="fas fa-calendar-times"></i>
                            <?php echo date('l, M d, Y', strtotime($lv['leave_date'])); ?>
                            <?php if (date('Y-m-d', strtotime($lv['leave_date'])) === date('Y-m-d')): ?>
                            <span style="background:var(--ep);color:white;padding:.15rem .6rem;border-radius:40px;font-size:.72rem">Today</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($lv['reason'])): ?>
                        <div class="leave-reason-text"><i class="fas fa-comment"></i> <?php echo htmlspecialchars($lv['reason']); ?></div>
                        <?php endif; ?>
                    </div>
                    <a href="?remove=<?php echo $lv['id']; ?>"
                       class="btn-remove-leave"
                       onclick="return confirm('Remove this day off?')">
                        <i class="fas fa-trash"></i> Remove
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-leave">
                    <i class="fas fa-calendar-check"></i>
                    <p>No days off scheduled — you are fully available!</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<footer>
    <div class="footer-content">
        <p style="font-size:1.1rem;font-weight:600">Valora Medical Center</p>
        <p style="color:var(--n4);font-size:.9rem">University Project · All information is fictional</p>
        <p class="footer-copy">&copy; 2026 Valora HMS. All rights reserved.</p>
    </div>
</footer>

<script>
// ── Slot preview ──────────────────────────────────────────────────────────────
function updatePreview() {
    const from  = document.querySelector('input[name="available_from"]').value;
    const to    = document.querySelector('input[name="available_to"]').value;
    const wrap  = document.getElementById('slotsPreview');
    const noSl  = document.getElementById('noSlots');

    wrap.innerHTML = '';

    if (!from || !to || from >= to) {
        noSl.style.display = 'block';
        return;
    }

    let slots = [];
    let [fh, fm] = from.split(':').map(Number);
    let [th, tm] = to.split(':').map(Number);
    let fMins = fh * 60 + fm;
    let tMins = th * 60 + tm;

    for (let m = fMins; m < tMins; m += 60) {
        let hh = Math.floor(m / 60);
        let ampm = hh >= 12 ? 'PM' : 'AM';
        let h12  = hh % 12 || 12;
        slots.push(`${h12}:00 ${ampm}`);
    }

    if (slots.length === 0) {
        noSl.style.display = 'block';
    } else {
        noSl.style.display = 'none';
        slots.forEach(s => {
            const sp = document.createElement('span');
            sp.className = 'slot-pill active-slot';
            sp.textContent = s;
            wrap.appendChild(sp);
        });
    }
}

// ── Calendar ──────────────────────────────────────────────────────────────────
const leaveDates  = <?php echo $leave_dates_json; ?>;
const workingDays = <?php echo json_encode($current_days); ?>;
const dayAbbrs    = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const dayNames    = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

let calYear  = new Date().getFullYear();
let calMonth = new Date().getMonth();

function renderCalendar() {
    const title = document.getElementById('calTitle');
    const names = document.getElementById('calDayNames');
    const cells = document.getElementById('calCells');

    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    title.textContent = monthNames[calMonth] + ' ' + calYear;

    // Day name headers
    names.innerHTML = dayNames.map(d => `<div class="cal-day-name">${d[0]}</div>`).join('');

    const firstDay  = new Date(calYear, calMonth, 1).getDay();
    const daysInMon = new Date(calYear, calMonth + 1, 0).getDate();
    const today     = new Date().toISOString().slice(0,10);

    let html = '';

    // Empty cells before first day
    for (let i = 0; i < firstDay; i++) html += '<div class="cal-cell empty"></div>';

    for (let d = 1; d <= daysInMon; d++) {
        const mm    = String(calMonth + 1).padStart(2,'0');
        const dd    = String(d).padStart(2,'0');
        const dateStr = `${calYear}-${mm}-${dd}`;
        const dow   = new Date(calYear, calMonth, d).getDay();
        const dayAb = dayAbbrs[dow];
        const isPast = dateStr < today;
        const isToday    = dateStr === today;
        const isLeave    = leaveDates.includes(dateStr);
        const isWorkDay  = workingDays.includes(dayAb);

        let cls = 'cal-cell';
        let title = '';
        if (isToday)   { cls += ' today'; title = 'Today'; }
        else if (isLeave)   { cls += ' has-leave'; title = 'Day off'; }
        else if (!isWorkDay) { cls += ' off-day'; title = 'Non-working day'; }
        else if (isPast)    { cls += ' past'; }
        else               { cls += ' available'; }

        html += `<div class="${cls}" title="${title}">${d}</div>`;
    }

    cells.innerHTML = html;
}

function prevMonth() {
    calMonth--;
    if (calMonth < 0) { calMonth = 11; calYear--; }
    renderCalendar();
}

function nextMonth() {
    calMonth++;
    if (calMonth > 11) { calMonth = 0; calYear++; }
    renderCalendar();
}

renderCalendar();
</script>

</body>
</html>