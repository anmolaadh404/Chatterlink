<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /Chatterlink/auth/login.php");
    exit;
}

include 'config/db.php';

$my_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get friend IDs
$friends_query = $conn->prepare("
    SELECT friend_id FROM friends 
    WHERE user_id = ? AND status = 'accepted'
    UNION
    SELECT user_id FROM friends 
    WHERE friend_id = ? AND status = 'accepted'
");
$friends_query->bind_param("ii", $my_id, $my_id);
$friends_query->execute();
$friends_result = $friends_query->get_result();
$friend_ids = [];
while ($row = $friends_result->fetch_assoc()) {
    $friend_ids[] = $row['friend_id'] ?? $row['user_id'];
}
$friends_query->close();

// Get pending friend requests
$pending_query = $conn->prepare("
    SELECT f.id, u.user_id, u.name, u.username, u.bio, u.avatar
    FROM friends f
    JOIN users u ON f.user_id = u.user_id
    WHERE f.friend_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$pending_query->bind_param("i", $my_id);
$pending_query->execute();
$pending_result = $pending_query->get_result();
$pending_requests = [];
while ($row = $pending_result->fetch_assoc()) {
    $pending_requests[] = $row;
}
$pending_query->close();

// Build query
$query = "SELECT user_id, name, username, bio, avatar FROM users WHERE user_id != ?";
$params = [$my_id];
$types = "i";

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY name ASC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<title>Users - Chatterlink</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
* {
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", sans-serif;
}

body {
    margin: 0;
    background: #f5f7fa;
    color: #2d3436;
}

.header {
    padding: 16px 24px;
    background: white;
    border-bottom: 1px solid #e1e8ed;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.header-title {
    font-weight: 700;
    font-size: 18px;
    color: #1a1a1a;
}

.header-right {
    display: flex;
    gap: 20px;
}

.header a {
    text-decoration: none;
    color: #0984e3;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.3s ease;
}

.header a:hover {
    color: #0770d1;
}

.search-box {
    padding: 20px 24px;
    background: white;
    border-bottom: 1px solid #e1e8ed;
}

.search-box form {
    display: flex;
    gap: 10px;
    max-width: 600px;
}

.search-box input {
    flex: 1;
    padding: 11px 16px;
    border: 1.5px solid #dfe6e9;
    border-radius: 10px;
    background: #f8f9fa;
    color: #2d3436;
    outline: none;
    font-size: 14px;
    transition: all 0.3s ease;
}

.search-box input:focus {
    border-color: #0984e3;
    background: white;
    box-shadow: 0 0 0 3px rgba(9, 132, 227, 0.1);
}

.search-box button {
    padding: 11px 24px;
    background: #0984e3;
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s ease;
}

.search-box button:hover {
    background: #0770d1;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(9, 132, 227, 0.3);
}

.users-section {
    max-width: 600px;
    margin: 0 auto;
    padding: 24px;
}

.section-title {
    font-weight: 700;
    padding: 16px 0 12px 0;
    color: #2d3436;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 8px;
}

.user {
    padding: 16px;
    margin-bottom: 10px;
    background: white;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid #e1e8ed;
    transition: all 0.3s ease;
}

.user:hover {
    border-color: #0984e3;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-weight: 600;
    color: #1a1a1a;
    text-decoration: none;
    display: block;
    margin-bottom: 4px;
    font-size: 15px;
    transition: color 0.3s ease;
}

.user-name:hover {
    color: #0984e3;
}

.user-username {
    font-size: 13px;
    color: #636e72;
    margin-bottom: 4px;
}

.user-bio {
    font-size: 13px;
    color: #636e72;
    margin-top: 4px;
    line-height: 1.4;
}

.user-actions {
    display: flex;
    gap: 8px;
    margin-left: 12px;
    flex-shrink: 0;
}

.btn {
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-chat {
    background: #0984e3;
    color: white;
}

.btn-chat:hover {
    background: #0770d1;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(9, 132, 227, 0.3);
}

.btn-friend {
    background: #e1e8ed;
    color: #636e72;
}

.btn-friend:hover {
    background: #dfe6e9;
}

.btn-friend.added {
    background: #26a644;
    color: white;
}

.btn-profile {
    background: #636e72;
    color: white;
}

.btn-profile:hover {
    background: #4b5358;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.no-results {
    padding: 60px 24px;
    text-align: center;
    color: #636e72;
}

.empty-state {
    padding: 60px 24px;
    text-align: center;
    color: #636e72;
}

@media (max-width: 600px) {
    .users-section {
        padding: 12px;
    }
    
    .user {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .user-actions {
        width: 100%;
        margin-left: 0;
    }
    
    .btn {
        flex: 1;
        text-align: center;
    }
}
</style>
</head>
<body>

<div class="header">
    <div class="header-title">
        <?php echo htmlspecialchars($_SESSION['name']); ?>
    </div>
    <div class="header-right">
        <a href="/Chatterlink/pages/profiles.php">My Profile</a>
        <a href="/Chatterlink/auth/logout.php">Logout</a>
    </div>
</div>

<div class="search-box">
    <form method="GET" style="display: flex;">
        <input type="text" name="search" placeholder="Search by username or name..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
    </form>
</div>

<div class="users-section">
    <!-- Pending Friend Requests Section -->
    <?php if (count($pending_requests) > 0): ?>
        <div class="section-title">🔔 Pending Friend Requests (<?php echo count($pending_requests); ?>)</div>
        <?php foreach ($pending_requests as $req): ?>
            <div class="user">
                <div class="user-info">
                    <a href="/Chatterlink/pages/profiles.php?user_id=<?php echo $req['user_id']; ?>" class="user-name">
                        <?php echo htmlspecialchars($req['name']); ?>
                    </a>
                    <div class="user-username">@<?php echo htmlspecialchars($req['username']); ?></div>
                    <?php if ($req['bio']): ?>
                        <div class="user-bio"><?php echo htmlspecialchars($req['bio']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-actions">
                    <button class="btn btn-chat" onclick="acceptRequest(<?php echo $req['id']; ?>, this)">✓ Accept</button>
                    <button class="btn btn-friend" onclick="rejectRequest(<?php echo $req['id']; ?>, this)">✕ Reject</button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Friends Section -->
    <?php if (count($friend_ids) > 0): ?>
        <div class="section-title">Friends</div>
        <?php 
        while($u = $result->fetch_assoc()):
            if (!in_array($u['user_id'], $friend_ids)) continue;
        ?>
            <div class="user">
                <div class="user-info">
                    <a href="/Chatterlink/pages/profiles.php?user_id=<?php echo $u['user_id']; ?>" class="user-name">
                        <?php echo htmlspecialchars($u['name']); ?>
                    </a>
                    <div class="user-username">@<?php echo htmlspecialchars($u['username']); ?></div>
                    <?php if ($u['bio']): ?>
                        <div class="user-bio"><?php echo htmlspecialchars($u['bio']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-actions">
                    <a href="/Chatterlink/pages/chat.php?user_id=<?php echo $u['user_id']; ?>" class="btn btn-chat">Chat</a>
                    <a href="/Chatterlink/pages/profiles.php?user_id=<?php echo $u['user_id']; ?>" class="btn btn-profile">View</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <div class="section-title">Other Users</div>
    <?php
    $result->data_seek(0);
    $found_other = false;
    while($u = $result->fetch_assoc()):
        if (in_array($u['user_id'], $friend_ids)) continue;
        $found_other = true;
    ?>
        <div class="user">
            <div class="user-info">
                <a href="/Chatterlink/pages/profiles.php?user_id=<?php echo $u['user_id']; ?>" class="user-name">
                    <?php echo htmlspecialchars($u['name']); ?>
                </a>
                <div class="user-username">@<?php echo htmlspecialchars($u['username']); ?></div>
                <?php if ($u['bio']): ?>
                    <div class="user-bio"><?php echo htmlspecialchars($u['bio']); ?></div>
                <?php endif; ?>
            </div>
            <div class="user-actions">
                <button class="btn btn-friend" onclick="addFriend(<?php echo $u['user_id']; ?>, this)">Add Friend</button>
                <a href="/Chatterlink/pages/profiles.php?user_id=<?php echo $u['user_id']; ?>" class="btn btn-profile">View</a>
            </div>
        </div>
    <?php endwhile; ?>
    
    <?php if (!$found_other): ?>
        <div class="empty-state">
            <?php echo !empty($search) ? 'No users found matching your search.' : 'No other users available.'; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function addFriend(userId, button) {
    if (button.textContent === 'Request Sent') return;
    
    fetch('/Chatterlink/actions/add_friend.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `friend_id=${userId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            button.textContent = 'Request Sent';
            button.disabled = true;
            button.style.opacity = '0.5';
        } else {
            alert(d.error || 'Error sending friend request');
        }
    })
    .catch(err => console.error(err));
}

function acceptRequest(requestId, button) {
    fetch('/Chatterlink/actions/handle_friend_request.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `request_id=${requestId}&action=accept`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            button.closest('.user').style.transition = 'opacity 0.3s';
            button.closest('.user').style.opacity = '0.5';
            setTimeout(() => location.reload(), 300);
        } else {
            alert(d.error || 'Error accepting request');
        }
    })
    .catch(err => console.error(err));
}

function rejectRequest(requestId, button) {
    if (!confirm('Reject this friend request?')) return;
    
    fetch('/Chatterlink/actions/handle_friend_request.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `request_id=${requestId}&action=reject`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            button.closest('.user').style.transition = 'opacity 0.3s';
            button.closest('.user').style.opacity = '0.5';
            setTimeout(() => location.reload(), 300);
        } else {
            alert(d.error || 'Error rejecting request');
        }
    })
    .catch(err => console.error(err));
}
</script>

</body>
</html>
<?php $stmt->close(); ?>
