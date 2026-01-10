<?php
session_start();
session_destroy();
header("Location: /Chatterlink/auth/login.php"); // Absolute path
exit;
?>