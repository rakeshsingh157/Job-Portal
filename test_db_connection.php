<?php
// Test database connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "Testing database connection...\n";

try {
    require('PHP/config.php');
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Database connection successful!\n";
    
    // Test if user session exists
    if (isset($_SESSION['user_id'])) {
        echo "User session exists: " . $_SESSION['user_id'] . "\n";
        
        // Test a simple query
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo "User found: " . print_r($user, true) . "\n";
        } else {
            echo "No user found with this ID\n";
        }
        $stmt->close();
    } else {
        echo "No user session found. Please log in first.\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>