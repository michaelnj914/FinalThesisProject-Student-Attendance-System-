<?php
// Read-only view of all attendance records across all sections.
// Supports filtering by date, program, and section.
// Provides Export to Excel (SheetJS) and Export to PDF (jsPDF + AutoTable) buttons.

include '../includes/dbcon.php';       // Load database connection
include '../includes/session_admin.php'; // Ensure user is logged in as admin
$pageTitle = 'View Attendance'; // Title shown in the topbar

// Read optional filter values from the URL query string
$filterDate  = $_GET['date']    ?? ''; // Date filter (YYYY-MM-DD), empty means no filter
// Validate classId: accept only if it's a string of digits, then cast to int; else 0
$filterClass = isset($_GET['classId']) && ctype_digit($_GET['classId']) ? (int)$_GET['classId'] : 0;
// Validate armId similarly
$filterArm   = isset($_GET['armId'])   && ctype_digit($_GET['armId'])   ? (int)$_GET['armId']   : 0;

// Build the WHERE clause dynamically based on which filters are active
$where=[]; $params=[]; $types='';
if ($filterDate)  { $where[]='a.dateTimeTaken=?'; $params[]=$filterDate;  $types.='s'; } // Add date condition
if ($filterClass) { $where[]='a.classId=?';        $params[]=$filterClass; $types.='i'; } // Add class condition
if ($filterArm)   { $where[]='a.classArmId=?';     $params[]=$filterArm;  $types.='i'; } // Add section condition

// Build the full SQL query, appending WHERE clause if any filters are active
$sql = "SELECT s.firstName, s.lastName, s.admissionNumber, c.className, ca.classArmName, a.status, a.dateTimeTaken
        FROM tblattendance a
        JOIN tblstudents s   ON s.admissionNumber = a.admissionNo
        JOIN tblclass c      ON c.Id = a.classId
        JOIN tblclassarms ca ON ca.Id = a.classArmId
        ".($where ? 'WHERE '.implode(' AND ',$where) : '').  // Glue all WHERE conditions with AND
        "
        ORDER BY a.dateTimeTaken DESC, s.lastName";

$stmt = $conn->prepare($sql); // Prepare the query
if ($params) $stmt->bind_param($types, ...$params); // Bind parameters only if there are any (spread operator)
$stmt->execute(); // Run the query
$records    = $stmt->get_result(); // Get the result set
$recordsArr = $records->fetch_all(MYSQLI_ASSOC); // Fetch all rows into an array (needed for count and export)

// Fetch all programs for the filter dropdown
$classes = $conn->query("SELECT * FROM tblclass ORDER BY className");
// Fetch all sections joined with their program name for the filter dropdown
$arms    = $conn->query("SELECT ca.*, c.className FROM tblclassarms ca JOIN tblclass c ON c.Id=ca.classId ORDER BY c.className,ca.classArmName");
$armsArr = []; while ($r=$arms->fetch_assoc()) $armsArr[] = $r; // Store sections in a plain array for JS use
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Attendance – Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- SheetJS: JavaScript library for generating Excel (.xlsx) files in the browser -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <!-- jsPDF: Library for generating PDF files in the browser -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <!-- jsPDF AutoTable plugin: adds autoTable() method for drawing tables in jsPDF -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
  <style>
    .export-btn-group { display:flex; gap:.5rem; flex-wrap:wrap; } /* Flex container for export buttons */
  </style>
