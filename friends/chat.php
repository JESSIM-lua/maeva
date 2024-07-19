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
if (!$stmt) {
    die('Erreur de préparation de la requête : ' . $conn->error);
}
$stmt->bind_param("s", $current_username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$current_user_id = $user['id'];
$stmt->close();

// Récupérer les amis depuis la table friend_requests
$friends_sql = "
    SELECT u.id, u.username 
    FROM users u
    JOIN friend_requests fr ON (u.username = fr.sender_username OR u.username = fr.receiver_username) 
    WHERE (fr.sender_username = ? OR fr.receiver_username = ?) AND fr.status = 'accepted' AND u.username != ?
";
$stmt = $conn->prepare($friends_sql);
if (!$stmt) {
    die('Erreur de préparation de la requête : ' . $conn->error);
}
$stmt->bind_param("sss", $current_username, $current_username, $current_username);
$stmt->execute();
$friends_result = $stmt->get_result();

$friends = [];
while ($row = $friends_result->fetch_assoc()) {
    $friends[] = $row;
}
$stmt->close();

// Créer ou récupérer une discussion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['start_discussion'])) {
    $friend_id = $_POST['friend_id'];
    
    // Vérifier l'existence d'une discussion entre les deux utilisateurs
    $check_discussion_sql = "
        SELECT id 
        FROM discussions 
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
    ";
    $stmt = $conn->prepare($check_discussion_sql);
    if (!$stmt) {
        die('Erreur de préparation de la requête : ' . $conn->error);
    }
    $stmt->bind_param("iiii", $current_user_id, $friend_id, $friend_id, $current_user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($discussion_id);
        $stmt->fetch();
    } else {
        // Créer une nouvelle discussion
        $stmt = $conn->prepare("INSERT INTO discussions (user1_id, user2_id) VALUES (?, ?)");
        if (!$stmt) {
            die('Erreur de préparation de la requête : ' . $conn->error);
        }
        $stmt->bind_param("ii", $current_user_id, $friend_id);
        $stmt->execute();
        $discussion_id = $stmt->insert_id;
        $stmt->close();

        // Ajouter les membres à la table discussion_members
        $stmt = $conn->prepare("INSERT INTO discussion_members (discussion_id, user_id) VALUES (?, ?), (?, ?)");
        $stmt->bind_param("iiii", $discussion_id, $current_user_id, $discussion_id, $friend_id);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode(['discussion_id' => $discussion_id]);
    exit();
}

// Récupérer les messages pour une discussion
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
    exit();
}

// Envoyer un message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $discussion_id = $_POST['discussion_id'];
    $message = $_POST['message'];
    $stmt = $conn->prepare("INSERT INTO messages (discussion_id, sender_id, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        die('Erreur de préparation de la requête : ' . $conn->error);
    }
    $stmt->bind_param("iis", $discussion_id, $current_user_id, $message);
    $stmt->execute();
    $stmt->close();
    echo "Message envoyé";
    exit();
}

