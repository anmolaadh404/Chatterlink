<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config/db.php';

$sender_id = $_SESSION['user_id'];
$receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);
$message = trim($_POST['message'] ?? '');
$photo_url = '';

// Validate inputs
if (!$receiver_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid receiver ID']);
    exit;
}

// Check if there's a message or photo
if (empty($message) && (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message or photo is required']);
    exit;
}

if (!empty($message) && strlen($message) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long']);
    exit;
}

if ($sender_id === $receiver_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot message yourself']);
    exit;
}

// Verify receiver exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $receiver_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    $stmt->close();
    exit;
}
$stmt->close();

// Check if users are friends (accepting both directions)
$stmt = $conn->prepare("
    SELECT id FROM friends 
    WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
    AND status = 'accepted'
");
$stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'You are not friends with this user']);
    $stmt->close();
    exit;
}
$stmt->close();

// Handle photo upload if provided
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== 0) {
        http_response_code(400);
        echo json_encode(['error' => 'File upload error: ' . $file['error']]);
        exit;
    }

    if (!in_array($file['type'], $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'Only image files are allowed. Got: ' . $file['type']]);
        exit;
    }

    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['error' => 'File size must not exceed 5MB']);
        exit;
    }

    $upload_dir = __DIR__ . '/../assets/uploads/chat/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create upload directory']);
            exit;
        }
    }

    // Make sure directory is writable
    chmod($upload_dir, 0777);

    $filename = $sender_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . basename($file['name']);
    $filepath = $upload_dir . $filename;
    
    // Debug info
    $debug_info = [
        'tmp_name' => $file['tmp_name'],
        'tmp_exists' => file_exists($file['tmp_name']),
        'tmp_readable' => is_readable($file['tmp_name']),
        'upload_dir' => $upload_dir,
        'dir_writable' => is_writable($upload_dir),
        'filepath' => $filepath
    ];
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        chmod($filepath, 0644);
        $photo_url = '/Chatterlink/assets/uploads/chat/' . $filename;
        // Add photo URL to message
        if (!empty($message)) {
            $message = $message . " [PHOTO:" . $photo_url . "]";
        } else {
            $message = "[PHOTO:" . $photo_url . "]";
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload photo. Move failed.', 'debug' => $debug_info]);
        exit;
    }
}

// Insert the message
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param("iis", $sender_id, $receiver_id, $message);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message_id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send message']);
}
$stmt->close();
?>