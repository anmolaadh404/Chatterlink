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
        * { 
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", sans-serif;
        }
        
        body, html { 
            margin: 0;
            background: #f5f7fa;
            color: #2d3436;
        }
        
        .header {
            padding: 16px 24px;
            background: white;
            border-bottom: 1px solid #e1e8ed;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        
        .header a {
            font-size: 20px;
            color: #0984e3;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .header a:hover {
            color: #0770d1;
        }
        
        .profile-container {
            max-width: 500px;
            margin: 24px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Card-style profile (cover image + overlay) */
        .profile-card {
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .card-media {
            position: relative;
            width: 100%;
            height: 320px;
            background: #e9ecef;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        .card-image {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, #f0f0f0 0%, #dfe6e9 100%);
        }

        .card-overlay {
            position: relative;
            width: 100%;
            padding: 18px 20px;
            background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.6) 100%);
            color: #fff;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        .card-sub {
            font-size: 14px;
            color: rgba(255,255,255,0.85);
        }

        .card-joined {
            margin-top: 8px;
            font-size: 13px;
            color: rgba(255,255,255,0.85);
            align-self: flex-start;
            background: rgba(0,0,0,0.25);
            padding: 6px 10px;
            border-radius: 6px;
        }
        
        .profile-header {
            padding: 0;
            background: linear-gradient(135deg, #5865F2 0%, #4752C4 100%);
            height: 120px;
            position: relative;
        }
        
        .profile-header-content {
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 20px 24px 24px;
        }
        
        .avatar-container {
            position: absolute;
            top: 60px;
            left: 24px;
        }
        
        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            background: #e1e8ed;
        }
        
        .avatar-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5865F2 0%, #4752C4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            border: 5px solid white;
        }
        
        .profile-body {
            padding: 60px 24px 28px;
        }
        
        .profile-info {
            margin-bottom: 28px;
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: #2d3436;
        }
        
        .profile-username {
            font-size: 14px;
            color: #636e72;
            margin: 0 0 12px 0;
        }
        
        .member-since {
            font-size: 13px;
            color: #636e72;
            margin: 0;
            padding: 8px 0;
        }
        
        .profile-section {
            margin-bottom: 28px;
        }
        
        .section-title {
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 12px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #636e72;
        }
        
        .section-content {
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #2d3436;
            line-height: 1.6;
            border: 1px solid #e1e8ed;
            font-size: 14px;
            word-break: break-word;
        }
        
        .edit-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .edit-form textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            resize: vertical;
            min-height: 100px;
            font-size: 14px;
            color: #2d3436;
            background: white;
            transition: all 0.3s ease;
        }
        
        .edit-form textarea:focus {
            border-color: #5865F2;
            outline: none;
            box-shadow: 0 0 0 2px rgba(88, 101, 242, 0.1);
        }
        
        .edit-form input[type="file"] {
            display: block;
            padding: 8px 0;
            font-size: 13px;
            color: #2d3436;
        }
        
        .edit-form input[type="file"]::file-selector-button {
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            padding: 8px 16px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            color: #2d3436;
            transition: all 0.2s ease;
        }
        
        .edit-form input[type="file"]::file-selector-button:hover {
            background: #f9f9f9;
            border-color: #5865F2;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }
        
        .btn {
            flex: 1;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #5865F2;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4752C4;
        }
        
        .btn-secondary {
            background: #e1e8ed;
            color: #2d3436;
            border: 1px solid #dfe6e9;
        }
        
        .btn-secondary:hover {
            background: #dfe6e9;
        }
        
        .btn-danger {
            background: #ED4245;
            color: white;
        }
        
        .btn-danger:hover {
            background: #DA373C;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            border: 1px solid #c3e6cb;
            font-size: 13px;
        }
        
        .error-message {
            background: #fff5f6;
            color: #d63031;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            border: 1px solid #f5a1a5;
            font-size: 13px;
        }
        
        @media (max-width: 600px) {
            .profile-container {
                margin: 12px;
                border-radius: 12px;
            }
            
            .profile-header {
                height: 100px;
            }
            
            .profile-body {
                padding: 60px 16px 20px;
            }
            
            .avatar {
                width: 80px;
                height: 80px;
                border-width: 4px;
            }
            
            .avatar-placeholder {
                width: 80px;
                height: 80px;
                font-size: 40px;
                border-width: 4px;
            }
            
            .avatar-container {
                top: 50px;
                left: 16px;
            }
            
            .profile-name {
                font-size: 20px;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <a href="/Chatterlink/users.php">←</a>
    <span>Profile</span>
</div>

<div class="profile-container">
    <div class="profile-card">
        <div class="card-media">
            <?php if ($user['avatar']): ?>
                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Cover" class="card-image">
            <?php else: ?>
                <div class="card-image-placeholder"></div>
            <?php endif; ?>
            <div class="card-overlay">
                <div>
                    <h2 class="card-title"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <div class="card-sub">@<?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                <div class="card-joined">Joined at <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
            </div>
        </div>
    </div>

    <div class="profile-body">
        <?php if (!empty($success_msg)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- About Section -->
        <div class="profile-section">
            <?php if ($user['bio'] || $is_own_profile): ?>
                <div class="section-title">About Me</div>
                <?php if ($is_own_profile): ?>
                    <form method="POST" class="edit-form">
                        <input type="hidden" name="action" value="update_bio">
                        <textarea name="bio" placeholder="Tell people about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        <button type="submit" class="btn btn-primary">Save Bio</button>
                    </form>
                <?php else: ?>
                    <div class="section-content">
                        <?php if ($user['bio']): ?>
                            <?php echo htmlspecialchars($user['bio']); ?>
                        <?php else: ?>
                            <p style="color: #999; margin: 0;">No bio added yet</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Avatar Upload Section (Own Profile Only) -->
        <?php if ($is_own_profile): ?>
            <div class="profile-section">
                <div class="section-title">Profile Picture</div>
                <form method="POST" enctype="multipart/form-data" class="edit-form">
                    <input type="hidden" name="action" value="upload_avatar">
                    <input type="file" name="avatar" accept="image/*" required>
                    <button type="submit" class="btn btn-primary">Change Photo</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <?php if (!$is_own_profile): ?>
            <div class="btn-group">
                <?php if ($friend_status === 'accepted'): ?>
                    <a href="/Chatterlink/pages/chat.php?user_id=<?php echo $view_user_id; ?>" class="btn btn-primary">Message</a>
                    <button onclick="removeF(<?php echo $view_user_id; ?>)" class="btn btn-danger">Remove</button>
                <?php elseif ($friend_status === 'pending'): ?>
                    <button disabled class="btn btn-secondary">Request Pending</button>
                <?php else: ?>
                    <button onclick="addF(<?php echo $view_user_id; ?>)" class="btn btn-primary">Follow</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="btn-group">
                <a href="/Chatterlink/users.php" class="btn btn-secondary">Back to Users</a>
            </div>
        <?php endif; ?>
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
