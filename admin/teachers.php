<?php
include '../includes/dbcon.php';        // Load the database connection, makes $conn available
include '../includes/session_admin.php'; // Verify the user is logged in as admin, redirect if not

$pageTitle = 'Manage Faculty';  // Sets the page title shown in the topbar
$msg = ''; $msgType = '';       // Initialize empty feedback message and its type (success/danger)

// ── DELETE ──
// Check if a delete request came through the URL (e.g. ?delete=5) and that the value is numeric
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int)$_GET['delete'];  // Cast the ID to integer for safety
    $stmt = $conn->prepare("DELETE FROM tblclassteacher WHERE Id = ?"); // Prepare parameterized delete query
    $stmt->bind_param("i", $id); // Bind the ID as an integer
    $stmt->execute();            // Run the delete query
    $msg = 'Faculty deleted.'; $msgType = 'success'; // Set success feedback message
}

// ── ADD ──
// Check if the form was submitted with action='add'
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $fn=$trim=$_POST['firstName']??''; $fn=trim($fn); // Get first name from POST, trim whitespace (note: $trim is a typo/leftover here)
    $ln  = trim($_POST['lastName']    ?? '');  // Get and trim last name
    $email = trim($_POST['email']     ?? '');  // Get and trim email
    $phone = trim($_POST['phone']     ?? '');  // Get and trim phone number
    $pass  = $_POST['password']       ?? '';   // Get password (not trimmed to preserve spaces)
    $cid   = (int)($_POST['classId']  ?? 0);   // Get program ID, cast to int
    $aid   = (int)($_POST['classArmId']??0);   // Get section ID, cast to int
    $dt    = date('Y-m-d');                    // Get today's date as the creation date

    // Validate that all required fields are filled
    if ($fn && $ln && $email && $pass && $cid && $aid) {
        $hash = password_hash($pass, PASSWORD_BCRYPT); // Hash the password using bcrypt before storing
        
        // Prepare INSERT query with all faculty fields
        $stmt = $conn->prepare("INSERT INTO tblclassteacher (firstName,lastName,emailAddress,password,phoneNo,classId,classArmId,dateCreated) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssiis", $fn,$ln,$email,$hash,$phone,$cid,$aid,$dt); // Bind all values: s=string, i=integer
        // If execute succeeds set success message, otherwise set error message (email unique constraint)
        $stmt->execute() ? ($msg='Faculty added successfully.') && ($msgType='success')
                         : ($msg='Error: email may already exist.') && ($msgType='danger');
    } else {
        $msg='All required fields must be filled.'; $msgType='danger'; // Validation failed
    }
}

// ── EDIT ──
// Check if the form was submitted with action='edit'
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id    = (int)($_POST['editId']    ?? 0);  // Get the faculty ID being edited, cast to int
    $fn    = trim($_POST['firstName']  ?? ''); // Get and trim first name
    $ln    = trim($_POST['lastName']   ?? ''); // Get and trim last name
    $email = trim($_POST['email']      ?? ''); // Get and trim email
    $phone = trim($_POST['phone']      ?? ''); // Get and trim phone
    $cid   = (int)($_POST['classId']   ?? 0);  // Get program ID, cast to int
    $aid   = (int)($_POST['classArmId']?? 0);  // Get section ID, cast to int
    $pass  = $_POST['password']        ?? '';  // Get new password (may be empty if not changing)

    // Only proceed if required fields are present
    if ($id && $fn && $ln && $email && $cid && $aid) {
        if ($pass !== '') {
            // If a new password was provided, hash it and include it in the UPDATE
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE tblclassteacher SET firstName=?,lastName=?,emailAddress=?,password=?,phoneNo=?,classId=?,classArmId=? WHERE Id=?");
            $stmt->bind_param("sssssiii", $fn,$ln,$email,$hash,$phone,$cid,$aid,$id); // 5 strings, 3 integers
        } else {
            // If no new password provided, update everything except the password
            $stmt = $conn->prepare("UPDATE tblclassteacher SET firstName=?,lastName=?,emailAddress=?,phoneNo=?,classId=?,classArmId=? WHERE Id=?");
            $stmt->bind_param("ssssiii", $fn,$ln,$email,$phone,$cid,$aid,$id); // 4 strings, 3 integers
        }
        // If execute succeeds set success message, otherwise set error message
        $stmt->execute() ? ($msg='Faculty updated successfully.') && ($msgType='success')
                         : ($msg='Error updating.') && ($msgType='danger');
    }
}

