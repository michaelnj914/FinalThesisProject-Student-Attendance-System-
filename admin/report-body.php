<?php
// ── Shared report body (partial/include file) ──
// This file is NOT a standalone page. It is included by:
//   - admin/student-report.php
//   - teacher/student-report.php
// The parent file must define these variables before including this file:
//   $student   – associative array with the student's database row
//   $rate      – overall attendance rate as a float (e.g. 87.5)
//   $rateColor – CSS color variable string matching the standing level
//   $standing  – human-readable standing label ("Good Standing", "At Risk", "Dropped")
//   $total     – total number of attendance days recorded
//   $present   – number of days the student was present
//   $absent    – number of days the student was absent
//   $monthly   – array of monthly breakdown rows (from the DB query)
//   $logs      – array of every individual attendance log entry

// Determine the CSS class for the standing badge color using nested ternary:
//   >= 80% → 'standing-good' (green)
//   >= 60% → 'standing-risk' (yellow)
//   < 60%  → 'standing-drop' (red)
$standingClass = $rate >= 80 ? 'standing-good' : ($rate >= 60 ? 'standing-risk' : 'standing-drop');

// Determine the Font Awesome icon name for the standing badge using the same thresholds:
//   >= 80% → checkmark circle
//   >= 60% → exclamation circle
//   < 60%  → X circle
$standingIcon  = $rate >= 80 ? 'circle-check'  : ($rate >= 60 ? 'circle-exclamation' : 'circle-xmark');
?>

<!-- ── Hero Card: full-width gradient banner at the top of the report ── -->
<div class="report-hero">

  <!-- Avatar circle: shows the UPPERCASED first letter of the student's first name -->
  <!-- substr(..., 0, 1) gets the first character; strtoupper() capitalises it -->
  <div class="report-avatar"><?= strtoupper(substr($student['firstName'],0,1)) ?></div>

  <!-- Info block: student name and key details -->
  <div class="report-hero-info">

    <!-- Full name formatted as "Last, First Other" -->
    <!-- trim() removes any extra whitespace; ?? '' provides empty string if otherName is null -->
    <!-- htmlspecialchars() prevents XSS by escaping < > & " characters -->
    <h2><?= htmlspecialchars($student['lastName'].', '.$student['firstName'].' '.trim($student['otherName']??'')) ?></h2>

    <!-- Student number row with an ID-card icon -->
    <p><i class="fas fa-id-card"></i> <strong>Student No.:</strong> <?= htmlspecialchars($student['admissionNumber']) ?></p>

    <!-- Program (class) name row with a book icon -->
    <p><i class="fas fa-book-open"></i> <strong>Program:</strong> <?= htmlspecialchars($student['className']) ?></p>

    <!-- Section (class arm) name row with a layers icon -->
    <p><i class="fas fa-layer-group"></i> <strong>Section:</strong> <?= htmlspecialchars($student['classArmName']) ?></p>

    <!-- Standing badge: pill-shaped label whose color class and icon are set dynamically above -->
    <span class="standing-badge <?= $standingClass ?>">
      <i class="fas fa-<?= $standingIcon ?>"></i> <?= $standing ?>
    </span>
  </div>

  <!-- Circular attendance rate display pinned to the right side of the hero -->
  <div class="rate-circle">
    <span class="rate-num"><?= $rate ?>%</span>  <!-- e.g. "87.5%" — the big number -->
    <span class="rate-lbl">Attendance</span>     <!-- Static label underneath the number -->
  </div>
</div>

<!-- ── Four Summary Stat Boxes ── -->
<div class="summary-row">

  <!-- Box 1: Total number of school days recorded (shown in the primary/blue color) -->
  <div class="summary-box">
    <div class="s-num" style="color:var(--primary)"><?= $total ?></div>
    <div class="s-lbl">Total School Days</div>
  </div>

  <!-- Box 2: Days the student was marked present (shown in green) -->
  <div class="summary-box">
    <div class="s-num" style="color:var(--success)"><?= $present ?></div>
    <div class="s-lbl">Days Present</div>
  </div>

  <!-- Box 3: Days the student was absent (shown in red) -->
  <div class="summary-box">
    <div class="s-num" style="color:var(--danger)"><?= $absent ?></div>
    <div class="s-lbl">Days Absent</div>
  </div>

  <!-- Box 4: Overall attendance rate % (color is set dynamically by $rateColor) -->
  <div class="summary-box">
    <div class="s-num" style="color:<?= $rateColor ?>"><?= $rate ?>%</div>
    <div class="s-lbl">Attendance Rate</div>
  </div>
