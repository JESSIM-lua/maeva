<?php
session_start();
require '../config/config.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

$current_username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil</title>
    <link rel="stylesheet" type="text/css" href="../css/styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        h2 {
            color: #333;
        }

        .nav-button {
            display: block;
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            background-color: #5cb85c;
            border: none;
            border-radius: 4px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
        }

        .nav-button:hover {
            background-color: #4cae4c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Bienvenue, <?php echo htmlspecialchars($current_username); ?> !</h2>
        <a class="nav-button" href="../album/album.php">Album</a>
        <a class="nav-button" href="../babyname/names.php">Idées de Prénoms</a>
        <a class="nav-button" href="../friends/chat.php">Chater</a>

        <a class="nav-button" href="../friends/friend_requests.php">Amis</a>
    </div>
</body>
</html>
