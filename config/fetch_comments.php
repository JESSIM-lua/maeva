<?php
session_start();
require '../config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['photo_id'])) {
    $photo_id = $_POST['photo_id'];
    $stmt = $conn->prepare("SELECT c.comment, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.photo_id = ?");
    $stmt->bind_param("i", $photo_id);
    $stmt->execute();
    $comments_result = $stmt->get_result();
    $comments = [];
    while ($row = $comments_result->fetch_assoc()) {
        $comments[] = $row;
    }
    $stmt->close();

    foreach ($comments as $comment) {
        echo "<p><strong>" . htmlspecialchars($comment['username']) . ":</strong> " . htmlspecialchars($comment['comment']) . "</p>";
    }
}
?>
