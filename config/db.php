<?php
$conn = new mysqli("localhost", "root", "", "chatterlink");

if ($conn->connect_error) {
    die("DB Connection Failed: " . $conn->connect_error);
}
?>
