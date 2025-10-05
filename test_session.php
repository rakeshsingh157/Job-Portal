<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Session Debug Info:\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data: ";
print_r($_SESSION);
echo "\n";

if (!isset($_SESSION['cuser_id'])) {
    echo "No company user session found!\n";
    echo "Available session keys: " . implode(', ', array_keys($_SESSION)) . "\n";
} else {
    echo "Company User ID: " . $_SESSION['cuser_id'] . "\n";
    
    // Test the company profile fetch
    require_once 'PHP/config.php';
    $cuserId = $_SESSION['cuser_id'];
    
    $stmt = $conn->prepare("SELECT company_name, email, phone_number, headquarter, industry, company_type, website, overview, profile_photo, founded_year FROM cuser WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $cuserId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $profile = $result->fetch_assoc();
            echo "Profile found: ";
            print_r($profile);
        } else {
            echo "No profile found for user ID: " . $cuserId . "\n";
        }
        $stmt->close();
    } else {
        echo "Failed to prepare statement: " . $conn->error . "\n";
    }
    $conn->close();
}
?>