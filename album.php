<?php
session_start();
require 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$current_username = $_SESSION['username'];

// Fonction pour compresser et nettoyer l'image
function processImage($source, $destination, $quality) {
    $image = new Imagick($source);
    $image->stripImage(); // Supprimer les métadonnées EXIF
    $image->setImageCompressionQuality($quality);
    $image->writeImage($destination);
    $image->destroy();
}

// Gestion du téléchargement des images
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $target_dir = "uploads/";
    // Vérifier si le répertoire existe, sinon le créer
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    $target_file = $target_dir . basename($_FILES["image"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Vérifier si le fichier image est une image réelle ou une fausse image
    if (!empty($_FILES["image"]["tmp_name"])) {
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false) {
            $uploadOk = 1;
        } else {
            echo "Le fichier n'est pas une image.";
            $uploadOk = 0;
        }
    } else {
        echo "Le fichier n'est pas une image.";
        $uploadOk = 0;
    }

    // Vérifier si le fichier existe déjà
    if (file_exists($target_file)) {
        echo "Désolé, le fichier existe déjà.";
        $uploadOk = 0;
    }

    // Vérifier la taille du fichier
    if ($_FILES["image"]["size"] > 5000000) { // 5MB
        echo "Désolé, votre fichier est trop volumineux. Compression en cours.";
        $temp_file = $_FILES["image"]["tmp_name"];
        processImage($temp_file, $target_file, 75);
        $uploadOk = 1;
    }

    // Limiter les formats de fichiers
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        echo "Désolé, seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
        $uploadOk = 0;
    }

    // Vérifier si $uploadOk est mis à 0 par une erreur
    if ($uploadOk == 0) {
        echo "Désolé, votre fichier n'a pas été téléchargé.";
    // Si tout est ok, essayer de télécharger le fichier
    } else {
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            processImage($target_file, $target_file, 75); // Assurez-vous que l'image finale est traitée
            $stmt = $conn->prepare("INSERT INTO albums (username, image_path) VALUES (?, ?)");
            $stmt->bind_param("ss", $current_username, $target_file);
            $stmt->execute();
            $stmt->close();
        } else {
            echo "Désolé, une erreur s'est produite lors du téléchargement de votre fichier.";
        }
    }
} else {
    if (isset($_FILES['image']) && $_FILES['image']['error'] != 0) {
        echo "Erreur lors du téléchargement du fichier.";
    }
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

$friends = [$current_username]; // Inclure les photos de l'utilisateur actuel
if ($friends_result->num_rows > 0) {
    while($row = $friends_result->fetch_assoc()) {
        $friends[] = $row['friend'];
    }
}

// Préparer la requête SQL pour récupérer les images
$friends_in = implode("','", array_map([$conn, 'real_escape_string'], $friends));
$albums_sql = "SELECT username, image_path, upload_time FROM albums WHERE username IN ('$friends_in') ORDER BY upload_time DESC";
$albums_result = $conn->query($albums_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <title>Album</title>
    <style>
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1; 
            padding-top: 60px; 
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto; 
            background-color: rgb(0,0,0); 
            background-color: rgba(0,0,0,0.9); 
        }

        .modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
        }

        .modal-content {
            -webkit-animation-name: zoom;
            -webkit-animation-duration: 0.6s;
            animation-name: zoom;
            animation-duration: 0.6s;
        }

        @-webkit-keyframes zoom {
            from {-webkit-transform:scale(0)}
            to {-webkit-transform:scale(1)}
        }

        @keyframes zoom {
            from {transform:scale(0)}
            to {transform:scale(1)}
        }

        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
        }

        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h2>Album Photo</h2>
    <form method="POST" action="" enctype="multipart/form-data">
        <label for="image">Télécharger une image :</label>
        <input type="file" name="image" id="image" required>
        <button type="submit">Télécharger</button>
    </form>
    <h2>Photos</h2>
    <div class="gallery">
        <?php
        if ($albums_result->num_rows > 0) {
            while ($row = $albums_result->fetch_assoc()) {
                echo "<div class='gallery-item'>";
                echo "<p><strong>" . htmlspecialchars($row["username"]) . "</strong></p>";
                echo "<img src='" . htmlspecialchars($row["image_path"]) . "' alt='Photo' style='width:100%;' onclick='openModal(\"" . htmlspecialchars($row["image_path"]) . "\")'>";
                echo "<p>Publié le: " . $row["upload_time"] . "</p>";
                echo "</div>";
            }
        } else {
            echo "<p>Aucune photo trouvée</p>";
        }
        ?>
    </div>
    <p><a href="index.php">Retour à l'accueil</a></p>
    <p><a href="names.php">Retour aux prénoms</a></p>

    <!-- Modal -->
    <div id="myModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <script>
        function openModal(imageSrc) {
            var modal = document.getElementById("myModal");
            var modalImg = document.getElementById("modalImage");
            modal.style.display = "block";
            modalImg.src = imageSrc;
        }

        function closeModal() {
            var modal = document.getElementById("myModal");
            modal.style.display = "none";
        }
    </script>
</body>
</html>
