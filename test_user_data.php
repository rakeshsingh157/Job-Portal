<?php
// Test file to check user data retrieval - REMOVE THIS FILE AFTER TESTING
session_start();
require_once 'PHP/config.php';

echo "<h2>Session Debug:</h2>";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'not set') . "<br>";
echo "Session cuser_id: " . ($_SESSION['cuser_id'] ?? 'not set') . "<br>";

echo "<h2>Database Connection:</h2>";
if ($conn) {
    echo "Connected successfully<br>";
} else {
    echo "Connection failed<br>";
}

// Test the API directly
echo "<h2>API Response:</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/SyWD/PHP/get_user_data.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIESESSION, true);

// Copy session cookies
$cookies = [];
foreach ($_COOKIE as $name => $value) {
    $cookies[] = $name . '=' . $value;
}
if (!empty($cookies)) {
    curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookies));
}

$response = curl_exec($ch);
curl_close($ch);

echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Also test database directly if user is logged in
if (isset($_SESSION['user_id'])) {
    echo "<h2>Direct Database Query (Users):</h2>";
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT profile_url, address FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "<pre>" . print_r($row, true) . "</pre>";
        } else {
            echo "No user found with ID: $userId<br>";
        }
        $stmt->close();
    }
} elseif (isset($_SESSION['cuser_id'])) {
    echo "<h2>Direct Database Query (Company):</h2>";
    $cuserId = $_SESSION['cuser_id'];
    $stmt = $conn->prepare("SELECT profile_photo, headquarter FROM cuser WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $cuserId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "<pre>" . print_r($row, true) . "</pre>";
        } else {
            echo "No company user found with ID: $cuserId<br>";
        }
        $stmt->close();
    }
}

$conn->close();
?>