<?php
session_start();
require '../config/config.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

$current_username = $_SESSION['username'];

// Récupérer l'ID de l'utilisateur actuel
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $current_username);
$stmt->execute();
$stmt->bind_result($current_user_id);
$stmt->fetch();
$stmt->close();

// Gestion des demandes d'amis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['friend_username'])) {
        $friend_username = sanitize_input($_POST['friend_username']);

        // Récupérer l'ID du destinataire
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $friend_username);
        $stmt->execute();
        $stmt->bind_result($friend_id);
        if ($stmt->fetch()) {
            $stmt->close();

            // Envoyer la demande d'ami
            $stmt = $conn->prepare("INSERT INTO friend_requests (sender_username, receiver_username, status, sender_id, receiver_id) VALUES (?, ?, 'pending', ?, ?)");
            $stmt->bind_param("ssii", $current_username, $friend_username, $current_user_id, $friend_id);
            $stmt->execute();
            $stmt->close();
        } else {
            echo "Utilisateur non trouvé.";
            $stmt->close();
        }
    } elseif (isset($_POST['accept_username'])) {
        $accept_username = sanitize_input($_POST['accept_username']);

        // Récupérer l'ID du destinataire
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $accept_username);
        $stmt->execute();
        $stmt->bind_result($accept_user_id);
        $stmt->fetch();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE friend_requests SET status='accepted' WHERE receiver_username=? AND sender_username=?");
        $stmt->bind_param("ss", $current_username, $accept_username);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['reject_username'])) {
        $reject_username = sanitize_input($_POST['reject_username']);

        $stmt = $conn->prepare("UPDATE friend_requests SET status='rejected' WHERE receiver_username=? AND sender_username=?");
        $stmt->bind_param("ss", $current_username, $reject_username);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['remove_friend'])) {
        $remove_friend = sanitize_input($_POST['remove_friend']);

        $stmt = $conn->prepare("DELETE FROM friend_requests WHERE (sender_username=? AND receiver_username=?) OR (sender_username=? AND receiver_username=?)");
        $stmt->bind_param("ssss", $current_username, $remove_friend, $remove_friend, $current_username);
        $stmt->execute();
        $stmt->close();
    }
}

// Obtenir les demandes d'amis en attente
$pending_requests_sql = "SELECT sender_username FROM friend_requests WHERE receiver_username=? AND status='pending'";
$stmt = $conn->prepare($pending_requests_sql);
$stmt->bind_param("s", $current_username);
$stmt->execute();
$pending_requests_result = $stmt->get_result();

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
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
    <title>Gérer les Amis</title>
</head>
<body>
    <h2>Envoyer une demande d'ami</h2>
    <form method="POST" action="">
        <label for="friend_username">Entrez le nom d'utilisateur :</label>
        <input type="text" name="friend_username" id="friend_username" required>
        <button type="submit">Envoyer la demande</button>
    </form>
    <h2>Demandes d'amis en attente</h2>
    <table>
        <tr>
            <th>Nom d'utilisateur</th>
            <th>Action</th>
        </tr>
        <?php
        if ($pending_requests_result->num_rows > 0) {
            while($row = $pending_requests_result->fetch_assoc()) {
                echo "<tr>
                        <td>" . htmlspecialchars($row["sender_username"]). "</td>
                        <td>
                            <form method='POST' action='' style='display:inline;'>
                                <input type='hidden' name='accept_username' value='" . htmlspecialchars($row["sender_username"]) . "'>
                                <button type='submit'>Accepter</button>
                            </form>
                            <form method='POST' action='' style='display:inline;'>
                                <input type='hidden' name='reject_username' value='" . htmlspecialchars($row["sender_username"]) . "'>
                                <button type='submit'>Refuser</button>
                            </form>
                        </td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='2'>Aucune demande en attente</td></tr>";
        }
        ?>
    </table>
    <h2>Liste des amis</h2>
    <ul>
        <?php
        if ($friends_result->num_rows > 0) {
            while($row = $friends_result->fetch_assoc()) {
                echo "<li>
                        " . htmlspecialchars($row['friend']) . "
                        <form method='POST' action='' style='display:inline;'>
                            <input type='hidden' name='remove_friend' value='" . htmlspecialchars($row['friend']) . "'>
                            <button type='submit'>Supprimer</button>
                        </form>
                      </li>";
            }
        } else {
            echo "<li>Aucun ami trouvé</li>";
        }
        ?>
    </ul>
    <p><a href="../index.php">Retour à l'accueil</a></p>
    <p><a href="../babyname/names.php">Retour aux prénoms</a></p>
</body>
</html>
