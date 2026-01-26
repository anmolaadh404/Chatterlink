<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config/db.php';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

// Validate message ID
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid message ID']);
    exit;
}

// Verify message belongs to user before deleting
$stmt = $conn->prepare("SELECT sender_id FROM messages WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$message = $result->fetch_assoc();
$stmt->close();

if (!$message) {
    http_response_code(404);
    echo json_encode(['error' => 'Message not found']);
    exit;
}

if ($message['sender_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'You can only delete your own messages']);
    exit;
}

// Soft delete the message
$stmt = $conn->prepare("UPDATE messages SET is_deleted = TRUE WHERE id = ? AND sender_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param("ii", $id, $user_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete message']);
}
$stmt->close();
?>
