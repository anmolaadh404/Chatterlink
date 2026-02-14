<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config/db.php';

$user_id = $_SESSION['user_id'];
$friend_id = filter_input(INPUT_POST, 'friend_id', FILTER_VALIDATE_INT);

if (!$friend_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid friend ID']);
    exit;
}

// Delete friendship in both directions
$stmt = $conn->prepare("
    DELETE FROM friends 
    WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
");
$stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to remove friend']);
}
$stmt->close();
?>
