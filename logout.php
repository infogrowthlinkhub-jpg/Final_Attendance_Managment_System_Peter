<?php
require_once 'config.php';

// Session is already started in config.php
session_unset();
session_destroy();

header('Location: login.php');
exit();
?>

