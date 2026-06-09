<?php
// Only start a new session if one isn't already active (prevents "headers already sent" errors)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is NOT logged in, OR if they are logged in but not as an admin
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'admin') {
    // Calculate how many directory levels deep we are, then build the relative path back to index.php
    // str_repeat('../', n) produces "../", "../../", etc. depending on depth
    header("Location: " . str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/', strlen($_SERVER['DOCUMENT_ROOT'])) - 1) . "index.php");
    exit; // Stop script execution so no admin content is served to unauthorized users
}
?>