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
$message = trim($_POST['message'] ?? '');
$user_id = $_SESSION['user_id'];

// Validate inputs
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid message ID']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

if (strlen($message) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long (max 5000 characters)']);
    exit;
}

// Verify message belongs to user before editing
$stmt = $conn->prepare("SELECT sender_id FROM messages WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$msg_row = $result->fetch_assoc();
$stmt->close();

if (!$msg_row) {
    http_response_code(404);
    echo json_encode(['error' => 'Message not found']);
    exit;
}

if ($msg_row['sender_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'You can only edit your own messages']);
    exit;
}

// Check if message is too old (e.g., can't edit after 1 hour)
$stmt = $conn->prepare("SELECT TIMESTAMPDIFF(MINUTE, created_at, NOW()) as age_minutes FROM messages WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$age_row = $result->fetch_assoc();
$stmt->close();

if ($age_row && $age_row['age_minutes'] > 60) {
    http_response_code(400);
    echo json_encode(['error' => 'Messages can only be edited within 1 hour of sending']);
    exit;
}

// Update the message
$stmt = $conn->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param("sii", $message, $id, $user_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to edit message']);
}
$stmt->close();
?>
