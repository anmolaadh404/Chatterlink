<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /Chatterlink/Chatterlink/auth/login.php");
    exit;
}

include '../config/db.php';

$my_id = $_SESSION['user_id'];
$view_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$view_user_id) {
    $view_user_id = $my_id;
}

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

$friend_status = null;
if (!$is_own_profile) {
    $stmt = $conn->prepare("SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?) LIMIT 1");
    $stmt->bind_param("iiii", $my_id, $view_user_id, $view_user_id, $my_id);
    $stmt->execute();
    $friend_result = $stmt->get_result();
    if ($friend_result->num_rows > 0) {
        $friend_row = $friend_result->fetch_assoc();
        $friend_status = $friend_row['status'];
    }
    $stmt->close();
}

// Handle profile updates
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
                $success_msg = "Bio updated successfully!";
            }
            $stmt->close();
        }
    } elseif ($action === 'upload_avatar') {
        if (!isset($_FILES['avatar'])) {
            $error = "No file selected";
        } elseif ($_FILES['avatar']['error'] !== 0) {
            $error = "File upload error";
        } else {
            $file = $_FILES['avatar'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024;

            if (!in_array($file['type'], $allowed)) {
                $error = "Only JPEG, PNG, and GIF images are allowed";
            } elseif ($file['size'] > $max_size) {
                $error = "File size must not exceed 5MB";
            } else {
                $upload_dir = __DIR__ . '/../assets/uploads/profile/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $filename = $my_id . '_' . time() . '_' . basename($file['name']);
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    if ($user['avatar'] && file_exists($user['avatar'])) {
                        unlink($user['avatar']);
                    }
                    
                    $relative_path = '/Chatterlink/Chatterlink/assets/uploads/profile/' . $filename;
                    
                    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $relative_path, $my_id);
                    if ($stmt->execute()) {
                        $user['avatar'] = $relative_path;
                        $success_msg = "Avatar updated successfully!";
                    }
                    $stmt->close();
                } else {
                    $error = "Failed to upload file. Check directory permissions.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name']); ?> - Chatterlink</title>
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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--bg-dark) 0%, #0f1535 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
            min-height: 100vh;
        }

        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header a {
            color: var(--accent-cyan);
            text-decoration: none;
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }

        .header a:hover {
            color: var(--accent-purple);
        }

        .container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .profile-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .profile-header {
            height: 120px;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(124, 58, 237, 0.1));
            position: relative;
        }

        .profile-body {
            padding: 2rem;
            text-align: center;
        }

        .avatar-section {
            margin-top: -60px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: center;
        }

        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--bg-secondary);
            background: #333;
        }

        .avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            border: 4px solid var(--bg-secondary);
        }

        .profile-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-username {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        .profile-joined {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .profile-bio {
            background: var(--bg-tertiary);
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            min-height: 60px;
            line-height: 1.6;
            color: var(--text-secondary);
        }

        .profile-bio.empty {
            font-style: italic;
            color: #666;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-cyan), #00a8cc);
            color: var(--bg-dark);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 212, 255, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--accent-cyan);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background: rgba(0, 212, 255, 0.1);
            border-color: var(--accent-cyan);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .edit-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        textarea {
            width: 100%;
            padding: 0.875rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 10px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            transition: all 0.3s ease;
        }

        textarea:focus {
            outline: none;
            border-color: var(--accent-cyan);
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.2);
        }

        input[type="file"] {
            color: var(--text-secondary);
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 480px) {
            .profile-name {
                font-size: 1.5rem;
            }

            .avatar, .avatar-placeholder {
                width: 100px;
                height: 100px;
                margin-top: -50px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="/Chatterlink/Chatterlink/users.php">←</a>
        <span>Profile</span>
    </div>

    <div class="container">
        <div class="profile-card">
            <div class="profile-header"></div>

            <div class="profile-body">
                <?php if (!empty($success_msg)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="avatar-section">
                    <?php if ($user['avatar']): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="avatar">
                    <?php else: ?>
                        <div class="avatar-placeholder"><?php echo strtoupper(substr($user['name'] ?? $user['username'], 0, 1)); ?></div>
                    <?php endif; ?>
                </div>

                <h1 class="profile-name"><?php echo htmlspecialchars($user['name'] ?: $user['username']); ?></h1>
                <div class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                <div class="profile-joined">Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>

                <div class="profile-bio <?php echo empty($user['bio']) ? 'empty' : ''; ?>">
                    <?php echo !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : 'No bio added yet'; ?>
                </div>

                <div class="action-buttons">
                    <?php if ($is_own_profile): ?>
                        <a href="/Chatterlink/Chatterlink/users.php" class="btn btn-outline">Back to Users</a>
                        <a href="/Chatterlink/Chatterlink/pages/chat.php" class="btn btn-primary">My Chats</a>
                    <?php else: ?>
                        <?php if ($friend_status === 'accepted'): ?>
                            <a href="/Chatterlink/Chatterlink/pages/chat.php?user_id=<?php echo $view_user_id; ?>" class="btn btn-primary">Message</a>
                            <button onclick="removeFriend(<?php echo $view_user_id; ?>)" class="btn btn-danger">Remove</button>
                        <?php elseif ($friend_status === 'pending'): ?>
                            <button disabled class="btn btn-outline">Request Pending</button>
                        <?php else: ?>
                            <button onclick="addFriend(<?php echo $view_user_id; ?>)" class="btn btn-primary">Follow</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($is_own_profile): ?>
                    <div class="edit-section">
                        <h3 style="margin-bottom: 1.5rem;">Edit Profile</h3>

                        <form method="POST" class="form-group">
                            <input type="hidden" name="action" value="update_bio">
                            <label for="bio">Bio (max 500 characters)</label>
                            <textarea name="bio" id="bio" placeholder="Tell people about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Save Bio</button>
                        </form>

                        <form method="POST" enctype="multipart/form-data" class="form-group">
                            <input type="hidden" name="action" value="upload_avatar">
                            <label for="avatar">Profile Picture</label>
                            <input type="file" name="avatar" id="avatar" accept="image/*" required>
                            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Change Photo</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function addFriend(userId) {
            fetch('/Chatterlink/Chatterlink/actions/add_friend.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `friend_id=${userId}`
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) location.reload();
                else alert(d.error || 'Error');
            });
        }

        function removeFriend(userId) {
            if (!confirm('Remove this friend?')) return;
            fetch('/Chatterlink/Chatterlink/actions/remove_friend.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `friend_id=${userId}`
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) location.reload();
                else alert(d.error || 'Error');
            });
        }
    </script>
</body>
</html>
