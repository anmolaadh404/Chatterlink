<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT user_id, name, password FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            header("Location: /Chatterlink/users.php");
            exit;
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "Invalid email or password";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | Chatterlink</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
* {
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", sans-serif;
}

body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    color: #2d3436;
}

.auth-box {
    width: 100%;
    max-width: 420px;
    padding: 48px 40px;
    background: white;
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
    color: #1a1a1a;
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
    color: #2d3436;
    outline: none;
    font-size: 15px;
    transition: all 0.3s ease;
}

input:focus {
    border-color: #0984e3;
    background: white;
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
    background: linear-gradient(135deg, #0984e3 0%, #0770d1 100%);
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
    color: #0984e3;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
}

.switch a:hover {
    color: #0770d1;
}
</style>
</head>

<body>

<div class="auth-box">

    <div class="logo">
        <img src="../assets/logo.png" alt="Chatterlink Logo">
    </div>

    <h1 class="title">Welcome to Chatterlink</h1>
    <p class="subtitle">Sign in to continue</p>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input name="email" placeholder="johndoe@gmail.com" required>
        <input name="password" type="password" placeholder="Password" required>
        <button>Login</button>
    </form>
    
    <div class="switch">
        New here? <a href="register.php">Sign up</a>
    </div>

</div>

</body>
</html>
