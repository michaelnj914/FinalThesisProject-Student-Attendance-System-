<?php
session_start();    // Resume the existing session so we can destroy it
session_unset();    // Remove all variables stored in the current session
session_destroy();  // Completely destroy the session and its data on the server
header("Location: ../index.php"); // Redirect the user back to the login page (one level up)
exit; // Stop any further script execution after the redirect
?>