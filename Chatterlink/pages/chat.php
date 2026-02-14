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
        :root {
            --bg-dark: #0a0e27;
            --bg-secondary: #1a1f3a;
            --bg-tertiary: #252d47;
            --accent-cyan: #00d4ff;
            --accent-purple: #7c3aed;
            --text-primary: #ffffff;
            --text-secondary: #b0b8d4;
            --border-color: #2d3548;
        }

        * { 
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body, html { 
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: var(--bg-dark);
        }

        .header {
            padding: 1.25rem 1.5rem;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 600;
        }

        .header a {
            text-decoration: none;
            font-size: 1.5rem;
            color: var(--accent-cyan);
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .header a:hover {
            color: var(--accent-purple);
        }

        .header-title {
            flex: 1;
            font-size: 1.1rem;
            color: var(--text-primary);
        }

        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: var(--bg-dark);
        }

        .msg-row {
            display: flex;
            width: 100%;
            margin-bottom: 0.5rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .me-row {
            justify-content: flex-end;
        }

        .them-row {
            justify-content: flex-start;
        }

        .msg {
            max-width: 70%;
            padding: 0.875rem 1.25rem;
            border-radius: 12px;
            font-size: 0.95rem;
            line-height: 1.5;
            word-break: break-word;
        }

        .me {
            background: linear-gradient(135deg, var(--accent-cyan), #00a8cc);
            color: var(--bg-dark);
            border-bottom-right-radius: 4px;
            font-weight: 500;
        }

        .them {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-bottom-left-radius: 4px;
        }

        .msg img {
            max-width: 100%;
            border-radius: 8px;
            margin-top: 0.5rem;
        }

        .input-area {
            padding: 1.25rem 1.5rem;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .input-group {
            flex: 1;
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .input-area input {
            flex: 1;
            padding: 0.875rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            outline: none;
            font-family: inherit;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .input-area input:focus {
            border-color: var(--accent-cyan);
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.2);
        }

        .input-area input::placeholder {
            color: var(--text-secondary);
        }

        .input-area button {
            background: linear-gradient(135deg, var(--accent-cyan), #00a8cc);
            color: var(--bg-dark);
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .input-area button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 212, 255, 0.3);
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent-cyan);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-purple);
        }

        @media (max-width: 768px) {
            .msg {
                max-width: 85%;
            }

            .header {
                padding: 1rem;
            }

            .messages {
                padding: 1rem;
            }

            .input-area {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="header">
        <a href="/Chatterlink/Chatterlink/users.php">←</a>
        <div class="header-title"><?php echo htmlspecialchars($r['name']); ?></div>
    </div>

    <div class="messages" id="chatBox">
        <!-- Messages loaded via AJAX -->
    </div>

    <form class="input-area" id="chatForm">
        <div class="input-group">
            <input type="text" id="msgInput" autocomplete="off" placeholder="Type a message...">
            <button type="submit">Send</button>
        </div>
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