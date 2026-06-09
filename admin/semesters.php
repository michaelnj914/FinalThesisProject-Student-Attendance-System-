<?php
include '../includes/dbcon.php';         // Load database connection
include '../includes/session_admin.php'; // Admin-only access
$pageTitle = 'Semester Management';
$msg = ''; $msgType = '';

// ── SET ACTIVE SEMESTER ──
// When an admin clicks "Set Active" on a semester row
if (isset($_GET['setActive']) && ctype_digit($_GET['setActive'])) {
    $id = (int)$_GET['setActive'];
    $conn->query("UPDATE tblsessionterm SET isActive = 0"); // Deactivate ALL semesters first
    $stmt = $conn->prepare("UPDATE tblsessionterm SET isActive = 1 WHERE Id = ?"); // Then activate the chosen one
    $stmt->bind_param("i", $id); $stmt->execute();
    $msg = 'Active semester updated.'; $msgType = 'success';
}

// ── DELETE SEMESTER ──
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Safety check: don't allow deletion if attendance records are linked to this semester
    $chk = $conn->prepare("SELECT COUNT(*) AS c FROM tblattendance WHERE sessionTermId = ?");
    $chk->bind_param("i", $id); $chk->execute();
    $used = $chk->get_result()->fetch_assoc()['c']; // Number of attendance records using this semester
    if ($used > 0) {
        // Prevent deletion to protect data integrity
        $msg = 'Cannot delete — this semester has ' . $used . ' attendance record(s) linked to it.';
        $msgType = 'danger';
    } else {
        // Safe to delete — no attendance records reference this semester
        $stmt = $conn->prepare("DELETE FROM tblsessionterm WHERE Id = ?");
        $stmt->bind_param("i", $id); $stmt->execute();
        $msg = 'Semester deleted.'; $msgType = 'success';
    }
}

// ── ADD SEMESTER ──
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $sessionName = trim($_POST['sessionName'] ?? ''); // e.g. "2024-2025"
    $termId      = (int)($_POST['termId'] ?? 0);      // e.g. 1st Sem, 2nd Sem
    $setNow      = isset($_POST['setActive']) ? 1 : 0; // Whether to make this the active semester immediately
    $dt          = date('Y-m-d'); // Today's date

    if ($sessionName && $termId) {
        // Check for duplicate (same school year + same semester term)
        $chk = $conn->prepare("SELECT Id FROM tblsessionterm WHERE sessionName=? AND termId=?");
        $chk->bind_param("si", $sessionName, $termId); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = 'That school year + semester combination already exists.'; $msgType = 'danger';
        } else {
            // If admin wants this to be active immediately, deactivate all others first
            if ($setNow) $conn->query("UPDATE tblsessionterm SET isActive = 0");
            $stmt = $conn->prepare("INSERT INTO tblsessionterm (sessionName, termId, isActive, dateCreated) VALUES (?,?,?,?)");
            $stmt->bind_param("siis", $sessionName, $termId, $setNow, $dt);
            if ($stmt->execute()) {
                $msg = 'Semester added' . ($setNow ? ' and set as active.' : '.'); $msgType = 'success';
            } else {
                $msg = 'Error adding semester.'; $msgType = 'danger';
            }
        }
    } else {
        $msg = 'Please fill in all required fields.'; $msgType = 'danger';
    }
}

