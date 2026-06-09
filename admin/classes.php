<?php
include '../includes/dbcon.php';         // Load database connection
include '../includes/session_admin.php'; // Ensure admin access only
$pageTitle = 'Programs & Sections'; // Shown in topbar
$msg = ''; $msgType = ''; // Feedback message variables

// ── ADD PROGRAM ──
if (isset($_POST['action']) && $_POST['action'] === 'addClass') {
    $name = trim($_POST['className'] ?? ''); // Get and trim the program name
    if ($name) {
        $stmt = $conn->prepare("INSERT INTO tblclass (className) VALUES (?)");
        $stmt->bind_param("s", $name); // Bind name as string
        $stmt->execute() ? ($msg='Program added.') && ($msgType='success')
                         : ($msg='Error adding program.') && ($msgType='danger');
    }
}

// ── DELETE PROGRAM ──
if (isset($_GET['deleteClass']) && ctype_digit($_GET['deleteClass'])) {
    $id = (int)$_GET['deleteClass']; // Safe integer cast
    $stmt = $conn->prepare("DELETE FROM tblclass WHERE Id=?");
    $stmt->bind_param("i", $id); $stmt->execute();
    $msg='Program deleted.'; $msgType='success';
}

// ── ADD SECTION ──
if (isset($_POST['action']) && $_POST['action'] === 'addArm') {
    $cid  = (int)($_POST['classId']     ?? 0); // Program this section belongs to
    $name = trim($_POST['classArmName'] ?? ''); // Section name
    if ($cid && $name) {
        // Insert new section with isAssigned=0 (not yet assigned to a teacher)
        $stmt = $conn->prepare("INSERT INTO tblclassarms (classId, classArmName, isAssigned) VALUES (?,?,0)");
        $stmt->bind_param("is", $cid, $name); // i=int, s=string
        $stmt->execute() ? ($msg='Section added.') && ($msgType='success')
                         : ($msg='Error adding section.') && ($msgType='danger');
    }
}

// ── DELETE SECTION ──
if (isset($_GET['deleteArm']) && ctype_digit($_GET['deleteArm'])) {
    $id = (int)$_GET['deleteArm'];
    $stmt = $conn->prepare("DELETE FROM tblclassarms WHERE Id=?");
    $stmt->bind_param("i", $id); $stmt->execute();
    $msg='Section deleted.'; $msgType='success';
}

// Fetch all programs for display
$classes = $conn->query("SELECT * FROM tblclass ORDER BY className");
// Fetch all sections joined with their program name for display
$arms    = $conn->query("SELECT ca.*, c.className FROM tblclassarms ca JOIN tblclass c ON c.Id=ca.classId ORDER BY c.className, ca.classArmName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Programs & Sections – Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper">
  <?php include 'sidebar.php'; ?>
  <div class="main-content">
    <?php include 'topbar.php'; ?>
    <div class="page-content">

      <!-- Show feedback message if any -->
      <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss>
          <i class="fas fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i>
          <?= htmlspecialchars($msg) ?>
        </div>
      <?php endif; ?>

      <!-- Two-column layout: Programs on the left, Sections on the right -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

        <!-- ── Programs Column ── -->
        <div>
          <div class="page-header">
            <h2><i class="fas fa-book-open"></i> Programs</h2>
            <button class="btn btn-primary" onclick="openModal('addClassModal')"><i class="fas fa-plus"></i> Add</button>
          </div>
          <div class="card">
            <div class="card-body" style="padding:0">
              <table>
                <thead><tr><th>#</th><th>Program Name</th><th>Action</th></tr></thead>
                <tbody>
                  <?php $i=1; $classes->data_seek(0); while ($c=$classes->fetch_assoc()): ?> <!-- Reset pointer and loop -->
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($c['className']) ?></td>
                    <td>
                      <!-- Delete link with confirmation -->
                      <a href="?deleteClass=<?= $c['Id'] ?>" class="btn btn-danger btn-sm"
                         onclick="return confirm('Delete this program?')">
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

        <!-- ── Sections Column ── -->
        <div>
          <div class="page-header">
            <h2><i class="fas fa-layer-group"></i> Sections</h2>
            <button class="btn btn-primary" onclick="openModal('addArmModal')"><i class="fas fa-plus"></i> Add</button>
          </div>
          <div class="card">
            <div class="card-body" style="padding:0">
              <table>
                <thead><tr><th>#</th><th>Section</th><th>Program</th><th>Action</th></tr></thead>
                <tbody>
                  <?php $i=1; while ($a=$arms->fetch_assoc()): ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($a['classArmName']) ?></td>
                    <td><?= htmlspecialchars($a['className']) ?></td>
                    <td>
                      <a href="?deleteArm=<?= $a['Id'] ?>" class="btn btn-danger btn-sm"
                         onclick="return confirm('Delete this section?')">
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
</div>

<!-- Add Program Modal -->
<div class="modal-overlay" id="addClassModal">
  <div class="modal">
    <div class="modal-header">
      <h4>Add Program</h4>
      <button class="modal-close" onclick="closeModal('addClassModal')">×</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="addClass"> <!-- Signals PHP to run ADD PROGRAM block -->
        <div class="form-group"><label>Program Name *</label><input type="text" name="className" class="form-control" required placeholder="e.g. BSIT"></div>
        <button type="submit" class="btn btn-primary btn-block">Add Program</button>
      </form>
    </div>
  </div>
</div>

<!-- Add Section Modal -->
<div class="modal-overlay" id="addArmModal">
  <div class="modal">
    <div class="modal-header">
      <h4>Add Section</h4>
      <button class="modal-close" onclick="closeModal('addArmModal')">×</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="addArm"> <!-- Signals PHP to run ADD SECTION block -->
        <div class="form-group">
          <label>Program *</label>
          <select name="classId" class="form-control" required>
            <option value="">— Select Program —</option>
            <?php $classes->data_seek(0); while ($c=$classes->fetch_assoc()): ?> <!-- Reset and loop programs -->
            <option value="<?= $c['Id'] ?>"><?= htmlspecialchars($c['className']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group"><label>Section Name *</label><input type="text" name="classArmName" class="form-control" required placeholder="e.g. BSIT-1A"></div>
        <button type="submit" class="btn btn-primary btn-block">Add Section</button>
      </form>
    </div>
  </div>
</div>

<script src="../assets/js/app.js"></script> <!-- Shared modal and sidebar JS -->
</body>
</html>