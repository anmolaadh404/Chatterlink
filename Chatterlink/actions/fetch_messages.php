<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) exit;

$me = $_SESSION['user_id'];
$other = (int)$_GET['user_id'];

$q = $conn->prepare("
    SELECT * FROM messages
    WHERE 
      (sender_id = ? AND receiver_id = ?)
      OR
      (sender_id = ? AND receiver_id = ?)
    ORDER BY created_at ASC
");

$q->bind_param("iiii", $me, $other, $other, $me);
$q->execute();
$res = $q->get_result();

while ($row = $res->fetch_assoc()) {

    $isMe = ($row['sender_id'] == $me);

    echo '<div class="msg-row '.($isMe ? 'me-row' : 'them-row').'">';

    echo '<div class="msg '.($isMe ? 'me' : 'them').'">';

    echo nl2br(htmlspecialchars($row['message']));

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