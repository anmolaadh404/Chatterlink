<?php
session_start();
// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /Chatterlink/auth/login.php");
    exit;
}
include '../config/db.php';

$me = $_SESSION['user_id'];
// Use filter_input to sanitize the GET variable to prevent SQL Injection
$receiver = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$receiver) {
    header("Location: /Chatterlink/users.php");
    exit;
}

// Security: Use prepared statements to prevent SQL Injection
$stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $receiver);
$stmt->execute();
$result = $stmt->get_result();
$r = $result->fetch_assoc();

if (!$r) {
    die("User not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat with <?php echo htmlspecialchars($r['name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body, html { height: 100%; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header {
            padding: 15px 20px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: bold;
        }

        .header a {
            text-decoration: none;
            font-size: 24px;
            color: #3797f0;
        }

        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background-color: #f9f9f9;
        }

        /* Message Bubbles Styles */
        .msg-row { display: flex; width: 100%; margin-bottom: 4px; }
        .me-row { justify-content: flex-end; }
        .them-row { justify-content: flex-start; }

        .msg {
            max-width: 75%;
            padding: 10px 15px;
            border-radius: 18px;
            font-size: 14px;
            position: relative;
            line-height: 1.4;
        }

        .me { background: #3797f0; color: #fff; border-bottom-right-radius: 4px; }
        .them { background: #e4e6eb; color: #000; border-bottom-left-radius: 4px; }

        .input-area {
            padding: 15px;
            background: #fff;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
        }

        .input-area input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 25px;
            outline: none;
            background: #f0f2f5;
        }

        .input-area button {
            background: #3797f0;
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
        }

        .input-area button:hover { background: #287dc5; }
        
        /* Message Actions */
        .actions { font-size: 10px; margin-top: 5px; opacity: 0.7; }
        .me .actions { color: #e1e1e1; text-align: right; }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="header">
        <a href="/Chatterlink/users.php">←</a>
        <span><?php echo htmlspecialchars($r['name']); ?></span>
    </div>

    <div class="messages" id="chatBox">
        <!-- Messages loaded via AJAX -->
    </div>

    <form class="input-area" id="chatForm">
        <input type="text" id="msgInput" autocomplete="off" placeholder="Type a message...">
        <button type="submit">Send</button>
    </form>
</div>

<script>
const chatBox = document.getElementById('chatBox');
const chatForm = document.getElementById('chatForm');
const msgInput = document.getElementById('msgInput');
const receiverId = <?php echo $receiver; ?>;
let lastData = "";

function loadChat(){
    fetch(`../actions/fetch_messages.php?user_id=${receiverId}`)
    .then(r => r.text())
    .then(d => {
        if(d !== lastData){
            chatBox.innerHTML = d;
            // Scroll to bottom only if content changed
            chatBox.scrollTop = chatBox.scrollHeight;
            lastData = d;
        }
    })
    .catch(err => console.error("Error fetching messages:", err));
}

function sendMsg(e){
    if(e) e.preventDefault();
    
    let m = msgInput.value.trim();
    if(!m) return;

    fetch("../actions/send_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `receiver_id=${receiverId}&message=${encodeURIComponent(m)}`
    }).then(() => {
        msgInput.value = "";
        loadChat();
    });
}

// Event Listeners
chatForm.addEventListener('submit', sendMsg);

// Auto-refresh chat every 2 seconds
setInterval(loadChat, 2000);

// Initial load
loadChat();

// Global functions for message management (called from HTML returned by fetch_messages.php)
window.editMessage = function(id) {
    let newMsg = prompt("Edit your message:");
    if (!newMsg || newMsg.trim() === "") return;

    fetch("../actions/edit_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `id=${id}&message=${encodeURIComponent(newMsg)}`
    }).then(() => loadChat());
};

window.deleteMessage = function(id) {
    if (!confirm("Are you sure you want to delete this message?")) return;

    fetch("../actions/delete_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `id=${id}`
    }).then(() => loadChat());
};
</script>

</body>
</html>