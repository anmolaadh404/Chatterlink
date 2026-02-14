<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

include '../config/db.php';

$me = $_SESSION['user_id'];
$other = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$other) {
    exit;
}

// Fetch messages between the two users
$q = $conn->prepare("
    SELECT message_id, sender_id, receiver_id, message, created_at
    FROM messages
    WHERE 
      (sender_id = ? AND receiver_id = ?)
      OR
      (sender_id = ? AND receiver_id = ?)
    ORDER BY created_at ASC
");

$q->bind_param("iiii", $me, $other, $other, $me);
$q->execute();
$res = $q->get_result();

if ($res->num_rows === 0) {
    // No messages yet - show nothing (chat.php will show empty state)
    exit;
}

while ($row = $res->fetch_assoc()) {
    $isMe = ($row['sender_id'] == $me);
    $message = $row['message'];
    $message_id = $row['message_id'];
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
        // Add ../ prefix since we're in actions folder
        $display_path = '../' . $photo_url;
        echo '<img src="'.htmlspecialchars($display_path).'" alt="Photo" style="max-width: 300px; border-radius: 8px; margin-bottom: 8px; display:block;">';
    }
    
    // Display text message if exists
    if (!empty(trim($display_message))) {
        echo nl2br($display_message);
    }
    
    // Actions only for sender
 // Actions only for sender
    if ($isMe) {
        // Check if message is recent enough to edit/delete (5 minutes)
        $created_time = strtotime($row['created_at']);
        $current_time = time();
        $time_diff = $current_time - $created_time;
        $time_limit = 300; // 5 minutes in seconds
        
        if ($time_diff <= $time_limit) {
            // Show edit/delete buttons only if within time limit
            echo '
            <div class="actions">
                <button onclick="editMessage('.$message_id.')" title="Edit">✏️</button>
                <button onclick="deleteMessage('.$message_id.')" title="Delete">🗑️</button>
            </div>';
        } else {
            // Optionally show a disabled indicator or nothing
            echo '
            <div class="actions">
                <span style="font-size:10px; color:#999; cursor:not-allowed;" title="Time limit expired">🔒</span>
            </div>';
        }
    }
    
    echo '</div>';
    echo '</div>';
}

$q->close();
?>