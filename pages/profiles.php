<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
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

/* Base Styles */
* { 
    box-sizing: border-box; 
}

body, html { 
    margin: 0; 
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
    background: #f5f7fa; 
    color: #2d3436; 
}

/* Header */
.header { 
    padding: 12px 16px; 
    background: #fff; 
    border-bottom: 1px solid #e1e8ed; 
    display: flex; 
    gap: 12px; 
    align-items: center; 
}

.header a { 
    color: #5865F2; 
    text-decoration: none; 
    font-weight: 600; 
}

.header a:hover {
    color: #4752C4;
}

.header span { 
    color: #2d3436; 
}

/* Profile Container */
.profile-container { 
    max-width: 520px; 
    margin: 22px auto; 
    padding: 0 15px; 
}

/* Profile Card */
.minimal-card { 
    background: #fff; 
    border-radius: 10px; 
    overflow: hidden; 
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); 
}

/* Cover Photo */
.card-cover { 
    height: 110px; 
    background: linear-gradient(135deg, #1e1e20ff 0%, #5f14aaa2 100%); 
    display: block; 
}

.cover-img { 
    width: 100%; 
    height: 110px; 
    object-fit: cover; 
    display: block; 
}

/* Card Body */
.card-body { 
    padding: 18px; 
    text-align: center; 
    position: relative; 
    background: #fff; 
}

/* Avatar */
.avatar-wrap { 
    position: relative; 
    margin-top: -48px; 
}

.avatar { 
    width: 96px; 
    height: 96px; 
    border-radius: 50%; 
    object-fit: cover; 
    border: 4px solid #fff; 
    background: #e1e8ed; 
    display: inline-block; 
}

.avatar-placeholder { 
    width: 96px; 
    height: 96px; 
    border-radius: 50%; 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    background: #5865F2; 
    color: #fff; 
    font-size: 36px; 
    border: 4px solid #fff; 
    font-weight: bold; 
}

/* Profile Info */
.profile-name { 
    margin: 10px 0 4px; 
    font-size: 20px; 
    font-weight: 700; 
    color: #1a1a1a; 
}

.profile-username { 
    font-size: 13px; 
    color: #636e72; 
    margin-bottom: 6px; 
}

.profile-joined { 
    font-size: 12px; 
    color: #999; 
    margin-bottom: 12px; 
}

.profile-bio { 
    font-size: 14px; 
    color: #2d3436; 
    line-height: 1.4; 
    margin: 0 0 12px; 
    min-height: 36px; 
}

.muted { 
    color: #999; 
    font-style: italic; 
}

/* Action Buttons */
.card-actions { 
    display: flex; 
    gap: 8px; 
    justify-content: center; 
    flex-wrap: wrap; 
    margin-top: 15px; 
}

.btn { 
    padding: 10px 18px; 
    border-radius: 8px; 
    border: 1px solid transparent; 
    cursor: pointer; 
    text-decoration: none; 
    display: inline-block; 
    font-weight: 600; 
    font-size: 14px; 
    transition: all 0.3s ease; 
}

.btn-primary { 
    background: #5865F2; 
    color: #fff; 
    border: none; 
}

.btn-primary:hover { 
    background: #4752C4; 
    transform: translateY(-2px); 
    box-shadow: 0 4px 12px rgba(88, 101, 242, 0.3); 
}

.btn-outline { 
    background: transparent; 
    color: #5865F2; 
    border: 2px solid #5865F2; 
}

.btn-outline:hover { 
    background: rgba(88, 101, 242, 0.1); 
}

.btn-disabled { 
    background: #e1e8ed; 
    color: #999; 
    border: 1px solid #dfe6e9; 
    cursor: not-allowed; 
}

/* Edit Modal Styles */
#editModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

#editModal > div {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

#editModal h3 {
    margin-top: 0;
    color: #2d3436;
}

#editModal label {
    display: block;
    margin-bottom: 8px;
    color: #636e72;
    font-size: 14px;
    font-weight: 500;
}

#editModal input,
#editModal textarea {
    width: 100%;
    padding: 12px;
    background: #f8f9fa;
    border: 1px solid #e1e8ed;
    border-radius: 8px;
    color: #2d3436;
    font-size: 15px;
    font-family: inherit;
}

#editModal input:focus,
#editModal textarea:focus {
    outline: none;
    border-color: #5865F2;
    box-shadow: 0 0 0 3px rgba(88, 101, 242, 0.1);
}

#editModal small {
    color: #999;
    font-size: 12px;
}

#editMessage {
    margin-top: 15px;
    padding: 10px;
    border-radius: 6px;
    display: none;
    font-size: 14px;
}

