<?php
include '../includes/dbcon.php';        // Loads the database connection file
include '../includes/session_teacher.php'; // Loads session validation to ensure only teachers can access this page
$pageTitle = 'View Attendance';         // Sets the page title used in the topbar

$classId    = (int)$_SESSION['classId'];    // Gets the teacher's assigned class ID from session, cast to integer for safety
$classArmId = (int)$_SESSION['classArmId']; // Gets the teacher's assigned class arm (section) ID from session
$filterDate = $_GET['date'] ?? '';          // Gets the date filter from the URL query string; defaults to empty string if not set

// Base SQL query: fetches student name, admission number, attendance status, and date
// Joins tblattendance with tblstudents using the admission number
$sql = "
  SELECT s.firstName, s.lastName, s.admissionNumber, a.status, a.dateTimeTaken
  FROM tblattendance a
  JOIN tblstudents s ON s.admissionNumber = a.admissionNo
  WHERE a.classId = ? AND a.classArmId = ?  -- Filters records to only this teacher's class and section
";
$params = [$classId, $classArmId]; // Array of parameters to bind to the query
$types  = 'ii';                    // Both classId and classArmId are integers ('i' = integer)

if ($filterDate) {                        // If a date filter was provided in the URL
    $sql     .= " AND a.dateTimeTaken = ?"; // Append a date condition to the SQL
    $params[] = $filterDate;               // Add the date value to the params array
    $types   .= 's';                       // Add 's' for string since date is a string type
}

$sql .= " ORDER BY a.dateTimeTaken DESC, s.lastName"; // Sort by most recent date first, then alphabetically by last name

$stmt = $conn->prepare($sql);              // Prepares the SQL statement to prevent SQL injection
$stmt->bind_param($types, ...$params);     // Binds the parameters using the type string and spreads the params array
$stmt->execute();                          // Executes the prepared statement
$records    = $stmt->get_result();         // Gets the result set from the executed statement
$recordsArr = $records->fetch_all(MYSQLI_ASSOC); // Fetches all rows as an associative array

// Fetch the class name and arm name for use in export headers
$secStmt = $conn->prepare("SELECT c.className, ca.classArmName FROM tblclass c JOIN tblclassarms ca ON ca.classId=c.Id WHERE ca.Id=?");
$secStmt->bind_param("i", $classArmId); // Bind the class arm ID as an integer
$secStmt->execute();                    // Execute the query
$section = $secStmt->get_result()->fetch_assoc(); // Fetch the single result row as an associative array
$sectionLabel = $section ? $section['className'].' – '.$section['classArmName'] : ''; // Build a readable label like "JSS 1 – Gold Arm", or empty string if not found
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Attendance – Faculty</title>  <!-- Browser tab title -->
  <link rel="stylesheet" href="../assets/css/style.css"> <!-- Main app stylesheet -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> <!-- Font Awesome icons -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>           <!-- SheetJS library for Excel export -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>           <!-- jsPDF library for PDF generation -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script> <!-- jsPDF plugin for table rendering in PDFs -->
</head>
<body>
<div class="app-wrapper"> <!-- Outer wrapper for the whole app layout -->
  <?php include 'sidebar.php'; ?> <!-- Includes the sidebar navigation -->
  <div class="main-content">     <!-- Main content area to the right of the sidebar -->
    <?php include 'topbar.php'; ?> <!-- Includes the top navigation bar -->
    <div class="page-content">    <!-- Inner content padding/wrapper -->

      <div class="page-header">  <!-- Header row with title and action button -->
        <h2><i class="fas fa-calendar-check"></i> Attendance Records</h2> <!-- Page heading with calendar icon -->
        <a href="take-attendance.php" class="btn btn-primary"><i class="fas fa-plus"></i> Take Today's</a> <!-- Button linking to the take-attendance page -->
      </div>

      <!-- Filter -->
      <div class="card" style="margin-bottom:1.5rem"> <!-- Card container for the filter form -->
        <div class="card-body">
          <form method="GET" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap"> <!-- GET form so filter date appears in URL -->
            <div class="form-group" style="flex:1;min-width:200px;margin:0">
              <label>Filter by Date</label>
              <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>"> <!-- Date input pre-filled with current filter value; htmlspecialchars prevents XSS -->
            </div>
            <div style="display:flex;gap:.5rem">
              <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button> <!-- Submits the filter form -->
              <a href="view-attendance.php" class="btn btn-warning"><i class="fas fa-rotate-left"></i></a> <!-- Resets the filter by reloading the page without query params -->
            </div>
          </form>
        </div>
      </div>

      <!-- Results -->
      <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:.75rem">
          <h3>Records <span style="font-size:.8rem;font-weight:400;color:var(--gray)">(<?= count($recordsArr) ?> records)</span></h3> <!-- Shows total number of records found -->
          <?php if ($recordsArr): ?> <!-- Only show export buttons if there are records -->
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button onclick="exportExcel()" class="btn btn-success btn-sm">
              <i class="fas fa-file-excel"></i> Export Excel <!-- Triggers the exportExcel() JS function -->
            </button>
            <button onclick="exportPDF()" class="btn btn-danger btn-sm">
              <i class="fas fa-file-pdf"></i> Export PDF <!-- Triggers the exportPDF() JS function -->
            </button>
          </div>
          <?php endif; ?>
        </div>
        <div class="card-body" style="padding:0">
          <div class="table-wrap"> <!-- Scrollable wrapper in case table overflows -->
            <table id="attTable"> <!-- Table with ID used by JS export functions -->
              <thead>
                <tr><th>#</th><th>Student</th><th>Student No.</th><th>Status</th><th>Date</th></tr> <!-- Column headers -->
              </thead>
              <tbody>
                <?php if (!$recordsArr): ?> <!-- If no records found, show a message -->
                <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--gray)">No records found.</td></tr>
                <?php endif; ?>
                <?php foreach ($recordsArr as $i => $r): ?> <!-- Loop through each attendance record -->
                <tr>
                  <td><?= $i+1 ?></td> <!-- Row number starting from 1 -->
                  <td><?= htmlspecialchars($r['firstName'].' '.$r['lastName']) ?></td> <!-- Student full name, sanitized -->
                  <td><?= htmlspecialchars($r['admissionNumber']) ?></td>              <!-- Student admission number, sanitized -->
                  <td data-status="<?= $r['status'] ?>"> <!-- Stores raw status value (1 or 0) as a data attribute for JS to read -->
                    <?php if ($r['status']): ?> <!-- If status is truthy (1 = Present) -->
                      <span class="badge badge-present">Present</span>
                    <?php else: ?>              <!-- If status is falsy (0 = Absent) -->
                      <span class="badge badge-absent">Absent</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars(date('F d, Y', strtotime($r['dateTimeTaken']))) ?></td> <!-- Formats the date from DB (e.g. "May 28, 2026"), sanitized -->
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
const sectionLabel = <?= json_encode($sectionLabel) ?>; // Passes the PHP section label string safely into JavaScript