</div>

<!-- ── Monthly Breakdown: one horizontal progress bar per calendar month ── -->
<?php if ($monthly): ?> <!-- Only render the card if the $monthly array is not empty -->
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Monthly Breakdown</h3></div>
  <div class="card-body">

    <?php foreach ($monthly as $m): // Loop through each month's aggregated data row

      // Calculate this month's attendance rate; guard against division by zero
      $mRate  = $m['total'] > 0 ? round(($m['present']/$m['total'])*100,1) : 0;

      // Choose the bar color using the same thresholds as overall standing
      $mColor = $mRate>=80?'var(--success)':($mRate>=60?'var(--warning)':'var(--danger)');
    ?>

    <!-- Wrapper div for one month's label row + progress bar -->
    <div class="month-bar-wrap">

      <!-- Top label row: month name on the left, counts and rate on the right -->
      <div class="month-label">
        <span><strong><?= htmlspecialchars($m['label']) ?></strong></span> <!-- e.g. "January 2025" -->
        <span style="color:<?= $mColor ?>;font-weight:700">
          <?= $m['present'] ?> present / <?= $m['absent'] ?> absent &nbsp;|&nbsp; <?= $mRate ?>%
        </span>
      </div>

      <!-- Grey track that the coloured bar sits inside -->
      <div class="bar-track">
        <!-- Coloured fill: inline width equals the monthly rate %; background colour set by $mColor -->
        <div class="bar-fill" style="width:<?= $mRate ?>%;background:<?= $mColor ?>"></div>
      </div>
    </div>
    <?php endforeach; ?> <!-- End of monthly loop -->

  </div>
</div>

<?php else: ?>
<!-- Empty state: shown when $monthly is empty (no attendance data for the selected period) -->
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-body" style="text-align:center;padding:2rem;color:var(--gray)">
    <!-- Faded bar-chart icon as a visual cue for the empty state -->
    <i class="fas fa-chart-bar" style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
    No attendance data for the selected period.
  </div>
</div>
<?php endif; ?> <!-- End of monthly breakdown conditional -->

<!-- ── Full Attendance Log: table of every individual attendance record ── -->
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-list-check"></i> Full Attendance Log</h3>
    <!-- count($logs) shows the total number of records next to the heading -->
    <span style="font-size:.8rem;color:var(--gray)"><?= count($logs) ?> records</span>
  </div>

  <!-- padding:0 lets the table stretch edge-to-edge inside the card -->
  <div class="card-body" style="padding:0">
    <div class="table-wrap"> <!-- Enables horizontal scrolling on small/mobile screens -->
      <table>
        <thead>
          <tr>
            <th>#</th>        <!-- Row counter -->
            <th>Date</th>     <!-- Formatted attendance date -->
            <th>Semester</th> <!-- Session name + term name -->
            <th>Status</th>   <!-- Present or Absent badge -->
          </tr>
        </thead>
        <tbody>

          <?php if (!$logs): ?> <!-- If $logs is empty, show a single "no records" row -->
          <tr>
            <td colspan="4" style="text-align:center;padding:2rem;color:var(--gray)">
              No attendance records found.
            </td>
          </tr>
          <?php endif; ?>

          <?php foreach ($logs as $i => $log): ?> <!-- Loop through every log entry -->
          <tr>
            <!-- $i is zero-based, so +1 gives a human-readable row number starting at 1 -->
            <td><?= $i+1 ?></td>

            <!-- Format the stored datetime string as "Month DD, YYYY" (e.g. "March 05, 2025") -->
            <!-- strtotime() converts the date string to a Unix timestamp for date() to format -->
            <td><?= htmlspecialchars(date('F d, Y', strtotime($log['dateTimeTaken']))) ?></td>

            <!-- Combine the session name and term name with an em dash separator -->
            <td><?= htmlspecialchars($log['sessionName'].' – '.$log['termName']) ?></td>

            <td>
              <!-- status = 1 → student was present that day (green badge) -->
              <!-- status = 0 → student was absent that day (red badge) -->
              <?php if ($log['status']): ?>
                <span class="badge badge-present"><i class="fas fa-check"></i> Present</span>
              <?php else: ?>
                <span class="badge badge-absent"><i class="fas fa-xmark"></i> Absent</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?> <!-- End of log loop -->

        </tbody>
      </table>
    </div>
  </div>
</div>