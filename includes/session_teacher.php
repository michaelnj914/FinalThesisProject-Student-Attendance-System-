<?php
// Only start a new session if one isn't already running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if the user is not logged in OR if their role is not 'teacher'
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); // Go up one directory to the login page
    exit; // Prevent any teacher page content from being sent to unauthorized users
}
?>