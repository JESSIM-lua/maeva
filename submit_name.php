<?php
session_start();
require 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $username = $_SESSION['username'];
    $shared_username = $_POST['shared_username'];
    
    $sql = "INSERT INTO baby_names (name, username, shared_username) VALUES ('$name', '$username', '$shared_username')";
    
    if ($conn->query($sql) === TRUE) {
        header("Location: names.php");
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <title>Submit Baby Name</title>
</head>
<body>
<p><a href="index.php">Accueil</a></p>
    <form method="POST" action="">
        <h2>Submit Baby Name</h2>
        <label>Baby Name:</label>
        <input type="text" name="name" required>
        <label>Share with Username (optional):</label>
        <input type="text" name="shared_username">
        <button type="submit">Submit</button>
        <p><a href="names.php">View Submitted Names</a></p>
    </form>
</body>
</html>
