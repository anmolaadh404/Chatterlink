<?php
include 'config/db.php';
$result = $conn->query("SELECT * FROM users");
while($row = $result->fetch_assoc()){
    echo $row['name']."<br>";
}
?>
