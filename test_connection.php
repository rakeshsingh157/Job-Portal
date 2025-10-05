<?php
require_once 'PHP/config.php';

echo "Database connection test:\n";
if ($conn->connect_error) {
    echo "Failed: " . $conn->connect_error . "\n";
} else {
    echo "Success!\n";
    
    // Test if session table exists and if we can check for company user
    echo "Testing company user session...\n";
    
    // Check if we have any company users in the database
    $result = $conn->query("SELECT COUNT(*) as count FROM cuser LIMIT 1");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Company users table accessible. Count: " . $row['count'] . "\n";
    } else {
        echo "Error accessing cuser table: " . $conn->error . "\n";
    }
}

$conn->close();
?>