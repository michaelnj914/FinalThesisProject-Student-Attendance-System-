<?php
session_start(); // Start or resume the PHP session so we can read/write $_SESSION variables
include 'includes/dbcon.php'; // Load the database connection file, making $conn available

// If the user is already logged in (session has a userId), redirect them to their dashboard
if (isset($_SESSION['userId'])) {
    // Ternary: if role is 'admin' go to admin dashboard, otherwise go to teacher dashboard
    $dest = $_SESSION['role'] === 'admin' ? 'admin/index.php' : 'teacher/index.php';
    header("Location: $dest"); // Send HTTP redirect header
    exit; // Stop script execution after redirect
}

$error = ''; // Initialize an empty error message string

// Only run the login logic if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = $_POST['role']     ?? ''; // Get the selected role (admin or faculty), default empty string
    $email    = trim($_POST['email']    ?? ''); // Get and trim whitespace from the email input
    $password = $_POST['password'] ?? ''; // Get the password input (not trimmed to preserve spaces)

    // Validate that all three fields were filled in
    if (empty($role) || empty($email) || empty($password)) {
        $error = 'All fields are required.'; // Set error if any field is empty
    } else {
        // Handle admin login
        if ($role === 'admin') {
            // Prepare a parameterized query to find admin by email (prevents SQL injection)
            $stmt = $conn->prepare("SELECT * FROM tbladmin WHERE emailAddress = ? LIMIT 1");
            $stmt->bind_param("s", $email); // Bind $email as a string parameter
            $stmt->execute(); // Run the query
            $user = $stmt->get_result()->fetch_assoc(); // Fetch the result as an associative array

            // Check if a user was found AND the provided password matches the stored hash
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true); // Regenerate session ID to prevent session fixation attacks
                // Store user info in the session
                $_SESSION['userId']    = $user['Id'];
                $_SESSION['firstName'] = $user['firstName'];
                $_SESSION['lastName']  = $user['lastName'];
                $_SESSION['email']     = $user['emailAddress'];
                $_SESSION['role']      = 'admin'; // Mark role as admin
                header("Location: admin/index.php"); // Redirect to admin dashboard
                exit; // Stop further execution
            } else {
                $error = 'Invalid email or password.'; // Set error for wrong credentials
            }

        // Handle faculty (teacher) login
        } elseif ($role === 'faculty') {
            // Prepare query to look up teacher by email
            $stmt = $conn->prepare("SELECT * FROM tblclassteacher WHERE emailAddress = ? LIMIT 1");
            $stmt->bind_param("s", $email); // Bind email as string
            $stmt->execute(); // Run query
            $user = $stmt->get_result()->fetch_assoc(); // Fetch result row

            // Check if user was found AND password is correct
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true); // Regenerate session ID for security
                // Store all necessary teacher info in the session
                $_SESSION['userId']      = $user['Id'];
                $_SESSION['firstName']   = $user['firstName'];
                $_SESSION['lastName']    = $user['lastName'];
                $_SESSION['email']       = $user['emailAddress'];
                $_SESSION['role']        = 'teacher'; // Mark role as teacher
                $_SESSION['classId']     = $user['classId']; // Store teacher's assigned class/program ID
                $_SESSION['classArmId']  = $user['classArmId']; // Store teacher's assigned section ID
                header("Location: teacher/index.php"); // Redirect to teacher dashboard
                exit; // Stop further execution
            } else {
                $error = 'Invalid email or password.'; // Wrong credentials
            }
        } else {
            $error = 'Please select a valid role.'; // Role was not 'admin' or 'faculty'
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"> <!-- Set character encoding to UTF-8 -->
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Make page responsive on mobile -->
  <title>Login – Student Attendance System</title>
  <link rel="stylesheet" href="assets/css/style.css"> <!-- Load the main stylesheet -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> <!-- Load Font Awesome icons -->
</head>
<body>
<div class="login-page"> <!-- Outer container for centering the login card -->
  <div class="login-card"> <!-- The white card/box that holds the form -->
    <div class="logo">
      <!-- Try to display the school logo image; hide it if the file doesn't exist -->
      <img src="assets/img/logo.png" onerror="this.style.display='none'" alt="AMA Logo">
      <!-- Show a graduation cap icon as fallback if logo image is missing -->
      <i class="fas fa-graduation-cap" style="font-size:3rem;color:var(--primary);display:<?= file_exists('assets/img/logo.png') ? 'none' : 'block' ?>"></i>
    </div>
    <h1>Student Attendance System</h1> <!-- Page heading -->
    <p class="sub">Sign in to continue</p> <!-- Subtitle -->

    <!-- Show error alert only if $error is not empty -->
    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Login form; submits via POST to the same page (action="") -->
    <form method="POST" action="">
      <div class="form-group">
        <label>Role</label>
        <select name="role" class="form-control" required>
          <option value="">— Select Role —</option>
          <!-- Retain selected option if form was submitted with errors -->
          <option value="admin"   <?= ($_POST['role'] ?? '') === 'admin'   ? 'selected' : '' ?>>Administrator</option>
          <option value="faculty" <?= ($_POST['role'] ?? '') === 'faculty' ? 'selected' : '' ?>>Faculty</option>
        </select>
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <!-- Re-populate email field with previously entered value on form error -->
        <input type="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@school.edu" autocomplete="email">
      </div>
      <div class="form-group">
        <label>Password</label>
        <!-- Password field; autocomplete hint helps password managers -->
        <input type="password" name="password" class="form-control" required placeholder="••••••••" autocomplete="current-password">
      </div>
      <!-- Submit button -->
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:.5rem">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </button>
    </form>
  </div>
</div>
</body>
</html>