<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    
    $error = null;

    // Validation
    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($password !== $password_confirm) {
        $error = "Passwords do not match";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = "Username must be 3-20 characters (letters, numbers, underscores only)";
    }

    if (!$error) {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Email already registered";
        }
        $stmt->close();

        // Check if username already exists
        if (!$error) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username already taken";
            }
            $stmt->close();
        }

        if (!$error) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, username, email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $username, $email, $password_hash);
            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign Up | Chatterlink</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
* {
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", sans-serif;
}

body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #111 0%, #e9ecef 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    color: #e0e0e0;
}

.auth-box {
    width: 100%;
    max-width: 420px;
    padding: 48px 40px;
    background: #2d2d2d;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
}

.logo {
    width: 56px;
    height: 56px;
    margin: 0 auto 28px;
}

.logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
    color: #ffffff;
}

.subtitle {
    font-size: 15px;
    color: #636e72;
    margin-bottom: 32px;
    font-weight: 400;
}

input {
    width: 100%;
    padding: 12px 16px;
    margin-bottom: 14px;
    border-radius: 10px;
    border: 1.5px solid #dfe6e9;
    background: #f8f9fa;
    color: #e0e0e0;
    outline: none;
    font-size: 15px;
    transition: all 0.3s ease;
}

input:focus {
    border-color: #5865F2;
    background: #2d2d2d;
    box-shadow: 0 0 0 3px rgba(9, 132, 227, 0.1);
}

input::placeholder {
    color: #b2bec3;
}

button {
    width: 100%;
    padding: 12px 24px;
    margin-top: 8px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #5865F2 0%, #4752C4 100%);
    color: white;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(9, 132, 227, 0.4);
}

button:active {
    transform: translateY(0);
}

.error {
    color: #d63031;
    font-size: 14px;
    margin-bottom: 16px;
    padding: 10px 14px;
    background: #fff5f6;
    border-radius: 8px;
    border-left: 4px solid #d63031;
}

.switch {
    margin-top: 20px;
    font-size: 14px;
    color: #636e72;
}

.switch a {
    color: #5865F2;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
}

.switch a:hover {
    color: #4752C4;
}
</style>
</head>

<body>

<div class="auth-box">

    <div class="logo">
        <img src="../assets/logo.png" alt="Chatterlink Logo">
    </div>

    <h1 class="title">Welcome to Chatterlink</h1>
    <p class="subtitle">Create your account</p>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="name" placeholder="Full name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        <input type="text" name="username" placeholder="Username (3-20 chars)" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
        <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        <input type="password" name="password" placeholder="Password (min 6 chars)" required>
        <input type="password" name="password_confirm" placeholder="Confirm password" required>
        <button type="submit">Sign up</button>
    </form>

    <div class="switch">
        Already have an account? <a href="login.php">Sign in</a>
    </div>

</div>

</body>
</html>
