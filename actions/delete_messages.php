<?php
session_start();
include '../config/db.php';

$id = (int)$_POST['id'];
$me = $_SESSION['user_id'];

mysqli_query($conn, "
    DELETE FROM messages 
    WHERE id=$id AND sender_id=$me
");
