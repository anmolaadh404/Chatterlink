<?php
session_start();
include '../config/db.php';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows > 0){
        $error = "Email already registered";
    } else {
        $stmt2 = $conn->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)");
        $stmt2->bind_param("sss",$name,$email,$password);
        $stmt2->execute();
        $stmt2->close();
        header("Location: /Chatterlink/auth/login.php");
        exit;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head><title>Register</title></head>
<body>
<h2>Register</h2>
<?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="POST">
    Name:<br><input type="text" name="name" required><br>
    Email:<br><input type="email" name="email" required><br>
    Password:<br><input type="password" name="password" required><br>
    <button type="submit">Register</button>
</form>
<a href="/Chatterlink/auth/login.php">Login</a>
</body>
</html>
