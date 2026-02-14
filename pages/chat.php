<?php
session_start();
// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
include '../config/db.php';

$me = $_SESSION['user_id'];
// Use filter_input to sanitize the GET variable to prevent SQL Injection
$receiver = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$receiver) {
    header("Location: ../users.php");
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
    die("You are not friends with this user. <a href='../users.php'>Back</a>");
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
            height: 100vh; 
            margin: 0;
            padding: 0;
            background: #111;
            overflow: hidden;
        }
        
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            background: #2d2d2d;
        }

        .header {
            padding: 12px 16px;
            background: #3d3838a4;
            border-bottom: 1px solid #404040;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
            min-height: 60px;
        }

        .header a {
            text-decoration: none;
            font-size: 20px;
            color: #5865F2;
            transition: color 0.3s ease;
            padding: 4px 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .header a:hover {
            color: #4752C4;
        }
        
        .header-name {
            font-weight: 700;
            font-size: 16px;
            color: #fdf7f7ff;
        }
        
        .header-username {
            font-size: 13px;
            color: #0f0e0eff;
            font-weight: 400;
            margin-top: 2px;
        }

        .messages {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background-color: #ffffff;
            position: relative;
            min-height: 0;
        }
        
        .messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .messages::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .messages::-webkit-scrollbar-thumb {
            background: #d0d7de;
            border-radius: 3px;
        }
        
        .messages::-webkit-scrollbar-thumb:hover {
            background: #b4bcc3;
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
            color: #e0e0e0;
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
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 14px;
            position: relative;
            line-height: 1.5;
            word-wrap: break-word;
            overflow-wrap: break-word;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        .msg img {
            max-width: 100%;
            max-height: 300px;
           
        }

        .me { 
            background: linear-gradient(135deg, #4f455393 0%, #141313ff 100%);
            color: white;
        }
        
        .them { 
            background: #a77d23ff;
            color: #0a0808ff;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .header {
                padding: 10px 12px;
                min-height: 54px;
            }
            
            .header-name {
                font-size: 15px;
            }
            
            .header-username {
                font-size: 12px;
            }
            
            .messages {
                padding: 12px;
                gap: 6px;
            }
            
            .msg {
                max-width: 80%;
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .input-area {
                padding: 10px;
                min-height: 54px;
                gap: 6px;
            }
            
            .input-area input[type="text"],
            .input-area button {
                padding: 9px 12px;
                font-size: 13px;
            }
            
            .photo-btn {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .header {
                padding: 8px 10px;
                min-height: 50px;
                gap: 8px;
            }
            
            .header a {
                font-size: 18px;
                padding: 4px 6px;
            }
            
            .header-name {
                font-size: 14px;
            }
            
            .header-username {
                font-size: 11px;
            }
            
            .messages {
                padding: 10px;
                gap: 4px;
            }
            
            .msg {
                max-width: 85%;
                padding: 8px 11px;
                font-size: 12px;
            }
            
            .msg img {
                max-height: 250px;
            }
            
            .empty-state-title {
                font-size: 18px;
            }
            
            .empty-state-message {
                font-size: 13px;
            }
            
            .input-area {
                padding: 8px;
                min-height: 50px;
                gap: 4px;
            }
            
            .input-area input[type="text"],
            .input-area button {
                padding: 8px 10px;
                font-size: 12px;
            }
            
            .photo-btn {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .input-area button {
                padding: 8px 12px;
            }
            
            .actions {
                font-size: 11px;
            }
        }
        
        /* Large screens */
        @media (min-width: 1024px) {
            .msg {
                max-width: 65%;
            }
            
            .header {
                padding: 14px 20px;
            }
            
            .messages {
                padding: 18px 20px;
            }
            
            .input-area {
                padding: 14px 20px;
            }
        }

        .input-area {
            padding: 12px;
            background: #2d2d2d;
            border-top: 1px solid #404040;
            display: flex;
            gap: 8px;
            align-items: flex-end;
            flex-shrink: 0;
            min-height: 60px;
            box-sizing: border-box;
        }

        .input-wrapper {
            flex: 1;
            display: flex;
            gap: 6px;
            align-items: center;
            min-width: 0;
        }

        .file-input-wrapper {
            position: relative;
            flex-shrink: 0;
        }

        #photoInput {
            display: none;
        }

        .photo-btn {
            background: #f0f0f0;
            color: #636e72;
            border: none;
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex-shrink: 0;
        }

        .photo-btn:hover {
            background: #404040;
            color: #e0e0e0;
        }

        .photo-btn:active {
            transform: scale(0.95);
        }

        .input-area input[type="text"] {
            flex: 1;
            padding: 10px 14px;
            border: 1.5px solid #dfe6e9;
            border-radius: 10px;
            outline: none;
            background: #f8f9fa;
            color: #e0e0e0;
            font-size: 14px;
            transition: all 0.3s ease;
            min-width: 0;
        }
        
        .input-area input[type="text"]:focus {
            border-color: #c9cbdfff;
            background: #2d2d2d;
            box-shadow: 0 0 0 3px rgba(9, 132, 227, 0.1);
        }
        
        .input-area input[type="text"]::placeholder {
            color: #b2bec3;
        }

        .input-area button {
            background: linear-gradient(135deg, #5865F2 0%, #4752C4 100%);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .input-area button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(129, 198, 250, 0.3);
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
            background: rgba(219, 219, 219, 0.5);
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .header {
                padding: 10px 12px;
                min-height: 54px;
            }
            
            .header-name {
                font-size: 15px;
            }
            
            .header-username {
                font-size: 12px;
            }
            
            .messages {
                padding: 12px;
                gap: 6px;
            }
            
            .msg {
                max-width: 80%;
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .input-area {
                padding: 10px;
                min-height: 54px;
                gap: 6px;
            }
            
            .input-area input[type="text"],
            .input-area button {
                padding: 9px 12px;
                font-size: 13px;
            }
            
            .photo-btn {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .header {
                padding: 8px 10px;
                min-height: 50px;
                gap: 8px;
            }
            
            .header a {
                font-size: 18px;
                padding: 4px 6px;
            }
            
            .header-name {
                font-size: 14px;
            }
            
            .header-username {
                font-size: 11px;
            }
            
            .messages {
                padding: 10px;
                gap: 4px;
            }
            
            .msg {
                max-width: 85%;
                padding: 8px 11px;
                font-size: 12px;
            }
            
            .msg img {
                max-height: 250px;
            }
            
            .empty-state-title {
                font-size: 18px;
            }
            
            .empty-state-message {
                font-size: 13px;
            }
            
            .input-area {
                padding: 8px;
                min-height: 50px;
                gap: 4px;
            }
            
            .input-area input[type="text"],
            .input-area button {
                padding: 8px 10px;
                font-size: 12px;
            }
            
            .photo-btn {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .input-area button {
                padding: 8px 12px;
            }
            
            .actions {
                font-size: 11px;
            }
        }
        
        /* Large screens */
        @media (min-width: 1024px) {
            .msg {
                max-width: 65%;
            }
            
            .header {
                padding: 14px 20px;
            }
            
            .messages {
                padding: 18px 20px;
            }
            
            .input-area {
                padding: 14px 20px;
            }
        }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="header">
        <a href="../users.php">←</a>
        <div>
            <a href="./profiles.php?user_id=<?php echo $receiver; ?>" style="text-decoration:none;color:inherit;">
                <div><?php echo htmlspecialchars($r['name']); ?></div>
                <div class="header-username">@<?php echo htmlspecialchars($r['username']); ?></div>
            </a>
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

    // REMOVED THE CHECK - Allow photo-only messages
    // if(!m && !photoFile) return;

    // Allow photo without text OR text without photo OR both
    if(!m && !photoFile) {
        return; // Only return if BOTH are empty
    }

    // Disable send button to prevent double-sending
    const sendBtn = document.querySelector('#chatForm button[type="submit"]');
    if(sendBtn) sendBtn.disabled = true;

    // If there's a photo, upload it
    if(photoFile) {
        let formData = new FormData();
        formData.append('receiver_id', receiverId);
        formData.append('photo', photoFile);
        // Only add message if it exists
        if(m) {
            formData.append('message', m);
        }

        fetch("../actions/send_message.php", {
            method: "POST",
            body: formData
        })
        .then(r => {
            if (!r.ok) {
                return r.json().then(err => Promise.reject(err));
            }
            return r.json();
        })
        .then(d => {
            if (d.success) {
                msgInput.value = "";
                document.getElementById('photoInput').value = "";
                loadChat();
                if(sendBtn) sendBtn.disabled = false;
            } else {
                alert(d.error || 'Error sending photo');
                if(sendBtn) sendBtn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error: ' + (err.error || err.message || 'Failed to send'));
            if(sendBtn) sendBtn.disabled = false;
        });
    } else {
        // Send text-only message (no photo)
        fetch("../actions/send_message.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `receiver_id=${receiverId}&message=${encodeURIComponent(m)}`
        })
        .then(r => {
            if (!r.ok) {
                return r.json().then(err => Promise.reject(err));
            }
            return r.json();
        })
        .then(d => {
            if (d.success) {
                msgInput.value = "";
                loadChat();
                if(sendBtn) sendBtn.disabled = false;
            } else {
                alert(d.error || 'Error sending message');
                if(sendBtn) sendBtn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error: ' + (err.error || err.message || 'Failed to send'));
            if(sendBtn) sendBtn.disabled = false;
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

window.editMessage = function(id) {
    let newMsg = prompt("Edit your message:");
    if (!newMsg || newMsg.trim() === "") return;

    fetch("../actions/edit_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `message_id=${id}&new_message=${encodeURIComponent(newMsg.trim())}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            loadChat();
        } else {
            alert(d.error || 'Error editing message');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to edit message');
    });
}

window.deleteMessage = function(id) {
    if (!confirm('Delete this message?')) return;

    fetch("../actions/delete_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `message_id=${id}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            loadChat();
        } else {
            alert(d.error || 'Error deleting message');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to delete message');
    });
}
</script>

</body>
</html>