// Fetch all programs (classes) for the dropdown, ordered alphabetically
$classes  = $conn->query("SELECT * FROM tblclass ORDER BY className");

// Fetch all sections with their parent program name, ordered by program then section name
$arms     = $conn->query("SELECT ca.*, c.className FROM tblclassarms ca JOIN tblclass c ON c.Id=ca.classId ORDER BY c.className,ca.classArmName");

// Convert sections result set into a plain PHP array (needed for json_encode later in JS)
$armsArr  = []; while ($r=$arms->fetch_assoc()) $armsArr[] = $r;

// Fetch all faculty with their assigned program and section names via JOIN
$teachers = $conn->query("SELECT t.*, c.className, ca.classArmName FROM tblclassteacher t JOIN tblclass c ON c.Id=t.classId JOIN tblclassarms ca ON ca.Id=t.classArmId ORDER BY t.lastName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Faculty – Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">   <!-- Main app stylesheet -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> <!-- Font Awesome icons -->
</head>
<body>
<div class="app-wrapper"> <!-- Outer layout wrapper -->
  <?php include 'sidebar.php'; ?> <!-- Admin sidebar navigation -->
  <div class="main-content">     <!-- Main content area -->
    <?php include 'topbar.php'; ?> <!-- Top navigation bar -->
    <div class="page-content">    <!-- Inner content padding wrapper -->

      <!-- Show feedback alert only if $msg is not empty -->
      <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss>
          <!-- Show check-circle icon for success, circle-exclamation for errors -->
          <i class="fas fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i>
          <?= htmlspecialchars($msg) ?> <!-- Display the message, sanitized against XSS -->
        </div>
      <?php endif; ?>

      <div class="page-header"> <!-- Header row with title and add button -->
        <h2><i class="fas fa-chalkboard-user"></i> Faculty</h2> <!-- Page heading with icon -->
        <button class="btn btn-primary" onclick="openModal('addModal')"> <!-- Opens the Add Faculty modal -->
          <i class="fas fa-plus"></i> Add Faculty
        </button>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0">
          <div class="table-wrap"> <!-- Scrollable wrapper for the table -->
            <table>
              <thead>
                <tr><th>#</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Assigned Section</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php $i=1; while ($t=$teachers->fetch_assoc()): ?> <!-- Loop through each faculty row -->
                <tr>
                  <td><?= $i++ ?></td> <!-- Row number, increments each iteration -->
                  <td><?= htmlspecialchars($t['lastName'].', '.$t['firstName']) ?></td> <!-- Full name in Last, First format -->
                  <td><?= htmlspecialchars($t['emailAddress']) ?></td> <!-- Email address, sanitized -->
                  <td><?= htmlspecialchars($t['phoneNo']) ?></td>      <!-- Phone number, sanitized -->
                  <td><?= htmlspecialchars($t['classArmName']) ?></td> <!-- Assigned section name, sanitized -->
                  <td>
                    <!-- Edit button: passes the full row data as JSON to fillEdit() JS function -->
                    <button class="btn btn-warning btn-sm" onclick='fillEdit(<?= json_encode($t) ?>)'>
                      <i class="fas fa-pen"></i>
                    </button>
                    <!-- Delete link: passes the faculty ID in the URL, confirms before deleting -->
                    <a href="?delete=<?= $t['Id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this faculty member?')">
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

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal"> <!-- Modal backdrop overlay -->
  <div class="modal">
    <div class="modal-header">
      <h4><i class="fas fa-user-plus"></i> Add Faculty</h4>
      <button class="modal-close" onclick="closeModal('addModal')">×</button> <!-- Close button -->
    </div>
    <div class="modal-body">
      <form method="POST"> <!-- Submits via POST to the same page -->
        <input type="hidden" name="action" value="add"> <!-- Hidden field to identify this as an add action -->
        <div class="form-group"><label>First Name *</label><input type="text" name="firstName" class="form-control" required></div>
        <div class="form-group"><label>Last Name *</label><input type="text" name="lastName" class="form-control" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required placeholder="faculty@ama.edu.ph"></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" placeholder="09XXXXXXXXX"></div>
        <div class="form-group"><label>Password *</label><input type="password" name="password" class="form-control" required></div>
        <div class="form-group">
          <label>Program *</label>
          <!-- When program changes, call filterArms() to populate the section dropdown -->
          <select name="classId" class="form-control" required id="addClassId" onchange="filterArms(this,'addArmId')">
            <option value="">— Select Program —</option>
            <?php $classes->data_seek(0); while ($c=$classes->fetch_assoc()): ?> <!-- Reset pointer and loop through programs -->
            <option value="<?= $c['Id'] ?>"><?= htmlspecialchars($c['className']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Section *</label>
          <!-- Sections are populated dynamically by JS based on selected program -->
          <select name="classArmId" class="form-control" required id="addArmId">
            <option value="">— Select Program first —</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Save Faculty</button>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal"> <!-- Modal backdrop overlay for editing -->
  <div class="modal">
    <div class="modal-header">
      <h4><i class="fas fa-pen"></i> Edit Faculty</h4>
      <button class="modal-close" onclick="closeModal('editModal')">×</button> <!-- Close button -->
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="edit">   <!-- Identifies this as an edit action -->
        <input type="hidden" name="editId" id="editId">    <!-- Holds the ID of the faculty being edited, filled by JS -->
        <div class="form-group"><label>First Name *</label><input type="text" name="firstName" id="editFN" class="form-control" required></div>
        <div class="form-group"><label>Last Name *</label><input type="text" name="lastName" id="editLN" class="form-control" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" id="editEmail" class="form-control" required></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="editPhone" class="form-control"></div>
        <!-- Password is optional on edit — leaving blank keeps the existing password -->
        <div class="form-group"><label>New Password <small style="color:var(--gray)">(leave blank to keep)</small></label><input type="password" name="password" class="form-control"></div>
        <div class="form-group">
          <label>Program *</label>
          <!-- When program changes, call filterArms() to repopulate the section dropdown -->
          <select name="classId" class="form-control" required id="editClassId" onchange="filterArms(this,'editArmId')">
            <option value="">— Select Program —</option>
            <?php $classes->data_seek(0); while ($c=$classes->fetch_assoc()): ?> <!-- Reset pointer and loop again -->
            <option value="<?= $c['Id'] ?>"><?= htmlspecialchars($c['className']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Section *</label>
          <select name="classArmId" class="form-control" required id="editArmId"></select> <!-- Populated by JS -->
        </div>
        <button type="submit" class="btn btn-primary btn-block">Update Faculty</button>
      </form>
    </div>
  </div>
</div>

<script>
// Pass all sections data from PHP into a JavaScript array for use in filtering
const armsData = <?= json_encode($armsArr) ?>;

// Filters the section dropdown based on the selected program
function filterArms(sel, armId) {
  const cid = sel.value; // Get the selected program ID
  const arm = document.getElementById(armId); // Get the section dropdown element
  arm.innerHTML = '<option value="">— Select Section —</option>'; // Reset the section dropdown
  // Filter sections that belong to the selected program and add them as options
  armsData.filter(a=>String(a.classId)===String(cid)).forEach(a=>{
    arm.innerHTML += `<option value="${a.Id}">${a.classArmName}</option>`;
  });
}

// Fills the Edit modal form with the selected faculty's existing data
function fillEdit(t) {
  document.getElementById('editId').value    = t.Id;           // Set the hidden faculty ID
  document.getElementById('editFN').value    = t.firstName;    // Pre-fill first name
  document.getElementById('editLN').value    = t.lastName;     // Pre-fill last name
  document.getElementById('editEmail').value = t.emailAddress; // Pre-fill email
  document.getElementById('editPhone').value = t.phoneNo;      // Pre-fill phone
  const cs = document.getElementById('editClassId');
  cs.value = t.classId;              // Set the program dropdown to the faculty's current program
  filterArms(cs,'editArmId');        // Populate the section dropdown for that program
  // Small delay before setting the section value to allow filterArms() to finish rendering options
  setTimeout(()=>{ document.getElementById('editArmId').value = t.classArmId; }, 50);
  openModal('editModal'); // Open the edit modal
}
</script>
<script src="../assets/js/app.js"></script> <!-- Load main app JS (modal open/close, sidebar toggle) -->
</body>
</html>