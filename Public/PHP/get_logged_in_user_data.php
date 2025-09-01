<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'db_config.php';

$response = [
    'success' => false,
    'profile_url' => 'https://placehold.co/150x150/png?text=P',
    'location' => 'N/A',
    'error' => 'No user logged in.'
];

// Check if a regular user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT profile_url, address FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $response['success'] = true;
        $response['profile_url'] = !empty($row['profile_url']) ? htmlspecialchars($row['profile_url']) : $response['profile_url'];
        $response['location'] = !empty($row['address']) ? htmlspecialchars($row['address']) : $response['location'];
        unset($response['error']);
    } else {
        $response['error'] = 'Logged-in user not found in database.';
    }
    $stmt->close();
}
// Check if a company user is logged in
elseif (isset($_SESSION['cuser_id'])) {
    $cuserId = $_SESSION['cuser_id'];
    $stmt = $conn->prepare("SELECT profile_photo, headquarter FROM cuser WHERE id = ?");
    $stmt->bind_param("i", $cuserId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $response['success'] = true;
        $response['profile_url'] = !empty($row['profile_photo']) ? htmlspecialchars($row['profile_photo']) : $response['profile_url'];
        $response['location'] = !empty($row['headquarter']) ? htmlspecialchars($row['headquarter']) : $response['location'];
        unset($response['error']);
    } else {
        $response['error'] = 'Logged-in company not found in database.';
    }
    $stmt->close();
}

$conn->close();
echo json_encode($response);
?>