function exportExcel() {
  const rows = [
    ['Student Attendance System – Faculty Attendance Records'], // Title row
    ['Section: ' + sectionLabel],                          // Section label row
    ['Generated: <?= date('F d, Y h:i A') ?>'],            // Current date/time row from PHP
    [],                                                    // Empty row as spacer
    ['#','Student','Student No.','Status','Date']          // Column header row
  ];
  document.querySelectorAll('#attTable tbody tr').forEach((tr, i) => { // Loop through each table row
    const tds = tr.querySelectorAll('td');                 // Get all cells in the row
    if (tds.length < 5) return;                            // Skip rows that don't have enough columns (e.g. "No records" row)
    rows.push([
      i + 1,                                               // Row number
      tds[1].textContent.trim(),                           // Student full name
      tds[2].textContent.trim(),                           // Admission number
      tds[3].dataset.status === '1' ? 'Present' : 'Absent', // Reads the data-status attribute to get text value
      tds[4].textContent.trim()                            // Date
    ]);
  });
  const wb = XLSX.utils.book_new();                        // Creates a new Excel workbook
  const ws = XLSX.utils.aoa_to_sheet(rows);               // Converts the rows array into a worksheet
  ws['!cols'] = [4,28,14,10,20].map(w => ({ wch: w }));   // Sets column widths in characters
  XLSX.utils.book_append_sheet(wb, ws, 'Attendance');      // Adds the worksheet to the workbook named 'Attendance'
  XLSX.writeFile(wb, 'attendance_<?= date('Y-m-d') ?>.xlsx'); // Triggers download with a date-stamped filename
}

function exportPDF() {
  const { jsPDF } = window.jspdf;                          // Destructures jsPDF class from the global jspdf object
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' }); // Creates a new A4 portrait PDF document

  doc.setFontSize(14);                                     // Sets font size for the main title
  doc.setTextColor(40);                                    // Sets text color to dark gray
  doc.text('Student Attendance System', 14, 14);               // Draws the main title at position x=14, y=14 mm
  doc.setFontSize(10);
  doc.setTextColor(80);                                    // Slightly lighter gray
  doc.text('Faculty Attendance Records', 14, 20);          // Subtitle at y=20 mm
  doc.setFontSize(8.5);
  doc.setTextColor(120);                                   // Even lighter gray for metadata
  doc.text('Section: ' + sectionLabel, 14, 26);            // Section label at y=26 mm
  doc.text('Generated: <?= date('F d, Y h:i A') ?>', 14, 31); // Generation timestamp at y=31 mm

  const head = [['#','Student','Student No.','Status','Date']]; // Table header row for the PDF
  const body = [];                                         // Array to hold data rows
  document.querySelectorAll('#attTable tbody tr').forEach((tr, i) => { // Loop through each table row
    const tds = tr.querySelectorAll('td');
    if (tds.length < 5) return;                            // Skip the "No records" row
    body.push([
      i + 1,
      tds[1].textContent.trim(),
      tds[2].textContent.trim(),
      tds[3].dataset.status === '1' ? 'Present' : 'Absent', // Converts data-status to readable text
      tds[4].textContent.trim()
    ]);
  });

  doc.autoTable({
    head, body,                                            // Pass the header and data rows
    startY: 36,                                            // Table starts 36 mm from the top (below the header text)
    styles: { fontSize: 8.5, cellPadding: 3 },            // Default cell styles
    headStyles: { fillColor: [67,97,238], textColor: 255, fontStyle: 'bold' }, // Blue header row with white bold text
    alternateRowStyles: { fillColor: [248,249,255] },      // Light blue tint on alternating rows
    columnStyles: { 3: { fontStyle: 'bold' } }             // Makes the "Status" column (index 3) bold
  });

  doc.save('attendance_<?= date('Y-m-d') ?>.pdf'); // Triggers download with a date-stamped filename
}
</script>
<script src="../assets/js/app.js"></script> <!-- Loads the main app JavaScript (sidebar toggle, etc.) -->
</body>
</html>