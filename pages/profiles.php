<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /Chatterlink/auth/login.php");
    exit;
}

include '../config/db.php';

$my_id = $_SESSION['user_id'];
$view_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

// If no user specified, show own profile
if (!$view_user_id) {
    $view_user_id = $my_id;
}

// Get user info
$stmt = $conn->prepare("SELECT user_id, name, username, bio, avatar, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found");
}

$is_own_profile = ($view_user_id === $my_id);

// Check friendship status if viewing another profile
$friend_status = null;
if (!$is_own_profile) {
    $stmt = $conn->prepare("
        SELECT status, user_id FROM friends 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
        LIMIT 1
    ");
    $stmt->bind_param("iiii", $my_id, $view_user_id, $view_user_id, $my_id);
    $stmt->execute();
    $friend_result = $stmt->get_result();
    if ($friend_result->num_rows > 0) {
        $friend_row = $friend_result->fetch_assoc();
        $friend_status = $friend_row['status'];
    }
    $stmt->close();
}

// Handle profile update
$success_msg = '';
$error = '';
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_bio') {
        $bio = trim($_POST['bio'] ?? '');
        if (strlen($bio) > 500) {
            $error = "Bio must be less than 500 characters";
        } else {
            $stmt = $conn->prepare("UPDATE users SET bio = ? WHERE user_id = ?");
            $stmt->bind_param("si", $bio, $my_id);
            if ($stmt->execute()) {
                $user['bio'] = $bio;
                $success_msg = "Profile updated successfully!";
            }
            $stmt->close();
        }
    } elseif ($action === 'upload_avatar') {
        if (!isset($_FILES['avatar'])) {
            $error = "No file selected";
        } elseif ($_FILES['avatar']['error'] !== 0) {
            $error = "File upload error: " . $_FILES['avatar']['error'];
        } else {
            $file = $_FILES['avatar'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed)) {
                $error = "Only JPEG, PNG, and GIF images are allowed. Got: " . $file['type'];
            } elseif ($file['size'] > $max_size) {
                $error = "File size must not exceed 5MB";
            } else {
                $upload_dir = __DIR__ . '/../assets/uploads/profile/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error = "Failed to create upload directory";
                    }
                }
                
                if (!$error) {
                    $filename = $my_id . '_' . time() . '_' . basename($file['name']);
                    $filepath = $upload_dir . $filename;
                    
                    // Try to move the uploaded file
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Delete old avatar if exists
                        if ($user['avatar'] && file_exists($user['avatar'])) {
                            unlink($user['avatar']);
                        }
                        
                        // Store relative path for web access
                        $relative_path = '/Chatterlink/assets/uploads/profile/' . $filename;
                        
                        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
                        if (!$stmt) {
                            $error = "Database error: " . $conn->error;
                        } else {
                            $stmt->bind_param("si", $relative_path, $my_id);
                            if ($stmt->execute()) {
                                $user['avatar'] = $relative_path;
                                $success_msg = "Avatar updated successfully!";
                            } else {
                                $error = "Failed to save avatar to database";
                            }
                            $stmt->close();
                        }
                    } else {
                        $error = "Failed to upload file. Check directory permissions.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($user['name']); ?> - Chatterlink</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Minimal, focused CSS for profile card */
        * { box-sizing: border-box; }
        body, html { margin:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background:#f5f7fa; color:#222; }

        .header { padding:12px 16px; background:#fff; border-bottom:1px solid #eceef0; display:flex; gap:12px; align-items:center; }
        .header a { color:#0984e3; text-decoration:none; font-weight:600; }

        .profile-container { max-width:520px; margin:22px auto; padding:0; }

        .minimal-card { background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 6px 18px rgba(20,20,20,0.08); }

        .card-cover { height:110px; background:linear-gradient(90deg,#eef2ff,#f8fafc); display:block; }
        .cover-img { width:100%; height:110px; object-fit:cover; display:block; }

        .card-body { padding:18px; text-align:center; position:relative; }
        .avatar-wrap { position: relative; margin-top:-48px; }
        .avatar { width:96px; height:96px; border-radius:50%; object-fit:cover; border:4px solid #fff; background:#eee; display:inline-block; }
        .avatar-placeholder { width:96px; height:96px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:#6c7ae0; color:#fff; font-size:36px; border:4px solid #fff; }

        .profile-name { margin:10px 0 4px; font-size:20px; font-weight:700; color:#111; }
        .profile-username { font-size:13px; color:#666; margin-bottom:6px; }
        .profile-joined { font-size:12px; color:#888; margin-bottom:12px; }

        .profile-bio { font-size:14px; color:#333; line-height:1.4; margin:0 0 12px; min-height:36px; }
        .muted { color:#999; }

        .card-actions { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
        .btn { padding:8px 14px; border-radius:8px; border:1px solid transparent; cursor:pointer; text-decoration:none; display:inline-block; font-weight:600; font-size:14px; }
        .btn-primary { background:#5865F2; color:#fff; }
        .btn-outline { background:#fff; color:#5865F2; border-color:#dfe6f8; }
        .btn-disabled { background:#f1f3f5; color:#888; border-color:#eceef0; }

        @media (max-width:480px){
            .card-body { padding:14px; }
            .avatar, .avatar-placeholder { width:80px; height:80px; margin-top:-40px; }
            .profile-name { font-size:18px; }
        }
    </style>
</head>
<body>

<div class="header">
    <a href="/Chatterlink/users.php">←</a>
    <span>Profile</span>
</div>

<div class="profile-container">
    <div class="minimal-card">
        <div class="card-cover">
            <?php if (!empty($user['cover']) && $user['cover'] !== ''): ?>
                <img src="<?php echo htmlspecialchars($user['cover']); ?>" alt="Cover" class="cover-img">
            <?php endif; ?>
        </div>

        <div class="card-body">
            <div class="avatar-wrap">
                <?php if ($user['avatar']): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="avatar">
                <?php else: ?>
                    <div class="avatar-placeholder"><?php echo strtoupper(substr($user['name'] ?? $user['username'],0,1)); ?></div>
                <?php endif; ?>
            </div>

            <h2 class="profile-name"><?php echo htmlspecialchars($user['name'] ?: $user['username']); ?></h2>
            <div class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></div>
            <div class="profile-joined">Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>

            <p class="profile-bio">
                <?php if (!empty($user['bio'])): ?>
                    <?php echo nl2br(htmlspecialchars($user['bio'])); ?>
                <?php else: ?>
                    <span class="muted">No bio yet</span>
                <?php endif; ?>
            </p>

            <div class="card-actions">
                <?php if ($is_own_profile): ?>
                    <a href="/Chatterlink/users.php" class="btn btn-outline">Back</a>
                    <a href="/Chatterlink/pages/chat.php" class="btn btn-primary">My Chats</a>
                <?php else: ?>
                    <?php if ($friend_status === 'accepted'): ?>
                        <a href="/Chatterlink/pages/chat.php?user_id=<?php echo $view_user_id; ?>" class="btn btn-primary">Message</a>
                        <button onclick="removeF(<?php echo $view_user_id; ?>)" class="btn btn-outline">Remove</button>
                    <?php elseif ($friend_status === 'pending'): ?>
                        <button disabled class="btn btn-disabled">Request Pending</button>
                    <?php else: ?>
                        <button onclick="addF(<?php echo $view_user_id; ?>)" class="btn btn-primary">Follow</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function addF(userId) {
    fetch('/Chatterlink/actions/add_friend.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `friend_id=${userId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            location.reload();
        } else {
            alert(d.error || 'Error');
        }
    });
}

function removeF(userId) {
    if (!confirm('Remove this friend?')) return;
    fetch('/Chatterlink/actions/remove_friend.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `friend_id=${userId}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            location.reload();
        } else {
            alert(d.error || 'Error');
        }
    });
}
</script>

</body>
</html>