/* Responsive Design */
@media (max-width: 480px) {
    .card-body { 
        padding: 14px; 
    }
    
    .avatar, 
    .avatar-placeholder { 
        width: 80px; 
        height: 80px; 
        margin-top: -40px; 
    }
    
    .profile-name { 
        font-size: 18px; 
    }
    
    .btn {
        padding: 8px 14px;
        font-size: 13px;
    }
}
</style>
</head>
<body>

<div class="header">
    <a href="../users.php">←</a>
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
                    <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="avatar">
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
                    <a href="../users.php" class="btn btn-outline">Back</a>
                    <button onclick="showEditModal()" class="btn btn-primary">Edit Profile</button>
                <?php else: ?>
                    <?php if ($friend_status === 'accepted'): ?>
                        <a href="./chat.php?user_id=<?php echo $view_user_id; ?>" class="btn btn-primary">Message</a>
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

<!-- Edit Profile Modal -->
<?php if ($is_own_profile): ?>
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#2d2d2d; border-radius:12px; padding:30px; max-width:500px; width:90%;">
        <h3 style="margin-top:0; color:#e0e0e0;">Edit Profile</h3>
        
        <div style="margin-bottom:20px;">
            <label style="display:block; margin-bottom:8px; color:#999; font-size:14px;">Name</label>
            <input type="text" id="editName" value="<?php echo htmlspecialchars($user['name']); ?>" 
                   style="width:100%; padding:12px; background:#1a1a1a; border:1px solid #1f1d1dff; border-radius:8px; color:#e0e0e0; font-size:15px;">
        </div>
        
        <div style="margin-bottom:20px;">
            <label style="display:block; margin-bottom:8px; color:#999; font-size:14px;">Bio</label>
            <textarea id="editBio" rows="4" 
                      style="width:100%; padding:12px; background:#1a1a1a; border:1px solid #404040; border-radius:8px; color:#e0e0e0; font-size:15px; resize:vertical;"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            <small style="color:#666; font-size:12px;">Max 500 characters</small>
        </div>
        
        <div style="margin-bottom:20px;">
            <label style="display:block; margin-bottom:8px; color:#999; font-size:14px;">Profile Picture</label>
            <input type="file" id="avatarInput" accept="image/*" 
                   style="width:100%; padding:12px; background:#1a1a1a; border:1px solid #404040; border-radius:8px; color:#e0e0e0; font-size:14px;">
        </div>
        
        <div style="display:flex; gap:10px; margin-top:25px;">
            <button onclick="saveProfile()" class="btn btn-primary" style="flex:1;">Save Changes</button>
            <button onclick="closeEditModal()" class="btn btn-outline" style="flex:1;">Cancel</button>
        </div>
        
        <div id="editMessage" style="margin-top:15px; padding:10px; border-radius:6px; display:none;"></div>
    </div>
</div>
<?php endif; ?>

<script>
function showEditModal() {
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function saveProfile() {
    const name = document.getElementById('editName').value.trim();
    const bio = document.getElementById('editBio').value.trim();
    const avatarFile = document.getElementById('avatarInput').files[0];
    
    // First, update name and bio
    const formData = new FormData();
    formData.append('action', 'update_bio');
    formData.append('name', name);
    formData.append('bio', bio);
    
    fetch('../actions/update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            // If avatar selected, upload it
            if (avatarFile) {
                const avatarData = new FormData();
                avatarData.append('action', 'upload_avatar');
                avatarData.append('avatar', avatarFile);
                
                return fetch('../actions/update_profile.php', {
                    method: 'POST',
                    body: avatarData
                }).then(r => r.json());
            }
            return d;
        } else {
            throw new Error(d.error);
        }
    })
    .then(d => {
        if (d.success || d.message) {
            showMessage('Profile updated successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else if (d.error) {
            showMessage(d.error, 'error');
        }
    })
    .catch(err => {
        showMessage(err.message || 'Error updating profile', 'error');
    });
}

function showMessage(msg, type) {
    const msgDiv = document.getElementById('editMessage');
    msgDiv.textContent = msg;
    msgDiv.style.display = 'block';
    msgDiv.style.background = type === 'success' ? 'rgba(40, 167, 69, 0.2)' : 'rgba(220, 53, 69, 0.2)';
    msgDiv.style.color = type === 'success' ? '#28a745' : '#dc3545';
    msgDiv.style.border = `1px solid ${type === 'success' ? '#28a745' : '#dc3545'}`;
}

function addF(userId) {
    fetch('../actions/add_friend.php', {
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
    fetch('../actions/remove_friend.php', {
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
