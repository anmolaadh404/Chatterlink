<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$sender   = $_SESSION['user_id'];
$receiver = (int)$_POST['receiver_id'];
$message  = trim($_POST['message']);

if ($message === "") exit;

/*
 Prevent duplicates:
 same sender, receiver, message
 within last 2 seconds
*/
$check = $conn->prepare("
    SELECT id FROM messages
    WHERE sender_id = ?
      AND receiver_id = ?
      AND message = ?
      AND created_at >= NOW() - INTERVAL 2 SECOND
");
$check->bind_param("iis", $sender, $receiver, $message);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $sender, $receiver, $message);
    $stmt->execute();
    $stmt->close();
}

$check->close();
