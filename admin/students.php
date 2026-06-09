<?php
include '../includes/dbcon.php';         // Load database connection
include '../includes/session_admin.php'; // Ensure user is logged in as admin

$pageTitle = 'Manage Students'; // Shown in the topbar
$msg = '';       // Feedback message to display after actions
$msgType = '';   // Type of message: 'success' or 'danger' (controls badge color)

// ── DELETE ──
// Check if a delete request came through the URL (e.g. ?delete=5)
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int)$_GET['delete']; // Cast to int for safety
    $stmt = $conn->prepare("DELETE FROM tblstudents WHERE Id = ?"); // Parameterized delete
    $stmt->bind_param("i", $id); // Bind id as integer
    $stmt->execute(); // Run the delete
    $msg = 'Student deleted.'; $msgType = 'success';
}

// ── ADD ──
// Check if the form was submitted with action='add'
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $fn  = trim($_POST['firstName']      ?? ''); // First name, trimmed
    $ln  = trim($_POST['lastName']       ?? ''); // Last name, trimmed
    $on  = trim($_POST['otherName']      ?? ''); // Middle name, trimmed (optional)
    $adm = trim($_POST['admissionNumber'] ?? ''); // Student number
    $cid = (int)($_POST['classId']       ?? 0); // Program ID, cast to int
    $aid = (int)($_POST['classArmId']    ?? 0); // Section ID, cast to int
    $dt  = date('Y-m-d'); // Today's date as the enrollment date

    // Validate required fields
    if ($fn && $ln && $adm && $cid && $aid) {
        // Check if student number already exists to prevent duplicates
        $chk = $conn->prepare("SELECT Id FROM tblstudents WHERE admissionNumber = ?");
        $chk->bind_param("s", $adm);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = 'Student number already exists.'; $msgType = 'danger';
        } else {
            // First prepare (with wrong types) is overwritten by the correct one below
            $stmt = $conn->prepare("INSERT INTO tblstudents (firstName,lastName,otherName,admissionNumber,classId,classArmId,dateCreated) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssiii", $fn,$ln,$on,$adm,$cid,$aid,$dt);
            // fix: date is string — correct binding with 'ssssiis'
            $stmt = $conn->prepare("INSERT INTO tblstudents (firstName,lastName,otherName,admissionNumber,classId,classArmId,dateCreated) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssiis", $fn,$ln,$on,$adm,$cid,$aid,$dt); // s=string, i=integer
            // Execute and set feedback message based on success/failure
            $stmt->execute() ? ($msg='Student added successfully.') && ($msgType='success')
                             : ($msg='Error adding student.') && ($msgType='danger');
        }
    } else {
        $msg = 'Please fill all required fields.'; $msgType = 'danger';
    }
}

// ── EDIT ──
// Check if the form was submitted with action='edit'
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id  = (int)($_POST['editId']         ?? 0); // ID of the student to update
    $fn  = trim($_POST['firstName']       ?? '');
    $ln  = trim($_POST['lastName']        ?? '');
    $on  = trim($_POST['otherName']       ?? '');
    $adm = trim($_POST['admissionNumber'] ?? '');
    $cid = (int)($_POST['classId']        ?? 0);
    $aid = (int)($_POST['classArmId']     ?? 0);

    // Validate that required fields and ID are present
    if ($id && $fn && $ln && $adm && $cid && $aid) {
        $stmt = $conn->prepare("UPDATE tblstudents SET firstName=?,lastName=?,otherName=?,admissionNumber=?,classId=?,classArmId=? WHERE Id=?");
        $stmt->bind_param("ssssiii", $fn,$ln,$on,$adm,$cid,$aid,$id);
        $stmt->execute() ? ($msg='Student updated successfully.') && ($msgType='success')
                         : ($msg='Error updating student.') && ($msgType='danger');
    }
}

