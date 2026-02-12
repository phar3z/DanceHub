<?php
session_start();
session_unset();
session_destroy();

// Start a new session for the logout message
session_start();
$_SESSION['logout_success'] = 'You have been successfully logged out.';
$_SESSION['active_form'] = 'login';

header("Location: index.php");
exit();
?>