<?php
session_start();
include '../config/db.php';

$id = (int)$_POST['id'];
$msg = mysqli_real_escape_string($conn, $_POST['message']);
$me = $_SESSION['user_id'];

mysqli_query($conn, "
    UPDATE messages 
    SET message='$msg' 
    WHERE id=$id AND sender_id=$me
");
