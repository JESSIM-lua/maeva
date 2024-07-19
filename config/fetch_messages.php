<?php
require '../config/config.php';

if (isset($_GET['discussion_id'])) {
    $discussion_id = $_GET['discussion_id'];
    $messages_sql = "
        SELECT m.*, u.username AS sender_username 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.discussion_id = ?
        ORDER BY m.sent_at ASC
    ";
    $stmt = $conn->prepare($messages_sql);
    if (!$stmt) {
        die('Erreur de préparation de la requête : ' . $conn->error);
    }
    $stmt->bind_param("i", $discussion_id);
    $stmt->execute();
    $messages_result = $stmt->get_result();

    $messages = [];
    while ($row = $messages_result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    echo json_encode($messages);
}
?>
