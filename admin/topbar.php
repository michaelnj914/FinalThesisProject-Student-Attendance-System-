<div class="topbar"> <!-- Top navigation bar container -->
  <div class="topbar-left"> <!-- Left side: hamburger menu + page title -->
    <button class="menu-toggle" aria-label="Toggle sidebar"> <!-- Button that opens/closes sidebar on mobile -->
      <i class="fas fa-bars"></i> <!-- Hamburger icon -->
    </button>
    <!-- Display the current page title set by each page, fallback to 'Dashboard' if not set -->
    <span class="page-title"><?= $pageTitle ?? 'Dashboard' ?></span>
  </div>
  <div class="topbar-right"> <!-- Right side: logged-in user info -->
    <div class="user-badge"> <!-- Container for avatar and name -->
      <!-- Avatar circle showing first letter of the logged-in user's first name, uppercased -->
      <div class="avatar"><?= strtoupper(substr($_SESSION['firstName'], 0, 1)) ?></div>
      <!-- Display the full name of the logged-in admin, escaped for safety -->
      <span><?= htmlspecialchars($_SESSION['firstName'] . ' ' . $_SESSION['lastName']) ?></span>
    </div>
  </div>
</div>