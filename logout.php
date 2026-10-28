<?php

// =====================================================
// LOGOUT.PHP
// Student Skill Exchange Platform
// =====================================================

session_start();

// Destroy all session data
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect user to login page
header("Location: login.php");
exit();

?>