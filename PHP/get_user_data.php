<?php
header('Content-Type: application/json');
session_start();


require_once 'config.php';

$response = [
    'success' => false,
    'profile_url' => '',
    'location' => '',
    'error' => ''
];


if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT profile_url, address FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $response['success'] = true;
            $response['profile_url'] = $row['profile_url'] ?? '';
            $response['location'] = $row['address'] ?? '';
        } else {
            $response['error'] = 'User not found.';
        }
        $stmt->close();
    } else {
        $response['error'] = 'Failed to prepare statement for users.';
    }
} elseif (isset($_SESSION['cuser_id'])) {
    $cuserId = $_SESSION['cuser_id'];
    $stmt = $conn->prepare("SELECT profile_photo, headquarter FROM cuser WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $cuserId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $response['success'] = true;
            $response['profile_url'] = $row['profile_photo'] ?? '';
            $response['location'] = $row['headquarter'] ?? '';
        } else {
            $response['error'] = 'Company user not found.';
        }
        $stmt->close();
    } else {
        $response['error'] = 'Failed to prepare statement for cuser.';
    }
} else {
    $response['error'] = 'No user is logged in.';
}

$conn->close();

echo json_encode($response);
?>