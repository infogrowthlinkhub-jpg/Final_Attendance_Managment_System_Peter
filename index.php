<?php
require_once 'config.php';

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to dashboard if logged in
    header('Location: dashboard.php');
    exit();
} else {
    // Redirect to login if not logged in
    header('Location: login.php');
    exit();
}
?>

