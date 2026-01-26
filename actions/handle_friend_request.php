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
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if (!$request_id || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Verify request exists and is for this user
$stmt = $conn->prepare("SELECT id, user_id FROM friends WHERE id = ? AND friend_id = ? AND status = 'pending'");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Friend request not found']);
    $stmt->close();
    exit;
}
$row = $result->fetch_assoc();
$requester_id = $row['user_id'];
$stmt->close();

if ($action === 'accept') {
    $stmt = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to accept request']);
    }
    $stmt->close();
} else {
    $stmt = $conn->prepare("DELETE FROM friends WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reject request']);
    }
    $stmt->close();
}
?>
