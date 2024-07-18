<?php
session_start();
require 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$current_username = $_SESSION['username'];

// Gestion de l'ajout de nouveaux prénoms
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['name'])) {
    $name = sanitize_input($_POST['name']);
    $stmt = $conn->prepare("INSERT INTO baby_names (name, username) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $current_username);
    $stmt->execute();
    $stmt->close();
}

// Obtenir les amis acceptés
$friends_sql = "
    SELECT receiver_username AS friend FROM friend_requests WHERE sender_username=? AND status='accepted'
    UNION
    SELECT sender_username AS friend FROM friend_requests WHERE receiver_username=? AND status='accepted'
";
$stmt = $conn->prepare($friends_sql);
$stmt->bind_param("ss", $current_username, $current_username);
$stmt->execute();
$friends_result = $stmt->get_result();

$friends = [];
if ($friends_result->num_rows > 0) {
    while($row = $friends_result->fetch_assoc()) {
        $friends[] = $row['friend'];
    }
}

// Préparer la requête SQL pour récupérer les prénoms
$friends_in = implode("','", array_map([$conn, 'real_escape_string'], $friends));
$names_sql = "SELECT name, username FROM baby_names WHERE username=? OR username IN ('$friends_in')";
$stmt = $conn->prepare($names_sql);
$stmt->bind_param("s", $current_username);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <title>Prénoms Soumis</title>
</head>
<body>
    <h2>Prénoms Soumis</h2>
    <form method="POST" action="">
        <label for="name">Ajouter un prénom :</label>
        <input type="text" name="name" id="name" required>
        <button type="submit">Ajouter</button>
    </form>
    <table>
        <tr>
            <th>Prénom</th>
            <th>Nom d'utilisateur</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                echo "<tr><td>" . htmlspecialchars($row["name"]). "</td><td>" . htmlspecialchars($row["username"]). "</td></tr>";
            }
        } else {
            echo "<tr><td colspan='2'>Aucun prénom trouvé</td></tr>";
        }
        ?>
    </table>
    <p><a href="index.php">Retour à l'accueil</a></p>
    <p><a href="friend_requests.php">Gérer les amis</a></p>
</body>
</html>
