<?php
header('Content-Type: application/json');
session_start();

require_once 'config.php';

$response = [
    'success' => false,
    'profile_url' => '',
    'location' => '',
    'photo' => '',
    'district' => '',
    'state' => '',
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
            $profile_url = $row['profile_url'] ?? 'https://placehold.co/36x36/000000/FFFFFF?text=U';
            $address = $row['address'] ?? 'City, District';
            
            $response['profile_url'] = $profile_url;
            $response['photo'] = $profile_url;
            $response['location'] = $address;
            
            // Parse address to get district and state if possible
            $address_parts = explode(',', $address);
            if (count($address_parts) >= 2) {
                $response['district'] = trim($address_parts[0]) ?: 'City';
                $response['state'] = trim($address_parts[1]) ?: 'District';
            } else {
                $response['district'] = 'City';
                $response['state'] = 'District';
            }
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
            $profile_photo = $row['profile_photo'] ?? 'https://placehold.co/36x36/000000/FFFFFF?text=C';
            $headquarter = $row['headquarter'] ?? 'City, State';
            
            $response['profile_url'] = $profile_photo;
            $response['photo'] = $profile_photo;
            $response['location'] = $headquarter;
            
            // Parse headquarter to get city and state if possible
            $location_parts = explode(',', $headquarter);
            if (count($location_parts) >= 2) {
                $response['district'] = trim($location_parts[0]) ?: 'City';
                $response['state'] = trim($location_parts[1]) ?: 'State';
            } else {
                $response['district'] = 'City';
                $response['state'] = 'State';
            }
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