// Fetch all programs for dropdowns
$classes  = $conn->query("SELECT * FROM tblclass ORDER BY className");
// Fetch all sections joined with program names for dropdowns
$arms     = $conn->query("SELECT ca.*, c.className FROM tblclassarms ca JOIN tblclass c ON c.Id=ca.classId ORDER BY c.className,ca.classArmName");
$armsArr  = []; while ($r=$arms->fetch_assoc()) $armsArr[] = $r; // Convert to plain array for JSON/JS
// Fetch all students with their program and section names joined
$students = $conn->query("SELECT s.*, c.className, ca.classArmName FROM tblstudents s JOIN tblclass c ON c.Id=s.classId JOIN tblclassarms ca ON ca.Id=s.classArmId ORDER BY s.lastName, s.firstName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Students – Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper">
  <?php include 'sidebar.php'; ?>
  <div class="main-content">
    <?php include 'topbar.php'; ?>
    <div class="page-content">

      <!-- Show feedback alert if there's a message; auto-dismissed by JS in app.js -->
      <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss>
          <i class="fas fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i>
          <?= htmlspecialchars($msg) ?>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <h2><i class="fas fa-users"></i> Students</h2>
        <!-- Button opens the Add Student modal via JS openModal() -->
        <button class="btn btn-primary" onclick="openModal('addModal')">
          <i class="fas fa-plus"></i> Add Student
        </button>
      </div>

      <!-- Students Table -->
      <div class="card">
        <div class="card-body" style="padding:0">
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>#</th><th>Full Name</th><th>Student No.</th><th>Section</th><th>Date Enrolled</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <!-- Loop through each student row -->
                <?php $i=1; while ($s=$students->fetch_assoc()): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($s['lastName'].', '.$s['firstName'].' '.($s['otherName']??'')) ?></td>
                  <td><?= htmlspecialchars($s['admissionNumber']) ?></td>
                  <td><?= htmlspecialchars($s['classArmName']) ?></td>
                  <td><?= htmlspecialchars($s['dateCreated']) ?></td>
                  <td>
                    <!-- View attendance report for this student -->
                    <a href="student-report.php?id=<?= $s['Id'] ?>" class="btn btn-primary btn-sm" title="View Report">
                      <i class="fas fa-chart-bar"></i>
                    </a>
                    <!-- Edit: passes the full student row as JSON to fillEdit() JS function -->
                    <button class="btn btn-warning btn-sm" onclick='fillEdit(<?= json_encode($s) ?>)' title="Edit">
                      <i class="fas fa-pen"></i>
                    </button>
                    <!-- Delete: asks for confirmation before following the link -->
                    <a href="?delete=<?= $s['Id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this student?')" title="Delete">
                      <i class="fas fa-trash"></i>
                    </a>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ADD MODAL: hidden by default; opened via openModal('addModal') -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <h4><i class="fas fa-user-plus"></i> Add Student</h4>
      <button class="modal-close" onclick="closeModal('addModal')">×</button> <!-- Closes the modal -->
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add"> <!-- Signals PHP to run the ADD block -->
        <div class="form-group"><label>First Name *</label><input type="text" name="firstName" class="form-control" required></div>
        <div class="form-group"><label>Last Name *</label><input type="text" name="lastName" class="form-control" required></div>
        <div class="form-group"><label>Middle Name</label><input type="text" name="otherName" class="form-control"></div>
        <div class="form-group"><label>Student No. *</label><input type="text" name="admissionNumber" class="form-control" required placeholder="e.g. 2024-00001"></div>
        <div class="form-group">
          <label>Program *</label>
          <!-- When program changes, update the section dropdown via JS -->
          <select name="classId" class="form-control" required id="addClassId" onchange="filterArms(this,'addArmId')">
            <option value="">— Select Program —</option>
            <?php $classes->data_seek(0); while ($c=$classes->fetch_assoc()): ?> <!-- Reset pointer, loop programs -->
            <option value="<?= $c['Id'] ?>"><?= htmlspecialchars($c['className']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Section *</label>
          <!-- Initially empty; populated by filterArms() JS when program is selected -->
          <select name="classArmId" class="form-control" required id="addArmId">
            <option value="">— Select Program first —</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Save Student</button>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL: pre-filled by fillEdit() JS function -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h4><i class="fas fa-pen"></i> Edit Student</h4>
      <button class="modal-close" onclick="closeModal('editModal')">×</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="edit"> <!-- Signals PHP to run the EDIT block -->
        <input type="hidden" name="editId" id="editId"> <!-- Stores the student ID to update -->
        <div class="form-group"><label>First Name *</label><input type="text" name="firstName" id="editFN" class="form-control" required></div>
        <div class="form-group"><label>Last Name *</label><input type="text" name="lastName" id="editLN" class="form-control" required></div>
        <div class="form-group"><label>Middle Name</label><input type="text" name="otherName" id="editON" class="form-control"></div>
        <div class="form-group"><label>Student No. *</label><input type="text" name="admissionNumber" id="editAdm" class="form-control" required></div>
        <div class="form-group">
          <label>Program *</label>
          <select name="classId" class="form-control" required id="editClassId" onchange="filterArms(this,'editArmId')">
            <option value="">— Select Program —</option>
            <?php $classes->data_seek(0); while ($c=$classes->fetch_assoc()): ?>
            <option value="<?= $c['Id'] ?>"><?= htmlspecialchars($c['className']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Section *</label>
          <select name="classArmId" class="form-control" required id="editArmId"></select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Update Student</button>
      </form>
    </div>
  </div>
</div>

<script>
// Pass all sections from PHP to JS as a JSON array for dynamic dropdown filtering
const armsData = <?= json_encode($armsArr) ?>;

// filterArms: filters the section dropdown to show only sections matching the selected program
function filterArms(sel, armId) {
  const cid = sel.value; // Selected program ID
  const arm = document.getElementById(armId); // The section <select> element
  arm.innerHTML = '<option value="">— Select Section —</option>'; // Reset options
  // Filter sections by classId and build new <option> elements
  armsData.filter(a=>String(a.classId)===String(cid)).forEach(a=>{
    arm.innerHTML += `<option value="${a.Id}">${a.classArmName}</option>`;
  });
}

// fillEdit: populates the Edit modal with data from the clicked student row
function fillEdit(s) {
  document.getElementById('editId').value  = s.Id;           // Set student ID
  document.getElementById('editFN').value  = s.firstName;    // Set first name
  document.getElementById('editLN').value  = s.lastName;     // Set last name
  document.getElementById('editON').value  = s.otherName || ''; // Set middle name (fallback empty)
  document.getElementById('editAdm').value = s.admissionNumber; // Set student number
  const cs = document.getElementById('editClassId');
  cs.value = s.classId;           // Set selected program
  filterArms(cs,'editArmId');     // Populate section dropdown for that program
  // Small delay so section dropdown is populated before we set its value
  setTimeout(()=>{ document.getElementById('editArmId').value = s.classArmId; }, 50);
  openModal('editModal'); // Open the edit modal
}
</script>
<script src="../assets/js/app.js"></script> <!-- Shared JS for modal open/close, sidebar toggle, alerts -->
</body>
</html>