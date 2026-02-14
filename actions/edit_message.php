<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config/db.php';

$user_id = $_SESSION['user_id'];
$message_id = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
$new_message = trim($_POST['new_message'] ?? '');

if (!$message_id) {
    echo json_encode(['error' => 'Invalid message ID']);
    exit;
}

if (empty($new_message)) {
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

if (strlen($new_message) > 5000) {
    echo json_encode(['error' => 'Message too long']);
    exit;
}

// Verify the message belongs to the user AND check time limit
$stmt = $conn->prepare("SELECT sender_id, message, created_at FROM messages WHERE message_id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Message not found']);
    exit;
}

$message = $result->fetch_assoc();
$stmt->close();

if ($message['sender_id'] != $user_id) {
    echo json_encode(['error' => 'You can only edit your own messages']);
    exit;
}

// CHECK TIME LIMIT - 5 minutes (300 seconds)
$created_time = strtotime($message['created_at']);
$current_time = time();
$time_diff = $current_time - $created_time;
$edit_time_limit = 300; // 5 minutes in seconds

if ($time_diff > $edit_time_limit) {
    $minutes = floor($edit_time_limit / 60);
    echo json_encode(['error' => "Messages can only be edited within $minutes minutes of sending"]);
    exit;
}

// Preserve photo if exists
$old_message = $message['message'];
$photo_marker = '';
if (strpos($old_message, '[PHOTO:') !== false) {
    preg_match('/\[PHOTO:.*?\]/', $old_message, $matches);
    if (!empty($matches[0])) {
        $photo_marker = ' ' . $matches[0];
    }
}

// Combine new message with photo marker
$final_message = $new_message . $photo_marker;

// Update the message
$stmt = $conn->prepare("UPDATE messages SET message = ? WHERE message_id = ?");
$stmt->bind_param("si", $final_message, $message_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to update message']);
}

$stmt->close();
?>