<?php
// Load session configuration
require_once 'session-config.php';

// Destroy session completely
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/', 'app.2rich.capital', true, true);
}

// Destroy session file
session_destroy();

// Redirect to login
header('Location: https://app.2rich.capital/login/');
exit;
?>