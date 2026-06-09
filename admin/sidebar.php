<div class="sidebar" id="sidebar"> <!-- Sidebar container; id used by JS to toggle open/close -->
  <div class="sidebar-brand"> <!-- Top area of sidebar with logo/title -->
    <i class="fas fa-graduation-cap" style="font-size:1.5rem;color:var(--white)"></i> <!-- Graduation cap icon -->
    <span>Student Attendance <small>Admin Panel</small></span> <!-- System name and role label -->
  </div>

  <nav class="sidebar-nav"> <!-- Navigation links section -->
    <div class="nav-label">Main</div> <!-- Section heading label -->
    <div class="nav-item">
      <!-- Link to admin dashboard; adds 'active' CSS class if current file is index.php -->
      <a href="index.php" class="<?= basename($_SERVER['PHP_SELF'])==='index.php'?'active':'' ?>">
        <i class="fas fa-gauge"></i> Dashboard
      </a>
    </div>

    <div class="nav-label">Management</div> <!-- Section heading label -->
    <div class="nav-item">
      <!-- Mark active if on students.php or student-report.php -->
      <a href="students.php" class="<?= in_array(basename($_SERVER['PHP_SELF']),['students.php','student-report.php'])?'active':'' ?>">
        <i class="fas fa-users"></i> Students
      </a>
    </div>
    <div class="nav-item">
      <!-- Mark active if on teachers.php -->
      <a href="teachers.php" class="<?= basename($_SERVER['PHP_SELF'])==='teachers.php'?'active':'' ?>">
        <i class="fas fa-chalkboard-user"></i> Faculty
      </a>
    </div>
    <div class="nav-item">
      <!-- Mark active if on classes.php -->
      <a href="classes.php" class="<?= basename($_SERVER['PHP_SELF'])==='classes.php'?'active':'' ?>">
        <i class="fas fa-book-open"></i> Programs &amp; Sections <!-- &amp; is HTML entity for & -->
      </a>
    </div>

    <div class="nav-label">Attendance</div> <!-- Section heading label -->
    <div class="nav-item">
      <!-- Mark active if on semesters.php -->
      <a href="semesters.php" class="<?= basename($_SERVER['PHP_SELF'])==='semesters.php'?'active':'' ?>">
        <i class="fas fa-calendar-alt"></i> Semesters
      </a>
    </div>
    <div class="nav-item">
      <!-- Mark active if on attendance.php -->
      <a href="attendance.php" class="<?= basename($_SERVER['PHP_SELF'])==='attendance.php'?'active':'' ?>">
        <i class="fas fa-calendar-check"></i> View Attendance
      </a>
    </div>
  </nav>

  <div class="sidebar-footer"> <!-- Bottom of sidebar -->
    <!-- Logout link — goes to the shared logout script one level up -->
    <a href="../includes/logout.php">
      <i class="fas fa-right-from-bracket"></i> Logout
    </a>
  </div>
</div>