</head>
<body>
<div class="app-wrapper">
  <?php include 'sidebar.php'; ?> <!-- Admin sidebar navigation -->
  <div class="main-content">
    <?php include 'topbar.php'; ?> <!-- Top bar with page title and user info -->
    <div class="page-content">

      <div class="page-header">
        <h2><i class="fas fa-calendar-check"></i> Attendance Records</h2>
      </div>

      <!-- Filter Form -->
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-body">
          <!-- GET form so filters appear in the URL (shareable/bookmarkable) -->
          <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="flex:1;min-width:150px;margin:0">
              <label>Date</label>
              <!-- Pre-fill date input with current filter value -->
              <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
            </div>
            <div class="form-group" style="flex:1;min-width:150px;margin:0">
              <label>Program</label>
              <!-- When program changes, call filterArms() to update the section dropdown -->
              <select name="classId" class="form-control" onchange="filterArms(this,'armFilter')">
                <option value="">All Programs</option>
                <?php while ($c=$classes->fetch_assoc()): ?>
                <!-- Mark as selected if this program matches the current filter -->
                <option value="<?= $c['Id'] ?>" <?= $filterClass==$c['Id']?'selected':'' ?>><?= htmlspecialchars($c['className']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group" style="flex:1;min-width:150px;margin:0">
              <label>Section</label>
              <select name="armId" class="form-control" id="armFilter"> <!-- id used by filterArms() JS function -->
                <option value="">All Sections</option>
                <?php foreach ($armsArr as $a): ?>
                <option value="<?= $a['Id'] ?>" <?= $filterArm==$a['Id']?'selected':'' ?>><?= htmlspecialchars($a['classArmName']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
              <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button> <!-- Apply filters -->
              <a href="attendance.php" class="btn btn-warning"><i class="fas fa-rotate-left"></i></a> <!-- Reset/clear all filters -->
            </div>
          </form>
        </div>
      </div>

      <!-- Results Table -->
      <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:.75rem">
          <!-- Show total number of matching records -->
          <h3>Results <span style="font-size:.8rem;font-weight:400;color:var(--gray)">(<?= count($recordsArr) ?> records)</span></h3>
          <!-- Only show export buttons if there are records to export -->
          <?php if ($recordsArr): ?>
          <div class="export-btn-group">
            <button onclick="exportExcel()" class="btn btn-success btn-sm">
              <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button onclick="exportPDF()" class="btn btn-danger btn-sm">
              <i class="fas fa-file-pdf"></i> Export PDF
            </button>
          </div>
          <?php endif; ?>
        </div>
        <div class="card-body" style="padding:0">
          <div class="table-wrap">
            <table id="attTable"> <!-- id used by export functions to read table rows -->
              <thead>
                <tr><th>#</th><th>Student</th><th>Student No.</th><th>Program</th><th>Section</th><th>Status</th><th>Date</th></tr>
              </thead>
              <tbody>
                <!-- Show "no records" message if array is empty -->
                <?php if (!$recordsArr): ?>
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--gray)">No records found.</td></tr>
                <?php endif; ?>
                <!-- Loop through each attendance record -->
                <?php foreach ($recordsArr as $i => $r): ?>
                <tr>
                  <td><?= $i+1 ?></td> <!-- Row number (1-based) -->
                  <td><?= htmlspecialchars($r['lastName'].', '.$r['firstName']) ?></td> <!-- Last, First format -->
                  <td><?= htmlspecialchars($r['admissionNumber']) ?></td>
                  <td><?= htmlspecialchars($r['className']) ?></td>
                  <td><?= htmlspecialchars($r['classArmName']) ?></td>
                  <!-- data-status stores the raw 0/1 value so JS export functions can read it cleanly -->
                  <td data-status="<?= $r['status'] ?>">
                    <?php if ($r['status']): ?>
                      <span class="badge badge-present">Present</span>
                    <?php else: ?>
                      <span class="badge badge-absent">Absent</span>
                    <?php endif; ?>
                  </td>
                  <!-- Format the date as "Month DD, YYYY" for readability -->
                  <td><?= htmlspecialchars(date('F d, Y', strtotime($r['dateTimeTaken']))) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// armsData: PHP array of all sections passed to JavaScript as JSON for use in the dropdown filter
const armsData = <?= json_encode($armsArr) ?>;

// filterArms: updates the Section dropdown to only show sections belonging to the selected program
function filterArms(sel, armId) {
  const cid = sel.value; // The selected program ID
  const arm = document.getElementById(armId); // The Section <select> element
  const cur = arm.value; // Remember the currently selected section (to re-select if possible)
  arm.innerHTML = '<option value="">All Sections</option>'; // Reset dropdown to default
  // If a program is selected, filter sections by classId; otherwise show all sections
  const f = cid ? armsData.filter(a => String(a.classId) === String(cid)) : armsData;
  // Build new <option> elements for each filtered section
  f.forEach(a => { arm.innerHTML += `<option value="${a.Id}" ${cur==a.Id?'selected':''}>${a.classArmName}</option>`; });
}

// ── Excel Export ──
function exportExcel() {
  const rows = [['#','Student','Student No.','Program','Section','Status','Date']]; // Header row
  // Loop over each table row and collect cell values
  document.querySelectorAll('#attTable tbody tr').forEach((tr, i) => {
    const tds = tr.querySelectorAll('td');
    if (tds.length < 7) return; // Skip empty/header rows
    const status = tds[5].dataset.status === '1' ? 'Present' : 'Absent'; // Read raw status from data attribute
    rows.push([
      i + 1,
      tds[1].textContent.trim(),
      tds[2].textContent.trim(),
      tds[3].textContent.trim(),
      tds[4].textContent.trim(),
      status,
      tds[6].textContent.trim()
    ]);
  });
  const wb = XLSX.utils.book_new(); // Create a new workbook
  const ws = XLSX.utils.aoa_to_sheet(rows); // Convert array-of-arrays to a worksheet
  ws['!cols'] = [4,25,14,10,12,10,18].map(w => ({ wch: w })); // Set column widths
  XLSX.utils.book_append_sheet(wb, ws, 'Attendance'); // Add worksheet to workbook
  XLSX.writeFile(wb, 'attendance_<?= date('Y-m-d') ?>.xlsx'); // Trigger download with today's date in filename
}

// ── PDF Export ──
function exportPDF() {
  const { jsPDF } = window.jspdf; // Destructure jsPDF constructor from global
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' }); // Create landscape A4 PDF

  // Add document title
  doc.setFontSize(14);
  doc.setTextColor(40);
  doc.text('AMA Attendance System – Attendance Records', 14, 15);
  // Add generation timestamp subtitle
  doc.setFontSize(9);
  doc.setTextColor(120);
  doc.text('Generated: <?= date('F d, Y h:i A') ?>', 14, 21);

  const head = [['#','Student','Student No.','Program','Section','Status','Date']]; // Table header
  const body = [];
  // Collect table data from DOM
  document.querySelectorAll('#attTable tbody tr').forEach((tr, i) => {
    const tds = tr.querySelectorAll('td');
    if (tds.length < 7) return;
    body.push([
      i + 1,
      tds[1].textContent.trim(),
      tds[2].textContent.trim(),
      tds[3].textContent.trim(),
      tds[4].textContent.trim(),
      tds[5].dataset.status === '1' ? 'Present' : 'Absent',
      tds[6].textContent.trim()
    ]);
  });

  // Draw the table using AutoTable plugin
  doc.autoTable({
    head, body,
    startY: 26, // Start table below the header text
    styles: { fontSize: 8, cellPadding: 2.5 },
    headStyles: { fillColor: [67,97,238], textColor: 255, fontStyle: 'bold' }, // Blue header
    alternateRowStyles: { fillColor: [248,249,255] }, // Light blue alternating rows
    // Color the Status column text green for Present, red for Absent
    didDrawCell: data => {
      if (data.column.index === 5 && data.section === 'body') {
        const val = body[data.row.index]?.[5];
        if (val === 'Present') data.cell.styles.textColor = [6,95,70];  // Dark green
        if (val === 'Absent')  data.cell.styles.textColor = [153,27,27]; // Dark red
      }
    }
  });

  doc.save('attendance_<?= date('Y-m-d') ?>.pdf'); // Trigger PDF download
}
</script>
<script src="../assets/js/app.js"></script> <!-- Shared JS: sidebar toggle, modals, auto-dismiss alerts -->
</body>
</html>