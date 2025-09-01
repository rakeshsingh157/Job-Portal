<?php
// chat_api.php
require_once 'config2.php';

header('Content-Type: application/json');

$currentUser = getCurrentUser();
if (!$currentUser) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_conversations':
        getConversations($currentUser);
        break;
    case 'get_messages':
        $conversationId = $_GET['conversation_id'] ?? 0;
        getMessages($conversationId, $currentUser);
        break;
    case 'send_message':
        $data = json_decode(file_get_contents('php://input'), true);
        sendMessage($data, $currentUser);
        break;
    case 'search_users':
        $query = $_GET['query'] ?? '';
        searchUsers($query, $currentUser);
        break;
    case 'get_current_user':
        getCurrentUserDetailsAPI();
        break;
    case 'get_user_details': // New case for fetching single user details
        $id = $_GET['id'] ?? 0;
        $type = $_GET['type'] ?? '';
        getUserDetailsAPI($id, $type);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function getCurrentUserDetailsAPI() {
    $currentUser = getCurrentUserDetails();
    if ($currentUser) {
        $currentUser['photo'] = $currentUser['photo'] ?: 'https://placehold.co/40x40/7A69FF/FFFFFF?text=' . substr($currentUser['name'], 0, 1);
        $currentUser['is_verified'] = (bool)$currentUser['is_verified'];
        echo json_encode($currentUser);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
}

function getUserDetailsAPI($id, $type) {
    global $conn;
    $userDetails = getUserDetails($id, $type);
    if ($userDetails) {
        $userDetails['photo'] = $userDetails['photo'] ?: 'https://placehold.co/40x40/7A69FF/FFFFFF?text=' . substr($userDetails['name'], 0, 1);
        $userDetails['is_verified'] = (bool)$userDetails['is_verified'];
        $userDetails['type'] = $type;
        echo json_encode($userDetails);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
}


function getConversations($currentUser) {
    global $conn;
    
    $conversations = [];
    
    if ($currentUser['type'] === 'user') {
        $stmt = $conn->prepare("
            SELECT c.id, c.company_id as other_id, 'company' as other_type, 
                   MAX(m.timestamp) as last_message_time
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            WHERE c.user_id = ?
            GROUP BY c.id
            ORDER BY last_message_time DESC
        ");
        $stmt->bind_param("i", $currentUser['id']);
    } else {
        $stmt = $conn->prepare("
            SELECT c.id, c.user_id as other_id, 'user' as other_type, 
                   MAX(m.timestamp) as last_message_time
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            WHERE c.company_id = ?
            GROUP BY c.id
            ORDER BY last_message_time DESC
        ");
        $stmt->bind_param("i", $currentUser['id']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $otherUser = getUserDetails($row['other_id'], $row['other_type']);
        if ($otherUser) {
            $conversations[] = [
                'conversation_id' => $row['id'],
                'other_user' => [
                    'id' => $otherUser['id'],
                    'name' => $otherUser['name'],
                    'photo' => $otherUser['photo'] ?: 'https://placehold.co/45x45/7A69FF/FFFFFF?text=' . substr($otherUser['name'], 0, 1),
                    'is_verified' => (bool)$otherUser['is_verified'],
                    'type' => $row['other_type']
                ],
                'last_message_time' => $row['last_message_time']
            ];
        }
    }
    
    echo json_encode($conversations);
}

function getMessages($conversationId, $currentUser) {
    global $conn;
    
    // Verify user has access to this conversation
    $stmt = $conn->prepare("
        SELECT id FROM conversations 
        WHERE id = ? AND (user_id = ? OR company_id = ?)
    ");
    $stmt->bind_param("iii", $conversationId, $currentUser['id'], $currentUser['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Conversation not found']);
        return;
    }
    
    // Get messages
    $stmt = $conn->prepare("
        SELECT m.*, 
               CASE 
                 WHEN m.sender_type = 'user' THEN u.profile_url 
                 ELSE cu.profile_photo 
               END as sender_photo,
               CASE 
                 WHEN m.sender_type = 'user' THEN CONCAT(u.first_name, ' ', u.last_name)
                 ELSE cu.company_name
               END as sender_name
        FROM messages m
        LEFT JOIN users u ON m.sender_type = 'user' AND m.sender_id = u.id
        LEFT JOIN cuser cu ON m.sender_type = 'company' AND m.sender_id = cu.id
        WHERE m.conversation_id = ?
        ORDER BY m.timestamp ASC
    ");
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $isMe = ($row['sender_type'] === $currentUser['type'] && $row['sender_id'] == $currentUser['id']);
        
        // Convert the timestamp to IST
        try {
            $timestampUTC = new DateTime($row['timestamp'], new DateTimeZone('UTC'));
            $timestampUTC->setTimezone(new DateTimeZone('Asia/Kolkata'));
            $formattedTimestamp = $timestampUTC->format('h:i A');
        } catch (Exception $e) {
            $formattedTimestamp = 'Invalid Date';
        }

        $messages[] = [
            'id' => $row['id'],
            'message' => $row['message'],
            'timestamp' => $formattedTimestamp,
            'isMe' => $isMe,
            'sender' => [
                'id' => $row['sender_id'],
                'type' => $row['sender_type'],
                'name' => $row['sender_name'],
                'photo' => $row['sender_photo'] ?: 'https://placehold.co/38x38/7A69FF/FFFFFF?text=' . substr($row['sender_name'], 0, 1)
            ]
        ];
    }
    
    // Mark messages as read
    $stmt = $conn->prepare("
        UPDATE messages SET is_read = TRUE 
        WHERE conversation_id = ? 
        AND sender_type != ? 
        AND sender_id != ?
    ");
    $otherType = $currentUser['type'] === 'user' ? 'company' : 'user';
    $stmt->bind_param("isi", $conversationId, $otherType, $currentUser['id']);
    $stmt->execute();
    
    echo json_encode($messages);
}

function sendMessage($data, $currentUser) {
    global $conn;
    
    $receiverId = $data['receiver_id'] ?? 0;
    $receiverType = $data['receiver_type'] ?? '';
    $message = trim($data['message'] ?? '');
    
    if (empty($message) ){
        echo json_encode(['error' => 'Message cannot be empty']);
        return;
    }
    
    // Find or create conversation
    if ($currentUser['type'] === 'user') {
        $stmt = $conn->prepare("
            SELECT id FROM conversations 
            WHERE user_id = ? AND company_id = ?
        ");
        $stmt->bind_param("ii", $currentUser['id'], $receiverId);
    } else {
        $stmt = $conn->prepare("
            SELECT id FROM conversations 
            WHERE company_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $currentUser['id'], $receiverId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conversationId = $row['id'];
    } else {
        // Create new conversation
        if ($currentUser['type'] === 'user') {
            $stmt = $conn->prepare("
                INSERT INTO conversations (user_id, company_id) 
                VALUES (?, ?)
            ");
            $stmt->bind_param("ii", $currentUser['id'], $receiverId);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO conversations (user_id, company_id) 
                VALUES (?, ?)
            ");
            $stmt->bind_param("ii", $receiverId, $currentUser['id']);
        }
        
        $stmt->execute();
        $conversationId = $conn->insert_id;
    }
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO messages (conversation_id, sender_type, sender_id, message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isis", $conversationId, $currentUser['type'], $currentUser['id'], $message);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message_id' => $conn->insert_id]);
}

function searchUsers($query, $currentUser) {
    global $conn;
    
    $searchTerm = "%$query%";
    $results = [];
    
    if ($currentUser['type'] === 'user') {
        // Search companies
        $stmt = $conn->prepare("
            SELECT id, company_name as name, profile_photo as photo, cverified as is_verified, 'company' as type
            FROM cuser 
            WHERE company_name LIKE ? OR email LIKE ?
        ");
    } else {
        // Search users
        $stmt = $conn->prepare("
            SELECT id, CONCAT(first_name, ' ', last_name) as name, profile_url as photo, is_verified, 'user' as type
            FROM users 
            WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
        ");
    }
    
    if ($currentUser['type'] === 'user') {
        $stmt->bind_param("ss", $searchTerm, $searchTerm);
    } else {
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'photo' => $row['photo'] ?: 'https://placehold.co/40x40/7A69FF/FFFFFF?text=' . substr($row['name'], 0, 1),
            'is_verified' => (bool)$row['is_verified'],
            'type' => $row['type']
        ];
    }
    
    echo json_encode($results);
}
?>