<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /Chatterlink/Chatterlink/auth/login.php");
    exit;
}

include 'config/db.php';

$my_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_id, name, username, avatar, bio FROM users WHERE user_id != ? ORDER BY name");
$stmt->bind_param("i", $my_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatterlink - Users</title>
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
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-brand {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-user {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .header-right {
            display: flex;
            gap: 1rem;
        }

        .header-btn {
            padding: 0.6rem 1.2rem;
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid var(--border-color);
            color: var(--accent-cyan);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .header-btn:hover {
            background: var(--accent-cyan);
            color: var(--bg-dark);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .user-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .user-card:hover {
            border-color: var(--accent-cyan);
            box-shadow: 0 8px 24px rgba(0, 212, 255, 0.15);
            transform: translateY(-4px);
        }

        .user-avatar-section {
            height: 120px;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(124, 58, 237, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent-cyan);
        }

        .user-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            border: 3px solid var(--accent-cyan);
        }

        .user-info {
            padding: 1.5rem;
        }

        .user-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .user-username {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .user-bio {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            min-height: 36px;
        }

        .user-bio.empty {
            color: #666;
            font-style: italic;
        }

        .user-action {
            display: flex;
            gap: 0.75rem;
        }

        .btn-message {
            flex: 1;
            padding: 0.7rem;
            background: linear-gradient(135deg, var(--accent-cyan), #00a8cc);
            border: none;
            color: var(--bg-dark);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-message:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 212, 255, 0.3);
        }

        .btn-profile {
            flex: 1;
            padding: 0.7rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-profile:hover {
            background: rgba(0, 212, 255, 0.1);
            border-color: var(--accent-cyan);
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
            }

            .header-left {
                width: 100%;
            }

            .header-right {
                width: 100%;
            }

            .container {
                padding: 1rem;
            }

            .users-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="header-brand">⚡ Chatterlink</div>
            <div class="header-user">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></div>
        </div>
        <div class="header-right">
            <a href="/Chatterlink/Chatterlink/pages/profiles.php" class="header-btn">My Profile</a>
            <a href="/Chatterlink/Chatterlink/auth/logout.php" class="header-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="section-title">Connect & Chat</div>

        <div class="users-grid">
            <?php while($user = $result->fetch_assoc()): ?>
            <div class="user-card">
                <div class="user-avatar-section">
                    <?php if($user['avatar']): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" class="user-avatar">
                    <?php else: ?>
                        <div class="user-placeholder"><?php echo strtoupper(substr($user['name'] ?? $user['username'], 0, 1)); ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="user-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="user-bio <?php echo empty($user['bio']) ? 'empty' : ''; ?>">
                        <?php echo htmlspecialchars($user['bio'] ?? 'No bio added'); ?>
                    </div>
                    <div class="user-action">
                        <a href="/Chatterlink/Chatterlink/pages/chat.php?user_id=<?php echo $user['user_id']; ?>" class="btn-message">Message</a>
                        <a href="/Chatterlink/Chatterlink/pages/profiles.php?user_id=<?php echo $user['user_id']; ?>" class="btn-profile">Profile</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
