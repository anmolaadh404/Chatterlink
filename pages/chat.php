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

// Verify users are friends
$stmt = $conn->prepare("
    SELECT id FROM friends 
    WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
    AND status = 'accepted'
");
$stmt->bind_param("iiii", $me, $receiver, $receiver, $me);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $stmt->close();
    die("You are not friends with this user. <a href='/Chatterlink/users.php'>Back</a>");
}
$stmt->close();

// Security: Use prepared statements to prevent SQL Injection
$stmt = $conn->prepare("SELECT name, username FROM users WHERE user_id = ?");
$stmt->bind_param("i", $receiver);
$stmt->execute();
$result = $stmt->get_result();
$r = $result->fetch_assoc();
$stmt->close();

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
        * { 
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", sans-serif;
        }
        
        body, html { 
            height: 100%; 
            margin: 0;
            background: #f5f7fa;
        }
        
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }

        .header {
            padding: 16px 24px;
            background: white;
            border-bottom: 1px solid #e1e8ed;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .header a {
            text-decoration: none;
            font-size: 20px;
            color: #0984e3;
            transition: color 0.3s ease;
        }
        
        .header a:hover {
            color: #0770d1;
        }
        
        .header-name {
            font-weight: 700;
            font-size: 16px;
            color: #2d3436;
        }
        
        .header-username {
            font-size: 13px;
            color: #636e72;
            font-weight: 400;
            margin-top: 2px;
        }

        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background-color: #f5f7fa;
            position: relative;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #636e72;
            gap: 12px;
        }

        .empty-state-icon {
            font-size: 56px;
            opacity: 0.5;
            margin-bottom: 8px;
        }

        .empty-state-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3436;
            margin: 0;
        }

        .empty-state-message {
            font-size: 14px;
            color: #636e72;
            margin: 0;
            max-width: 300px;
        }

        /* Message Bubbles Styles */
        .msg-row { 
            display: flex; 
            width: 100%; 
            margin-bottom: 4px;
        }
        
        .me-row { justify-content: flex-end; }
        .them-row { justify-content: flex-start; }

        .msg {
            max-width: 70%;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 14px;
            position: relative;
            line-height: 1.5;
            word-wrap: break-word;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        .msg img {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .me { 
            background: linear-gradient(135deg, #a2b0bbff 0%, #727e88ff 100%);
            color: white;
        }
        
        .them { 
            background: #cee8f8ff;
            color: #2d3436;
        }

        .input-area {
            padding: 16px 24px;
            background: white;
            border-top: 1px solid #e1e8ed;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .input-wrapper {
            flex: 1;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .file-input-wrapper {
            position: relative;
        }

        #photoInput {
            display: none;
        }

        .photo-btn {
            background: #f0f0f0;
            color: #636e72;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .photo-btn:hover {
            background: #e1e8ed;
            color: #2d3436;
        }

        .photo-btn:active {
            transform: scale(0.95);
        }

        .input-area input[type="text"] {
            flex: 1;
            padding: 11px 16px;
            border: 1.5px solid #dfe6e9;
            border-radius: 10px;
            outline: none;
            background: #f8f9fa;
            color: #2d3436;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .input-area input[type="text"]:focus {
            border-color: #0984e3;
            background: white;
            box-shadow: 0 0 0 3px rgba(9, 132, 227, 0.1);
        }

        .input-area button {
            background: linear-gradient(135deg, #0984e3 0%, #0770d1 100%);
            color: white;
            border: none;
            padding: 11px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .input-area button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(9, 132, 227, 0.3);
        }

        .input-area button:active {
            transform: translateY(0);
        }
        
        /* Message Actions */
        .actions { 
            font-size: 12px; 
            margin-top: 6px; 
            opacity: 0;
            display: flex;
            gap: 6px;
            transition: opacity 0.3s ease;
        }
        
        .msg-row:hover .actions {
            opacity: 0.8;
        }
        
        .actions span {
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .them .actions span {
            background: rgba(0, 0, 0, 0.1);
            color: #636e72;
        }
        
        .them .actions span:hover {
            background: rgba(0, 0, 0, 0.15);
        }
        
        .me .actions {
            color: rgba(255, 255, 255, 0.9);
            justify-content: flex-end;
        }
        
        .me .actions span {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .me .actions span:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        @media (max-width: 600px) {
            .msg {
                max-width: 85%;
            }
            
            .input-area {
                padding: 12px 16px;
                gap: 8px;
            }
            
            .input-area input,
            .input-area button {
                padding: 10px 14px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="header">
        <a href="/Chatterlink/users.php">←</a>
        <div>
            <div><?php echo htmlspecialchars($r['name']); ?></div>
            <div class="header-username">@<?php echo htmlspecialchars($r['username']); ?></div>
        </div>
    </div>

    <div class="messages" id="chatBox">
        <!-- Messages loaded via AJAX -->
    </div>

    <form class="input-area" id="chatForm">
        <div class="input-wrapper">
            <div class="file-input-wrapper">
                <input type="file" id="photoInput" accept="image/*">
                <button type="button" class="photo-btn" onclick="document.getElementById('photoInput').click();" title="Send photo">
                    📎
                </button>
            </div>
            <input type="text" id="msgInput" autocomplete="off" placeholder="Type a message...">
        </div>
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
            // Check if there are no messages
            if(d.trim() === ''){
                chatBox.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">💬</div>
                        <h2 class="empty-state-title">This could be the start of something great!</h2>
                        <p class="empty-state-message">Say hello and start your conversation</p>
                    </div>
                `;
            } else {
                chatBox.innerHTML = d;
                chatBox.scrollTop = chatBox.scrollHeight;
            }
            lastData = d;
        }
    })
    .catch(err => console.error("Error fetching messages:", err));
}

function sendMsg(e){
    if(e) e.preventDefault();
    
    let m = msgInput.value.trim();
    let photoFile = document.getElementById('photoInput').files[0];

    if(!m && !photoFile) return;

    // If there's a photo, upload it first
    if(photoFile) {
        let formData = new FormData();
        formData.append('receiver_id', receiverId);
        formData.append('photo', photoFile);
        if(m) formData.append('message', m);

        fetch("../actions/send_message.php", {
            method: "POST",
            body: formData
        }).then(r => r.json()).then(d => {
            if (d.success) {
                msgInput.value = "";
                document.getElementById('photoInput').value = "";
                loadChat();
            } else {
                let errorMsg = d.error || 'Error sending photo';
                if (d.debug) {
                    errorMsg += '\n\nDEBUG:\n' + JSON.stringify(d.debug, null, 2);
                }
                alert(errorMsg);
            }
        }).catch(err => {
            console.error('Error:', err);
            alert('Error sending photo: ' + err.message);
        });
    } else {
        // Send text-only message
        fetch("../actions/send_message.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `receiver_id=${receiverId}&message=${encodeURIComponent(m)}`
        }).then(r => r.json()).then(d => {
            if (d.success) {
                msgInput.value = "";
                loadChat();
            } else {
                alert(d.error || 'Error sending message');
            }
        });
    }
}

chatForm.addEventListener('submit', sendMsg);
setInterval(loadChat, 2000);
loadChat();

window.editMessage = function(id) {
    let newMsg = prompt("Edit your message:");
    if (!newMsg || newMsg.trim() === "") return;

    fetch("../actions/edit_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `id=${id}&message=${encodeURIComponent(newMsg)}`
    }).then(r => r.json()).then(d => {
        if (d.success) {
            loadChat();
        } else {
            alert(d.error || 'Error editing message');
        }
    });
};

window.deleteMessage = function(id) {
    if (!confirm("Are you sure you want to delete this message?")) return;

    fetch("../actions/delete_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `id=${id}`
    }).then(r => r.json()).then(d => {
        if (d.success) {
            loadChat();
        } else {
            alert(d.error || 'Error deleting message');
        }
    });
};
</script>

</body>
</html>