// Fetch all semester types (e.g. 1st Sem, 2nd Sem, Summer) for the dropdown
$terms    = $conn->query("SELECT * FROM tblterm ORDER BY Id");
// Fetch all semesters with their term name and count of linked attendance records
$semesters = $conn->query("
    SELECT st.*, t.termName,
           (SELECT COUNT(*) FROM tblattendance a WHERE a.sessionTermId = st.Id) AS recordCount
    FROM tblsessionterm st
    JOIN tblterm t ON t.Id = st.termId
    ORDER BY st.sessionName DESC, t.Id ASC
");
// Fetch just the one currently active semester for the banner display
$activeSem = $conn->query("SELECT st.*, t.termName FROM tblsessionterm st JOIN tblterm t ON t.Id=st.termId WHERE st.isActive=1 LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Semesters – Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Blue gradient banner showing the currently active semester */
    .active-banner {
      background: linear-gradient(135deg, var(--primary), var(--primary-d));
      color: #fff;
      border-radius: var(--radius);
      padding: 1.1rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .active-banner .ab-icon {
      width: 46px; height: 46px;
      background: rgba(255,255,255,.18); /* Semi-transparent white circle */
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; flex-shrink: 0;
    }
    .active-banner h3 { font-size: 1rem; margin-bottom: .1rem; opacity: .85; font-weight: 600; }
    .active-banner p  { font-size: 1.2rem; font-weight: 800; margin: 0; }
    .active-banner .no-active { font-size: 1rem; font-weight: 700; opacity: .9; }
    /* Green badge for active semester */
    .badge-active-sem {
      display: inline-flex; align-items: center; gap: .35rem;
      background: #d1fae5; color: #065f46;
      font-size: .72rem; font-weight: 700;
      padding: .2rem .65rem; border-radius: 50px;
    }
    /* Gray badge for inactive semester */
    .badge-inactive {
      display: inline-flex; align-items: center; gap: .35rem;
      background: #f3f4f6; color: var(--gray);
      font-size: .72rem; font-weight: 600;
      padding: .2rem .65rem; border-radius: 50px;
    }
    .record-count {
      font-size: .78rem; color: var(--gray);
      display: inline-flex; align-items: center; gap: .3rem;
    }
  </style>
</head>
<body>
<div class="app-wrapper">
  <?php include 'sidebar.php'; ?>
  <div class="main-content">
    <?php include 'topbar.php'; ?>
    <div class="page-content">

      <!-- Feedback alert -->
      <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss>
          <i class="fas fa-<?= $msgType==='success'?'check-circle':'circle-exclamation' ?>"></i>
          <?= htmlspecialchars($msg) ?>
        </div>
      <?php endif; ?>

      <!-- Active Semester Banner: prominently shows which semester is currently active -->
      <div class="active-banner">
        <div class="ab-icon"><i class="fas fa-calendar-check"></i></div>
        <div>
          <h3>Currently Active Semester</h3>
          <?php if ($activeSem): ?>
            <!-- Show the active semester name and term -->
            <p><?= htmlspecialchars($activeSem['sessionName']) ?> &mdash; <?= htmlspecialchars($activeSem['termName']) ?></p>
          <?php else: ?>
            <!-- Warn admin that no active semester is set — teachers cannot take attendance -->
            <p class="no-active"><i class="fas fa-triangle-exclamation"></i> No active semester set — faculty cannot take attendance!</p>
          <?php endif; ?>
        </div>
        <div style="margin-left:auto">
          <!-- "Add Semester" button in the banner (same as the one below) -->
          <button class="btn btn-primary" onclick="openModal('addModal')" style="background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.4)">
            <i class="fas fa-plus"></i> Add Semester
          </button>
        </div>
      </div>

      <div class="page-header">
        <h2><i class="fas fa-calendar-alt"></i> All Semesters</h2>
        <button class="btn btn-primary" onclick="openModal('addModal')">
          <i class="fas fa-plus"></i> Add Semester
        </button>
      </div>

      <!-- Semesters Table -->
      <div class="card">
        <div class="card-body" style="padding:0">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>School Year</th>
                  <th>Semester</th>
                  <th>Status</th>
                  <th>Records</th>
                  <th>Date Added</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php $i=1; while ($sem=$semesters->fetch_assoc()): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><strong><?= htmlspecialchars($sem['sessionName']) ?></strong></td>
                  <td><?= htmlspecialchars($sem['termName']) ?></td>
                  <td>
                    <!-- Show active/inactive badge based on isActive flag -->
                    <?php if ($sem['isActive']): ?>
                      <span class="badge-active-sem"><i class="fas fa-circle" style="font-size:.5rem"></i> Active</span>
                    <?php else: ?>
                      <span class="badge-inactive">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <!-- Display how many attendance records are linked to this semester -->
                    <span class="record-count">
                      <i class="fas fa-clipboard-list"></i> <?= number_format($sem['recordCount']) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($sem['dateCreated']) ?></td>
                  <td>
                    <!-- Show "Set Active" button only if the semester is not already active -->
                    <?php if (!$sem['isActive']): ?>
                      <a href="?setActive=<?= $sem['Id'] ?>"
                         class="btn btn-success btn-sm"
                         onclick="return confirm('Set \'<?= htmlspecialchars($sem['sessionName'].' – '.$sem['termName']) ?>\' as the active semester? This will deactivate the current one.')"
                         title="Set as Active">
                        <i class="fas fa-check"></i> Set Active
                      </a>
                    <?php else: ?>
                      <span style="font-size:.78rem;color:var(--gray);font-style:italic">Currently active</span>
                    <?php endif; ?>
                    <!-- Show Delete button only if no records are linked AND it's not active -->
                    <?php if ($sem['recordCount'] == 0 && !$sem['isActive']): ?>
                      <a href="?delete=<?= $sem['Id'] ?>"
                         class="btn btn-danger btn-sm"
                         onclick="return confirm('Delete this semester?')"
                         title="Delete">
                        <i class="fas fa-trash"></i>
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endwhile; ?>
                <!-- Show empty state if no semesters have been added yet -->
                <?php if ($semesters->num_rows === 0): ?>
                <tr>
                  <td colspan="7" style="text-align:center;padding:2rem;color:var(--gray)">
                    No semesters added yet. Add one to get started.
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Quick Guide: explains how semesters work to administrators -->
      <div class="card" style="margin-top:1.5rem">
        <div class="card-header"><h3><i class="fas fa-circle-info"></i> How Semesters Work</h3></div>
        <div class="card-body" style="font-size:.88rem;line-height:1.8;color:#444">
          <p><i class="fas fa-check-circle" style="color:var(--success)"></i> <strong>Only one semester can be active at a time.</strong> Faculty use the active semester when taking attendance.</p>
          <p><i class="fas fa-check-circle" style="color:var(--success)"></i> <strong>Changing the active semester does not delete old records.</strong> Past attendance stays linked to its semester.</p>
          <p><i class="fas fa-check-circle" style="color:var(--success)"></i> <strong>School Year format:</strong> use <code>2024-2025</code> style. You can add 1st Sem, 2nd Sem, and Summer separately.</p>
          <p><i class="fas fa-triangle-exclamation" style="color:var(--warning)"></i> <strong>Semesters with attendance records cannot be deleted</strong> to protect data integrity.</p>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ADD SEMESTER MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <h4><i class="fas fa-calendar-plus"></i> Add Semester</h4>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add"> <!-- Signals PHP to run ADD SEMESTER block -->
        <div class="form-group">
          <label>School Year *</label>
          <!-- Pattern attribute enforces YYYY-YYYY format in the browser -->
          <input type="text" name="sessionName" class="form-control" required
                 placeholder="e.g. 2024-2025"
                 pattern="\d{4}-\d{4}"
                 title="Format: YYYY-YYYY (e.g. 2024-2025)"
                 value="<?= htmlspecialchars($_POST['sessionName'] ?? '') ?>"> <!-- Re-populate on error -->
          <small style="color:var(--gray);font-size:.78rem">Format: 2024-2025</small>
        </div>
        <div class="form-group">
          <label>Semester *</label>
          <select name="termId" class="form-control" required>
            <option value="">— Select Semester —</option>
            <?php $terms->data_seek(0); while ($t=$terms->fetch_assoc()): ?> <!-- Loop term types -->
            <option value="<?= $t['Id'] ?>"><?= htmlspecialchars($t['termName']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <!-- Checkbox to optionally activate this semester immediately upon creation -->
        <div class="form-group" style="display:flex;align-items:center;gap:.6rem;margin-top:.5rem">
          <input type="checkbox" name="setActive" id="setActive" style="width:16px;height:16px;cursor:pointer">
          <label for="setActive" style="margin:0;cursor:pointer;font-weight:600">
            Set as active semester immediately
          </label>
        </div>
        <!-- Info box explaining what checking the box does -->
        <div class="alert alert-info" style="margin-top:.75rem;font-size:.82rem">
          <i class="fas fa-info-circle"></i>
          Checking the box above will deactivate the current active semester and make this one active right away.
        </div>
        <button type="submit" class="btn btn-primary btn-block" style="margin-top:.5rem">
          <i class="fas fa-save"></i> Save Semester
        </button>
      </form>
    </div>
  </div>
</div>

<script src="../assets/js/app.js"></script>
</body>
</html>