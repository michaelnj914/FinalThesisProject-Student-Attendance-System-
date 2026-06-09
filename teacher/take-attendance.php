<?php
// Include the database connection file so $conn is available throughout this page
include '../includes/dbcon.php';

// Include the teacher session guard — redirects to login if the teacher is not logged in
include '../includes/session_teacher.php';

// Set the page title used by the layout
$pageTitle = 'Take Attendance';

// Read the teacher's assigned class ID and section (arm) ID from the session
// Cast to int to prevent non-numeric values from reaching the database
$classId    = (int)$_SESSION['classId'];
$classArmId = (int)$_SESSION['classArmId'];

// Get today's date in YYYY-MM-DD format — used as the attendance date for all queries
$today = date('Y-m-d');

// Initialise empty feedback message variables (populated after form submission)
$msg = ''; $msgType = '';

// ── Step 1: Get the currently active semester ID ──
// LIMIT 1 ensures only one row is returned even if multiple active terms exist
// ?? 0 sets $sessionTermId to 0 if no active term is found (no row returned)
$termRow = $conn->query("SELECT Id FROM tblsessionterm WHERE isActive=1 LIMIT 1")->fetch_assoc();
$sessionTermId = $termRow['Id'] ?? 0;

// ── Step 2: Check if attendance has already been submitted today ──
// Counts how many present (status=1) records exist for this class/section/date
// If the count is greater than 0, attendance was already taken today
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tblattendance WHERE classId=? AND classArmId=? AND dateTimeTaken=? AND status=1");
$stmt->bind_param("iis", $classId, $classArmId, $today);
$stmt->execute();
$alreadySubmitted = $stmt->get_result()->fetch_assoc()['c'] > 0; // true if already submitted

// ── Step 3: Pre-populate attendance rows for today (default everyone to Absent) ──
// This only runs if attendance hasn't been submitted yet
// It ensures every student has a row in tblattendance for today before the form is shown
if (!$alreadySubmitted) {

    // Fetch all students in this teacher's class and section
    $stmt = $conn->prepare("SELECT admissionNumber FROM tblstudents WHERE classId=? AND classArmId=?");
    $stmt->bind_param("ii", $classId, $classArmId);
    $stmt->execute();
    $studs = $stmt->get_result();

    // Loop through each student to check if they already have a row for today
    while ($s = $studs->fetch_assoc()) {
        $adm = $s['admissionNumber']; // Student's admission number

        // Check if an attendance record already exists for this student today
        $chk = $conn->prepare("SELECT Id FROM tblattendance WHERE admissionNo=? AND classId=? AND classArmId=? AND dateTimeTaken=?");
        $chk->bind_param("siis", $adm, $classId, $classArmId, $today);
        $chk->execute();

        // Only insert a new row if one doesn't already exist (avoid duplicates)
        if ($chk->get_result()->num_rows === 0) {
            // Insert a default "Absent" row (status=0) for this student for today
            $ins = $conn->prepare("INSERT INTO tblattendance (admissionNo,classId,classArmId,sessionTermId,status,dateTimeTaken) VALUES (?,?,?,?,0,?)");
            $ins->bind_param("siiis", $adm, $classId, $classArmId, $sessionTermId, $today);
            $ins->execute();
        }
    }
}

// ── Step 4: Handle the form submission (Save Attendance) ──
// Only runs when the form was submitted via POST and the hidden 'save' field is present
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    if ($alreadySubmitted) {
        // Prevent double-submission: show an error if attendance was already saved today
        $msg = 'Attendance for today has already been submitted.';
        $msgType = 'danger';
    } else {
        // Read the arrays of admission numbers and their statuses sent from the form
        // ?? [] provides an empty array fallback if the field wasn't submitted
        $admNos   = $_POST['admissionNos'] ?? [];
        $statuses = $_POST['statuses']     ?? [];

        // Start a database transaction so all updates succeed or all fail together
        $conn->begin_transaction();
        $ok = true; // Flag to track if all updates succeeded

        // Loop through each student submitted in the form
        foreach ($admNos as $i => $adm) {
            // Get the status for this student (1=present, 0=absent); default to 0 if missing
            $status = isset($statuses[$i]) ? (int)$statuses[$i] : 0;

            // Update the pre-inserted row for this student with the actual status
            $stmt = $conn->prepare("UPDATE tblattendance SET status=? WHERE admissionNo=? AND classId=? AND classArmId=? AND dateTimeTaken=?");
            $stmt->bind_param("isiis", $status, $adm, $classId, $classArmId, $today);

            // If any single UPDATE fails, set the flag to false and stop the loop
            if (!$stmt->execute()) { $ok = false; break; }
        }

        if ($ok) {
            // All updates succeeded — commit the transaction to permanently save changes
            $conn->commit();
            $alreadySubmitted = true; // Prevent the form from showing again on the same request
            $msg = 'Attendance saved successfully!';
            $msgType = 'success';
        } else {
            // At least one UPDATE failed — rollback to undo any partial changes
            $conn->rollback();
            $msg = 'Error saving attendance.';
            $msgType = 'danger';
        }
    }
}

