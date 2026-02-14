<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config/db.php';

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'update_bio') {
    $name = trim($_POST['name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['error' => 'Name cannot be empty']);
        exit;
    }
    
    if (strlen($bio) > 500) {
        echo json_encode(['error' => 'Bio must be less than 500 characters']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, bio = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $name, $bio, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
    } else {
        echo json_encode(['error' => 'Failed to update profile']);
    }
    $stmt->close();
    
} elseif ($action === 'upload_avatar') {
    
    if (!isset($_FILES['avatar'])) {
        echo json_encode(['error' => 'No file selected']);
        exit;
    }
    
    $file = $_FILES['avatar'];
    
    if ($file['error'] !== 0) {
        echo json_encode(['error' => 'File upload error: ' . $file['error']]);
        exit;
    }
    
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['error' => 'Only JPEG, PNG, GIF, and WEBP images are allowed']);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        echo json_encode(['error' => 'File size must not exceed 5MB']);
        exit;
    }
    
    $upload_dir = __DIR__ . '/../assets/uploads/profile/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = $user_id . '_' . time() . '_' . basename($file['name']);
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        chmod($filepath, 0644);
        
        // Get old avatar to delete
        $stmt = $conn->prepare("SELECT avatar FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $old = $result->fetch_assoc();
        $stmt->close();
        
        // Delete old avatar if exists
        if ($old && $old['avatar']) {
            $old_file = __DIR__ . '/../' . ltrim($old['avatar'], '/');
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        
        // Store relative path from root
        $relative_path = 'assets/uploads/profile/' . $filename;
        
        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
        $stmt->bind_param("si", $relative_path, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Avatar updated successfully!',
                'avatar_url' => $relative_path
            ]);
        } else {
            echo json_encode(['error' => 'Failed to save avatar to database']);
        }
        $stmt->close();
        
    } else {
        echo json_encode(['error' => 'Failed to upload file']);
    }
    
} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>
