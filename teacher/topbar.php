<!-- Topbar container: the horizontal top navigation bar -->
<div class="topbar">

  <!-- Left side of the topbar -->
  <div class="topbar-left">

    <!-- Hamburger menu button using a Font Awesome bars icon -->
    <button class="menu-toggle"><i class="fas fa-bars"></i></button>

    <!-- Displays the page title; uses $pageTitle if set, otherwise defaults to 'Dashboard' -->
    <span class="page-title"><?= $pageTitle ?? 'Dashboard' ?></span>

  </div>

  <!-- Right side of the topbar -->
  <div class="topbar-right">

    <!-- User info badge shown on the top right -->
    <div class="user-badge">

      <!-- Avatar circle: takes the first letter of the user's first name and uppercases it -->
      <!-- substr() grabs the first character, strtoupper() makes it capital -->
      <div class="avatar"><?= strtoupper(substr($_SESSION['firstName'], 0, 1)) ?></div>

      <!-- Displays the full name of the logged-in user (first + last name) -->
      <!-- htmlspecialchars() prevents XSS by escaping special HTML characters -->
      <span><?= htmlspecialchars($_SESSION['firstName'] . ' ' . $_SESSION['lastName']) ?></span>

    </div>
  </div>
</div>