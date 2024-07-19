<?php
session_start();
require '../config/config.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
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
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    $target_file = $target_dir . basename($_FILES["image"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

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

    if (file_exists($target_file)) {
        echo "Désolé, le fichier existe déjà.";
        $uploadOk = 0;
    }

    if ($_FILES["image"]["size"] > 5000000) { // 5MB
        echo "Désolé, votre fichier est trop volumineux. Compression en cours.";
        $temp_file = $_FILES["image"]["tmp_name"];
        processImage($temp_file, $target_file, 75);
        $uploadOk = 1;
    } else {
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            processImage($target_file, $target_file, 75);
            $uploadOk = 1;
        }
    }

    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        echo "Désolé, seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
        $uploadOk = 0;
    }

    if ($uploadOk == 0) {
        echo "Désolé, votre fichier n'a pas été téléchargé.";
    } else {
        $stmt = $conn->prepare("INSERT INTO albums (username, image_path) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $current_username, $target_file);
            $stmt->execute();
            $stmt->close();
        } else {
            echo "Erreur SQL : " . $conn->error;
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['like_photo'])) {
    $photo_id = $_POST['photo_id'];
    $stmt = $conn->prepare("INSERT IGNORE INTO likes (user_id, photo_id) VALUES ((SELECT id FROM users WHERE username = ?), ?)");
    if ($stmt) {
        $stmt->bind_param("si", $current_username, $photo_id);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "Erreur SQL : " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comment_photo'])) {
    $photo_id = $_POST['photo_id'];
    $comment = $_POST['comment'];
    $stmt = $conn->prepare("INSERT INTO comments (user_id, photo_id, comment) VALUES ((SELECT id FROM users WHERE username = ?), ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sis", $current_username, $photo_id, $comment);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "Erreur SQL : " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_photo'])) {
    $photo_id = $_POST['photo_id'];

    // Commencez une transaction
    $conn->begin_transaction();

    try {
        // Supprimer les likes liés à la photo
        $stmt = $conn->prepare("DELETE FROM likes WHERE photo_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $photo_id);
            $stmt->execute();
            $stmt->close();
        } else {
            throw new Exception("Erreur SQL lors de la suppression des likes : " . $conn->error);
        }

        // Supprimer les commentaires liés à la photo
        $stmt = $conn->prepare("DELETE FROM comments WHERE photo_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $photo_id);
            $stmt->execute();
            $stmt->close();
        } else {
            throw new Exception("Erreur SQL lors de la suppression des commentaires : " . $conn->error);
        }

        // Supprimer la photo
        $stmt = $conn->prepare("DELETE FROM albums WHERE id = ? AND username = ?");
        if ($stmt) {
            $stmt->bind_param("is", $photo_id, $current_username);
            $stmt->execute();
            $stmt->close();
        } else {
            throw new Exception("Erreur SQL lors de la suppression de la photo : " . $conn->error);
        }

        // Supprimer le fichier de l'image du système de fichiers
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        if (file_exists($target_file)) {
            if (!unlink($target_file)) {
                throw new Exception("Erreur lors de la suppression du fichier image.");
            }
        }

        // Commit la transaction
        $conn->commit();
    } catch (Exception $e) {
        // En cas d'erreur, rollback la transaction
        $conn->rollback();
        echo "Erreur lors de la suppression de la photo : " . $e->getMessage();
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

$friends = [$current_username];
if ($friends_result->num_rows > 0) {
    while($row = $friends_result->fetch_assoc()) {
        $friends[] = $row['friend'];
    }
}

// Préparer la requête SQL pour récupérer les images
$friends_in = implode("','", array_map([$conn, 'real_escape_string'], $friends));
$albums_sql = "
    SELECT a.id, a.username, a.image_path, a.upload_time, 
    (SELECT COUNT(*) FROM likes WHERE photo_id = a.id) AS like_count,
    (SELECT COUNT(*) FROM comments WHERE photo_id = a.id) AS comment_count
    FROM albums a 
    WHERE username IN ('$friends_in') 
    ORDER BY upload_time DESC
";
$albums_result = $conn->query($albums_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
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

        /* Style pour les commentaires */
        .comments-modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.9);
        }

        .comments-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
        }

        .comments-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .comments-close:hover,
        .comments-close:focus {
            color: black;
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
                echo "<p>Likes: " . $row["like_count"] . "</p>";
                echo "<form method='POST' action=''>
                        <input type='hidden' name='photo_id' value='" . $row["id"] . "'>
                        <button type='submit' name='like_photo'>Like</button>
                      </form>";
                echo "<p>Commentaires: " . $row["comment_count"] . "</p>";
                echo "<form method='POST' action=''>
                        <input type='hidden' name='photo_id' value='" . $row["id"] . "'>
                        <textarea name='comment' required></textarea>
                        <button type='submit' name='comment_photo'>Commenter</button>
                      </form>";
                echo "<button onclick='showComments(" . $row["id"] . ")'>Afficher les commentaires</button>";
                if ($row["username"] == $current_username) {
                    echo "<form method='POST' action=''>
                            <input type='hidden' name='photo_id' value='" . $row["id"] . "'>
                            <button type='submit' name='delete_photo'>Supprimer</button>";
                }
                echo "</div>";
            }
        } else {
            echo "<p>Aucune photo trouvée</p>";
        }
        ?>
    </div>
    <p><a href="../index.php">Retour à l'accueil</a></p>
    <p><a href="../babyname/names.php">Retour aux prénoms</a></p>

    <!-- Modal pour les images -->
    <div id="myModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <!-- Modal pour les commentaires -->
    <div id="commentsModal" class="comments-modal">
        <div class="comments-content">
            <span class="comments-close" onclick="closeCommentsModal()">&times;</span>
            <div id="commentsSection"></div>
        </div>
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

        function showComments(photoId) {
            var modal = document.getElementById("commentsModal");
            var commentsSection = document.getElementById("commentsSection");
            modal.style.display = "block";

            // Fetch comments using AJAX
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "fetch_comments.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    commentsSection.innerHTML = xhr.responseText;
                }
            };
            xhr.send("photo_id=" + photoId);
        }

        function closeCommentsModal() {
            var modal = document.getElementById("commentsModal");
            modal.style.display = "none";
        }
    </script>
</body>
</html>
