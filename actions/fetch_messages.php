# Navigate to your project
cd /home/anmol/Desktop/Codes/Chatterlink

# Remove the duplicate root-level directories (keeping Chatterlink/ subdirectory)
rm -rf auth actions config pages
rm -f index.php test_db.php users.php

# Verify the cleaned structure
ls -la
ls -la Chatterlink/<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

include '../config/db.php';

$me = $_SESSION['user_id'];
$other = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$other) {
    http_response_code(400);
    exit;
}

// Verify users are friends
$stmt = $conn->prepare("
    SELECT id FROM friends 
    WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
    AND status = 'accepted'
");
$stmt->bind_param("iiii", $me, $other, $other, $me);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    $stmt->close();
    exit;
}
$stmt->close();

$q = $conn->prepare("
    SELECT id, sender_id, message, created_at, is_deleted 
    FROM messages
    WHERE is_deleted = FALSE
    AND (
      (sender_id = ? AND receiver_id = ?)
      OR
      (sender_id = ? AND receiver_id = ?)
    )
    ORDER BY created_at ASC
");

$q->bind_param("iiii", $me, $other, $other, $me);
$q->execute();
$res = $q->get_result();

while ($row = $res->fetch_assoc()) {
    $isMe = ($row['sender_id'] == $me);
    $message = $row['message'];
    $display_message = htmlspecialchars($message);
    $photo_url = '';
    
    // Check if message contains photo marker
    if (strpos($message, '[PHOTO:') !== false) {
        preg_match('/\[PHOTO:(.*?)\]/', $message, $matches);
        if (!empty($matches[1])) {
            $photo_url = $matches[1];
            $display_message = preg_replace('/\s*\[PHOTO:.*?\]\s*/', '', $display_message);
        }
    }
    
    echo '<div class="msg-row '.($isMe ? 'me-row' : 'them-row').'">';
    echo '<div class="msg '.($isMe ? 'me' : 'them').'">';
    
    // Display photo if exists
    if (!empty($photo_url)) {
        echo '<img src="'.htmlspecialchars($photo_url).'" alt="Photo" style="max-width: 300px; border-radius: 8px; margin-bottom: 8px;">';
    }
    
    // Display text message if exists
    if (!empty(trim($display_message))) {
        echo nl2br($display_message);
    }
    
    // Actions only for sender
    if ($isMe) {
        echo '
        <div class="actions">
            <span class="edit-btn" onclick="editMessage('.$row['id'].')">✎</span>
            <span class="delete-btn" onclick="deleteMessage('.$row['id'].')">🗑️</span>
        </div>';
    }
    
    echo '</div></div>';
}

$q->close();
?>