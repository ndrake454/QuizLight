<?php
require_once 'config.php';

// End the session
session_start();
session_unset();
session_destroy();

// Redirect to the home page
header('Location: /');
exit;
?>