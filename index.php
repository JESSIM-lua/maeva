<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($_POST['username']);
    $password = sanitize_input($_POST['password']);
    
    $stmt = $conn->prepare("SELECT password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    
    if (password_verify($password, $hashed_password)) {
        $_SESSION['username'] = $username;
        header("Location: names.php");
        exit();
    } else {
        $error = "Nom d'utilisateur ou mot de passe invalide.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <title>Connexion</title>
</head>
<body>
    <?php if (isset($_SESSION['username'])): ?>
        <h2>Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?> !</h2>
        <p><a href="names.php">Soumettre un prénom</a></p>
        <p><a href="names.php">Voir les prénoms soumis</a></p>
        <p><a href="album.php">Consulter l'album photo</a></p>
        <p><a href="friend_requests.php">Gérer les amis</a></p>
        <p><a href="logout.php">Déconnexion</a></p>
    <?php else: ?>
        <form method="POST" action="">
            <h2>Connexion</h2>
            <?php if (isset($error)): ?>
                <p style="color: red;"><?php echo $error; ?></p>
            <?php endif; ?>
            <label>Nom d'utilisateur :</label>
            <input type="text" name="username" required>
            <label>Mot de passe :</label>
            <input type="password" name="password" required>
            <button type="submit">Connexion</button>
            <p>Vous n'avez pas de compte ? <a href="signup.php">Inscription</a></p>
        </form>
    <?php endif; ?>
</body>
</html>
