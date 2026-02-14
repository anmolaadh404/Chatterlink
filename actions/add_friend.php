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

if ($friend_id === $user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot add yourself']);
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $friend_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    $stmt->close();
    exit;
}
$stmt->close();

// Check if already friends or request exists
$stmt = $conn->prepare("
    SELECT status FROM friends 
    WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
");
$stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['status'] === 'accepted') {
        http_response_code(400);
        echo json_encode(['error' => 'Already friends']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Friend request already sent']);
    }
    $stmt->close();
    exit;
}
$stmt->close();

// Create friend request
$stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
$stmt->bind_param("ii", $user_id, $friend_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send friend request']);
}
$stmt->close();
?>
