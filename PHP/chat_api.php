<?php

require_once 'config2.php';

header('Content-Type: application/json');


function jsonError($message) {
    echo json_encode(['error' => $message]);
    exit;
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    jsonError('Not authenticated');
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_conversations':
        getConversations($currentUser);
        break;
    case 'get_messages':
        $conversationId = $_GET['conversation_id'] ?? 0;
        if (!$conversationId) {
            jsonError('Conversation ID is required');
        }
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
    case 'get_user_details':
        $id = $_GET['id'] ?? 0;
        $type = $_GET['type'] ?? '';
        getUserDetailsAPI($id, $type);
        break;
    default:
        jsonError('Invalid action');
        break;
}

function getCurrentUserDetailsAPI() {
    $currentUser = getCurrentUserDetails();
    if ($currentUser) {
        $currentUser['photo'] = $currentUser['photo'] ?: 'https://placehold.co/40x40/7A69FF/FFFFFF?text=' . substr($currentUser['name'], 0, 1);
        $currentUser['is_verified'] = (bool)$currentUser['is_verified'];
        echo json_encode($currentUser);
    } else {
        jsonError('User not found');
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
        jsonError('User not found');
    }
}

function getConversations($currentUser) {
    global $conn;
    $conversations = [];

    if ($currentUser['type'] === 'user') {
        $stmt = $conn->prepare("
            SELECT c.id, c.conversation_type, c.user1_id, c.user2_id, c.company1_id, c.company2_id, MAX(m.timestamp) as last_message_time
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            WHERE (
                (c.conversation_type = 'user_to_user' AND (c.user1_id = ? OR c.user2_id = ?)) OR
                (c.conversation_type = 'user_to_company' AND c.user1_id = ?)
            )
            GROUP BY c.id
            ORDER BY last_message_time DESC
        ");
        $stmt->bind_param("iii", $currentUser['id'], $currentUser['id'], $currentUser['id']);
    } else if ($currentUser['type'] === 'company') {
        $stmt = $conn->prepare("
            SELECT c.id, c.conversation_type, c.user1_id, c.user2_id, c.company1_id, c.company2_id, MAX(m.timestamp) as last_message_time
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            WHERE (
                (c.conversation_type = 'company_to_company' AND (c.company1_id = ? OR c.company2_id = ?)) OR
                (c.conversation_type = 'user_to_company' AND c.company1_id = ?)
            )
            GROUP BY c.id
            ORDER BY last_message_time DESC
        ");
        $stmt->bind_param("iii", $currentUser['id'], $currentUser['id'], $currentUser['id']);
    } else {
        echo json_encode([]);
        return;
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $otherId = null;
        $otherType = '';
        switch ($row['conversation_type']) {
            case 'user_to_user':
                $otherId = ($row['user1_id'] == $currentUser['id']) ? $row['user2_id'] : $row['user1_id'];
                $otherType = 'user';
                break;
            case 'user_to_company':
                if ($currentUser['type'] === 'user') {
                    $otherId = $row['company1_id'];
                    $otherType = 'company';
                } else {
                    $otherId = $row['user1_id'];
                    $otherType = 'user';
                }
                break;
            case 'company_to_company':
                $otherId = ($row['company1_id'] == $currentUser['id']) ? $row['company2_id'] : $row['company1_id'];
                $otherType = 'company';
                break;
        }
        if ($otherId && $otherType) {
            $otherUser = getUserDetails($otherId, $otherType);
            if ($otherUser) {
                $conversations[] = [
                    'conversation_id' => $row['id'],
                    'other_user' => [
                        'id' => $otherUser['id'],
                        'name' => $otherUser['name'],
                        'photo' => $otherUser['photo'] ?: 'https://placehold.co/45x45/7A69FF/FFFFFF?text=' . substr($otherUser['name'], 0, 1),
                        'is_verified' => (bool)$otherUser['is_verified'],
                        'type' => $otherType
                    ],
                    'last_message_time' => $row['last_message_time']
                ];
            }
        }
    }
    echo json_encode($conversations);
}

function getMessages($conversationId, $currentUser) {
    global $conn;
    

    $conversationAccess = false;
    $stmt = $conn->prepare("
        SELECT id, conversation_type, user1_id, user2_id, company1_id, company2_id 
        FROM conversations 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        

        switch ($row['conversation_type']) {
            case 'user_to_user':
                if ($currentUser['type'] === 'user' && 
                    ($row['user1_id'] == $currentUser['id'] || $row['user2_id'] == $currentUser['id'])) {
                    $conversationAccess = true;
                }
                break;
            case 'user_to_company':
                if (($currentUser['type'] === 'user' && $row['user1_id'] == $currentUser['id']) ||
                    ($currentUser['type'] === 'company' && $row['company1_id'] == $currentUser['id'])) {
                    $conversationAccess = true;
                }
                break;
            case 'company_to_company':
                if ($currentUser['type'] === 'company' && 
                    ($row['company1_id'] == $currentUser['id'] || $row['company2_id'] == $currentUser['id'])) {
                    $conversationAccess = true;
                }
                break;
        }
    }
    
    if (!$conversationAccess) {
        jsonError('Conversation not found or access denied');
    }
    

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
    

    $stmt = $conn->prepare("
        UPDATE messages SET is_read = TRUE 
        WHERE conversation_id = ? 
        AND sender_type != ? 
        AND sender_id != ?
    ");
    $stmt->bind_param("isi", $conversationId, $currentUser['type'], $currentUser['id']);
    $stmt->execute();
    
    echo json_encode($messages);
}

function sendMessage($data, $currentUser) {
    global $conn;
    
    $receiverId = $data['receiver_id'] ?? 0;
    $receiverType = $data['receiver_type'] ?? '';
    $message = trim($data['message'] ?? '');
    
    if (empty($message) || !$receiverId) {
        jsonError('Message or receiver ID is empty');
    }
    

    $conversationId = null;
    $stmt = null;
    

    $conversationType = '';
    if ($currentUser['type'] === 'user' && $receiverType === 'user') {
        $conversationType = 'user_to_user';
    } elseif ($currentUser['type'] === 'company' && $receiverType === 'company') {
        $conversationType = 'company_to_company';
    } else {
        $conversationType = 'user_to_company';
    }
    
    if ($conversationType === 'user_to_user') {
        $stmt = $conn->prepare("
            SELECT id FROM conversations 
            WHERE conversation_type = 'user_to_user' 
            AND ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))
        ");
        $stmt->bind_param("iiii", $currentUser['id'], $receiverId, $receiverId, $currentUser['id']);
    } elseif ($conversationType === 'company_to_company') {
        $stmt = $conn->prepare("
            SELECT id FROM conversations 
            WHERE conversation_type = 'company_to_company' 
            AND ((company1_id = ? AND company2_id = ?) OR (company1_id = ? AND company2_id = ?))
        ");
        $stmt->bind_param("iiii", $currentUser['id'], $receiverId, $receiverId, $currentUser['id']);
    } else {
        if ($currentUser['type'] === 'user') {
            $stmt = $conn->prepare("
                SELECT id FROM conversations 
                WHERE conversation_type = 'user_to_company' 
                AND user1_id = ? AND company1_id = ?
            ");
            $stmt->bind_param("ii", $currentUser['id'], $receiverId);
        } else {
            $stmt = $conn->prepare("
                SELECT id FROM conversations 
                WHERE conversation_type = 'user_to_company' 
                AND user1_id = ? AND company1_id = ?
            ");
            $stmt->bind_param("ii", $receiverId, $currentUser['id']);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conversationId = $row['id'];
    } else {

        if ($conversationType === 'user_to_user') {
            $stmt = $conn->prepare("
                INSERT INTO conversations (user1_id, user2_id, conversation_type) 
                VALUES (?, ?, 'user_to_user')
            ");
            if ($currentUser['id'] < $receiverId) {
                $stmt->bind_param("ii", $currentUser['id'], $receiverId);
            } else {
                $stmt->bind_param("ii", $receiverId, $currentUser['id']);
            }
        } elseif ($conversationType === 'company_to_company') {
            $stmt = $conn->prepare("
                INSERT INTO conversations (company1_id, company2_id, conversation_type) 
                VALUES (?, ?, 'company_to_company')
            ");
            if ($currentUser['id'] < $receiverId) {
                $stmt->bind_param("ii", $currentUser['id'], $receiverId);
            } else {
                $stmt->bind_param("ii", $receiverId, $currentUser['id']);
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO conversations (user1_id, company1_id, conversation_type) 
                VALUES (?, ?, 'user_to_company')
            ");
            if ($currentUser['type'] === 'user') {
                $stmt->bind_param("ii", $currentUser['id'], $receiverId);
            } else {
                $stmt->bind_param("ii", $receiverId, $currentUser['id']);
            }
        }
        
        if ($stmt->execute()) {
            $conversationId = $conn->insert_id;
        } else {
            jsonError('Failed to create a new conversation');
        }
    }
    

    $stmt = $conn->prepare("
        INSERT INTO messages (conversation_id, sender_type, sender_id, message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isis", $conversationId, $currentUser['type'], $currentUser['id'], $message);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message_id' => $conn->insert_id]);
    } else {
        jsonError('Failed to insert message');
    }
}

function searchUsers($query, $currentUser) {
    global $conn;
    
    $searchTerm = "%$query%";
    $results = [];
    

    $stmt = $conn->prepare("
        (SELECT id, CONCAT(first_name, ' ', last_name) as name, profile_url as photo, is_verified, 'user' as type
        FROM users 
        WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
        UNION
        (SELECT id, company_name as name, profile_photo as photo, cverified as is_verified, 'company' as type
        FROM cuser 
        WHERE company_name LIKE ? OR email LIKE ?)
    ");
    
    $stmt->bind_param("sssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {

        if ($row['type'] === $currentUser['type'] && $row['id'] == $currentUser['id']) {
            continue;
        }
        
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