// ── Step 5: Fetch all students with their attendance status for today's form ──
// LEFT JOIN tblattendance so students with no record still appear (status will be NULL)
// The JOIN conditions include classId, classArmId, and dateTimeTaken to match only today's record
// Ordered alphabetically by last name then first name
$stmt = $conn->prepare("
  SELECT s.firstName, s.lastName, s.admissionNumber, a.status
  FROM tblstudents s
  LEFT JOIN tblattendance a ON a.admissionNo=s.admissionNumber AND a.classId=? AND a.classArmId=? AND a.dateTimeTaken=?
  WHERE s.classId=? AND s.classArmId=?
  ORDER BY s.lastName, s.firstName
");
// "iisii" = int, int, string, int, int — note classId and classArmId are bound twice (for JOIN and WHERE)
$stmt->bind_param("iisii", $classId, $classArmId, $today, $classId, $classArmId);
$stmt->execute();
// Load all rows into a PHP array so they can be looped in the HTML
$studentsArr = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Declare character encoding -->
  <meta charset="utf-8">
  <!-- Make the page responsive on mobile devices -->
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Take Attendance – Faculty</title>
  <!-- Main site stylesheet -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- Font Awesome icon library (CDN) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper"> <!-- Outer wrapper for the full page layout -->

  <?php include 'sidebar.php'; ?> <!-- Render the left navigation sidebar -->

  <div class="main-content"> <!-- Content area to the right of the sidebar -->

    <?php include 'topbar.php'; ?> <!-- Render the top navigation bar -->

    <div class="page-content"> <!-- Inner padded content area -->

      <!-- Feedback alert: only shown after a form submission produces a message -->
      <?php if ($msg): ?>
        <!-- Alert type is dynamic: 'success' (green) or 'danger' (red) -->
        <!-- data-auto-dismiss tells app.js to automatically hide this alert after a few seconds -->
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss>
          <!-- Icon changes based on message type: checkmark for success, exclamation for error -->
          <i class="fas fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i>
          <?= htmlspecialchars($msg) ?>
        </div>
      <?php endif; ?>

      <!-- Page header: title on the left, today's formatted date on the right -->
      <div class="page-header">
        <h2><i class="fas fa-clipboard-check"></i> Take Attendance</h2>
        <!-- date('l, d M Y') formats today as e.g. "Monday, 15 Jan 2025" -->
        <span style="font-size:.85rem;color:var(--gray)"><i class="fas fa-calendar"></i> <?= date('l, d M Y') ?></span>
      </div>

      <!-- Info banner: shown when attendance is already done AND there's no new feedback message -->
      <!-- The !$msg condition prevents showing this banner at the same time as the success alert -->
      <?php if ($alreadySubmitted && !$msg): ?>
        <div class="alert alert-info">
          <i class="fas fa-info-circle"></i> Attendance for today has already been submitted.
          <!-- Link to the attendance history page -->
          <a href="view-attendance.php" style="color:var(--primary);font-weight:600">View records →</a>
        </div>
      <?php endif; ?>

      <!-- Guard: if no active semester exists, show an error and skip the attendance form entirely -->
      <?php if (!$sessionTermId): ?>
        <div class="alert alert-danger">
          <i class="fas fa-triangle-exclamation"></i> No active semester. Please ask the admin to set one.
        </div>

      <?php else: ?> <!-- Active semester exists — show the attendance form -->

        <div class="card">
          <div class="card-header">
            <!-- Show the total number of students in brackets next to the heading -->
            <h3>Student List (<?= count($studentsArr) ?>)</h3>

            <!-- "All Present" and "All Absent" bulk buttons — only shown before submission -->
            <?php if (!$alreadySubmitted): ?>
              <div style="display:flex;gap:.5rem">
                <!-- onclick calls the markAll() JavaScript function defined at the bottom -->
                <button class="btn btn-success btn-sm" onclick="markAll(1)">All Present</button>
                <button class="btn btn-danger btn-sm" onclick="markAll(0)">All Absent</button>
              </div>
            <?php endif; ?>
          </div>

          <div class="card-body">

            <!-- Empty state: shown if no students are enrolled in this section -->
            <?php if (!$studentsArr): ?>
              <p style="color:var(--gray);text-align:center;padding:1rem">No students enrolled in your section.</p>

            <?php else: ?>
            <!-- Attendance form; POST submits status values back to this same page -->
            <form method="POST" id="attForm">

              <!-- Hidden field signals to the PHP handler that this is an attendance save request -->
              <input type="hidden" name="save" value="1">

              <!-- Loop through each student to render their row -->
              <?php foreach ($studentsArr as $s): ?>

              <?php
                // Escape the admission number for safe use in HTML attributes
                $adm = htmlspecialchars($s['admissionNumber']);
                // Determine if the student is currently marked present
                // ?? 0 handles the case where status is NULL (no attendance row yet)
                $isPresent = ($s['status'] ?? 0) == 1;
              ?>

              <!-- One row per student; the ID is used by JS to target this specific row -->
              <div class="student-row" id="row-<?= $adm ?>">

                <!-- Left side: student name and admission number -->
                <div>
                  <div class="name"><?= htmlspecialchars($s['firstName'].' '.$s['lastName']) ?></div>
                  <div class="adm"><?= $adm ?></div>
                </div>

                <!-- Right side: hidden inputs + toggle button (or read-only badge if already submitted) -->
                <div style="display:flex;align-items:center;gap:.75rem">

                  <!-- Hidden input carrying this student's admission number in the form array -->
                  <input type="hidden" name="admissionNos[]" value="<?= $adm ?>">

                  <!-- Hidden input carrying this student's current status (1 or 0) -->
                  <!-- app.js updates this value when the toggle button is clicked -->
                  <!-- The ID is used by markAll() and app.js to find this input by admission number -->
                  <input type="hidden" name="statuses[]" value="<?= $isPresent ? 1 : 0 ?>" id="status-<?= $adm ?>">

                  <?php if (!$alreadySubmitted): ?>
                    <!-- Interactive toggle button: clicking it switches between Present/Absent -->
                    <!-- data-adm stores the admission number so app.js knows which input to update -->
                    <!-- CSS class ('present' or 'absent') controls the button's color -->
                    <button type="button"
                      class="toggle-btn <?= $isPresent ? 'present' : 'absent' ?>"
                      data-adm="<?= $adm ?>">
                      <?= $isPresent ? 'Present' : 'Absent' ?>
                    </button>

                  <?php else: ?>
                    <!-- Read-only badge shown after attendance has been submitted (no toggling allowed) -->
                    <span class="badge <?= $isPresent ? 'badge-present' : 'badge-absent' ?>">
                      <?= $isPresent ? 'Present' : 'Absent' ?>
                    </span>
                  <?php endif; ?>

                </div>
              </div>
              <?php endforeach; ?> <!-- End of student loop -->

              <!-- Submit button: only shown before attendance is submitted -->
              <?php if (!$alreadySubmitted): ?>
              <div style="margin-top:1.5rem">
                <!-- Centered submit button; btn-block + max-width makes it look neat -->
                <button type="submit" class="btn btn-primary btn-block" style="max-width:300px;margin:0 auto;display:flex">
                  <i class="fas fa-floppy-disk"></i> Submit Attendance
                </button>
              </div>
              <?php endif; ?>

            </form>
            <?php endif; ?> <!-- End of student list / empty state conditional -->

          </div>
        </div>
      <?php endif; ?> <!-- End of active semester conditional -->

    </div> <!-- end .page-content -->
  </div> <!-- end .main-content -->
</div> <!-- end .app-wrapper -->

<script>
// markAll(val): bulk-marks every student on the page as Present (val=1) or Absent (val=0)
// Called by the "All Present" and "All Absent" buttons in the card header
function markAll(val) {
  // Select every toggle button on the page
  document.querySelectorAll('.toggle-btn').forEach(btn => {
    const adm   = btn.dataset.adm; // Get the student's admission number from the button's data attribute
    const input = document.getElementById('status-' + adm); // Find the corresponding hidden status input

    if (val === 1) {
      // Mark as Present: swap CSS class, update button label, set hidden input to "1"
      btn.classList.remove('absent'); btn.classList.add('present');
      btn.textContent = 'Present'; input.value = '1';
    } else {
      // Mark as Absent: swap CSS class, update button label, set hidden input to "0"
      btn.classList.remove('present'); btn.classList.add('absent');
      btn.textContent = 'Absent'; input.value = '0';
    }
  });
}
</script>

<!-- app.js handles the per-student individual toggle button click behavior -->
<script src="../assets/js/app.js"></script>
</body>
</html>