<!-- ── Sidebar (Faculty Panel) ──
     This is a shared partial included on every teacher-facing page.
     The #sidebar ID is used by app.js to toggle open/close on mobile. -->
<div class="sidebar" id="sidebar">

  <!-- Brand / logo area at the top of the sidebar -->
  <div class="sidebar-brand">
    <!-- Graduation cap icon using the site's primary color CSS variable -->
    <i class="fas fa-graduation-cap" style="font-size:1.5rem;color:var(--primary)"></i>
    <span>
      Student Attendance
      <!-- <small> renders "Faculty Panel" in smaller text below the main title -->
      <small>Faculty Panel</small>
    </span>
  </div>

  <!-- Navigation link list -->
  <nav class="sidebar-nav">

    <!-- ── MAIN section ── -->
    <div class="nav-label">Main</div> <!-- Section heading label (not a link) -->

    <div class="nav-item">
      <!-- Dashboard link
           basename($_SERVER['PHP_SELF']) returns just the filename of the current page (e.g. "index.php")
           If it matches 'index.php', add the 'active' CSS class to highlight this link -->
      <a href="index.php" class="<?= basename($_SERVER['PHP_SELF'])==='index.php'?'active':'' ?>">
        <i class="fas fa-gauge"></i> Dashboard
      </a>
    </div>

    <!-- ── ATTENDANCE section ── -->
    <div class="nav-label">Attendance</div> <!-- Section heading label (not a link) -->

    <div class="nav-item">
      <!-- Take Attendance link
           Marked active when the current page filename is 'take-attendance.php' -->
      <a href="take-attendance.php" class="<?= basename($_SERVER['PHP_SELF'])==='take-attendance.php'?'active':'' ?>">
        <i class="fas fa-clipboard-check"></i> Take Attendance
      </a>
    </div>

    <div class="nav-item">
      <!-- View Attendance link
           Marked active when the current page filename is 'view-attendance.php' -->
      <a href="view-attendance.php" class="<?= basename($_SERVER['PHP_SELF'])==='view-attendance.php'?'active':'' ?>">
        <i class="fas fa-calendar-check"></i> View Attendance
      </a>
    </div>

    <!-- ── STUDENTS section ── -->
    <div class="nav-label">Students</div> <!-- Section heading label (not a link) -->

    <div class="nav-item">
      <!-- My Students link
           Uses in_array() instead of a simple === comparison because this link should stay
           highlighted on BOTH 'my-students.php' AND 'student-report.php'
           (a student's report page is considered part of the "My Students" section) -->
      <a href="my-students.php" class="<?= in_array(basename($_SERVER['PHP_SELF']),['my-students.php','student-report.php'])?'active':'' ?>">
        <i class="fas fa-users"></i> My Students
      </a>
    </div>

  </nav>

  <!-- Logout link pinned to the bottom of the sidebar -->
  <div class="sidebar-footer">
    <!-- Links to the shared logout script which destroys the session and redirects to login -->
    <a href="../includes/logout.php">
      <i class="fas fa-right-from-bracket"></i> Logout
    </a>
  </div>

</div> <!-- end .sidebar -->