// Vérifier les nouveaux messages
if (isset($_GET['check_messages']) && isset($_GET['discussion_id'])) {
    $discussion_id = $_GET['discussion_id'];
    $last_message_id = $_GET['last_message_id'];
    $new_messages_sql = "
        SELECT m.*, u.username AS sender_username 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.discussion_id = ? AND m.id > ?
        ORDER BY m.sent_at ASC
    ";
    $stmt = $conn->prepare($new_messages_sql);
    if (!$stmt) {
        die('Erreur de préparation de la requête : ' . $conn->error);
    }
    $stmt->bind_param("ii", $discussion_id, $last_message_id);
    $stmt->execute();
    $new_messages_result = $stmt->get_result();

    $new_messages = [];
    while ($row = $new_messages_result->fetch_assoc()) {
        $new_messages[] = $row;
    }
    $stmt->close();
    echo json_encode($new_messages);
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussions</title>
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
            max-width: 600px;
            width: 100%;
        }

        h2, h3 {
            text-align: center;
            color: #333;
        }

        .friends-list {
            list-style: none;
            padding: 0;
        }

        .friends-list li {
            margin-bottom: 10px;
            text-align: center;
        }

        .friends-list button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .friends-list button:hover {
            background-color: #0056b3;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .message-form textarea {
            width: 100%;
            height: 50px;
            resize: none;
        }

        .message-form button {
            width: 100%;
            padding: 10px;
            background-color: #5cb85c;
            border: none;
            border-radius: 4px;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .message-form button:hover {
            background-color: #4cae4c;
        }

        .messages {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .message {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 10px;
        }

        .message.sent {
            background-color: #d1ffd1;
            text-align: right;
        }

        .message.received {
            background-color: #d1d1ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Bienvenue, <?php echo htmlspecialchars($current_username); ?>!</h2>
        <h3>Vos amis</h3>
        <ul class="friends-list">
            <?php if (empty($friends)) : ?>
                <li>Aucun ami trouvé.</li>
            <?php else: ?>
                <?php foreach ($friends as $friend): ?>
                    <li>
                        <?php echo htmlspecialchars($friend['username']); ?>
                        <button onclick="startDiscussion(<?php echo $friend['id']; ?>, '<?php echo htmlspecialchars($friend['username']); ?>')">Discuter</button>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Modal pour les discussions -->
    <div id="chatModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeChat()">&times;</span>
            <h3 id="chatWith"></h3>
            <div class="messages" id="chatMessages"></div>
            <form class="message-form" id="messageForm">
                <textarea name="message" id="messageInput" required></textarea>
                <button type="submit">Envoyer</button>
                <input type="hidden" name="discussion_id" id="discussionId">
            </form>
        </div>
    </div>

    <script>
        let lastMessageId = 0;

        function startDiscussion(friendId, friendUsername) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../friends/chat.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (this.status === 200) {
                    const response = JSON.parse(this.responseText);
                    openChat(response.discussion_id, friendUsername);
                }
            };
            xhr.send('start_discussion=true&friend_id=' + friendId);
        }

        function openChat(discussionId, friendUsername) {
            document.getElementById('chatModal').style.display = 'block';
            document.getElementById('chatWith').textContent = 'Discussion avec ' + friendUsername;
            document.getElementById('discussionId').value = discussionId;
            loadMessages(discussionId);
        }

        function closeChat() {
            document.getElementById('chatModal').style.display = 'none';
        }

        function loadMessages(discussionId) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', '../friends/chat.php?discussion_id=' + discussionId, true);
            xhr.onload = function () {
                if (this.status === 200) {
                    const messages = JSON.parse(this.responseText);
                    const chatMessages = document.getElementById('chatMessages');
                    chatMessages.innerHTML = '';
                    messages.forEach(message => {
                        const messageElement = document.createElement('div');
                        messageElement.classList.add('message');
                        messageElement.classList.add(message.sender_id == <?php echo $current_user_id; ?> ? 'sent' : 'received');
                        messageElement.innerHTML = `
                            <p><strong>${message.sender_username}:</strong> ${message.message}</p>
                            <p><small>${message.sent_at}</small></p>
                        `;
                        chatMessages.appendChild(messageElement);
                        lastMessageId = message.id; // Mettre à jour le dernier ID de message
                    });
                }
            };
            xhr.send();
            checkNewMessages(discussionId); // Commence à vérifier les nouveaux messages
        }

        function checkNewMessages(discussionId) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', '../friends/chat.php?check_messages=true&discussion_id=' + discussionId + '&last_message_id=' + lastMessageId, true);
            xhr.onload = function () {
                if (this.status === 200) {
                    const newMessages = JSON.parse(this.responseText);
                    if (newMessages.length > 0) {
                        const chatMessages = document.getElementById('chatMessages');
                        newMessages.forEach(message => {
                            const messageElement = document.createElement('div');
                            messageElement.classList.add('message');
                            messageElement.classList.add(message.sender_id == <?php echo $current_user_id; ?> ? 'sent' : 'received');
                            messageElement.innerHTML = `
                                <p><strong>${message.sender_username}:</strong> ${message.message}</p>
                                <p><small>${message.sent_at}</small></p>
                            `;
                            chatMessages.appendChild(messageElement);
                            lastMessageId = message.id; // Mettre à jour le dernier ID de message
                        });
                        chatMessages.scrollTop = chatMessages.scrollHeight; // Défiler vers le bas
                    }
                }
            };
            xhr.send();
            setTimeout(() => checkNewMessages(discussionId), 5000); // Vérifier toutes les 5 secondes
        }

        document.getElementById('messageForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const discussionId = document.getElementById('discussionId').value;
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value;
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../friends/chat.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (this.status === 200) {
                    messageInput.value = '';
                    loadMessages(discussionId);
                }
            };
            xhr.send('send_message=true&discussion_id=' + discussionId + '&message=' + encodeURIComponent(message));
        });
    </script>
</body>
</html>
