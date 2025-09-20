<?php

session_start();
define('DB_SERVER', 'database-1.chcyc88wcx2l.eu-north-1.rds.amazonaws.com');
define('DB_USERNAME', 'admin'); 
define('DB_PASSWORD', 'DBpicshot'); 
define('DB_NAME', 'jobp_db');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return ['id' => $_SESSION['user_id'], 'type' => 'user'];
    } elseif (isset($_SESSION['cuser_id'])) {
        return ['id' => $_SESSION['cuser_id'], 'type' => 'company'];
    }
    return null;
}


function getUserDetails($id, $type) {
    global $conn;
    
    if ($type === 'user') {
        $stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name, profile_url as photo, is_verified FROM users WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT id, company_name as name, profile_photo as photo, cverified as is_verified FROM cuser WHERE id = ?");
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}


function getCurrentUserDetails() {
    global $conn;
    $currentUser = getCurrentUser();
    
    if (!$currentUser) {
        return null;
    }
    
    if ($currentUser['type'] === 'user') {
        $stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name, profile_url as photo, is_verified FROM users WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT id, company_name as name, profile_photo as photo, cverified as is_verified FROM cuser WHERE id = ?");
    }
    
    $stmt->bind_param("i", $currentUser['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        $user['type'] = $currentUser['type'];
    }
    
    return $user;
}


function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
?>