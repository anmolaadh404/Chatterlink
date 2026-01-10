<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /Chatterlink/auth/login.php");
    exit;
}

include 'config/db.php';

$my_id = $_SESSION['user_id'];
$result = mysqli_query($conn, "SELECT user_id, name FROM users WHERE user_id != $my_id");
?>
<!DOCTYPE html>
<html>
<head>
<title>Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: Arial; background:#fafafa; margin:0; }
.header {
    padding:15px;
    background:#fff;
    border-bottom:1px solid #ddd;
    display:flex;
    justify-content:space-between;
}
.user {
    padding:15px;
    border-bottom:1px solid #eee;
}
a { text-decoration:none; color:#3797f0; }
</style>
</head>
<body>

<div class="header">
    <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
    <a href="/Chatterlink/auth/logout.php">Logout</a>
</div>

<?php while($u = mysqli_fetch_assoc($result)): ?>
<div class="user">
    <a href="/Chatterlink/pages/chat.php?user_id=<?php echo $u['user_id']; ?>">
        <?php echo htmlspecialchars($u['name']); ?>
    </a>
</div>
<?php endwhile; ?>

</body>
</html>
