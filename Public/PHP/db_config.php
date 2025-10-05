<?php

// Database credentials from your query
define('DB_SERVER', 'database-1.chcyc88wcx2l.eu-north-1.rds.amazonaws.com');
define('DB_USERNAME', 'admin'); 
define('DB_PASSWORD', 'DBpicshot'); 
define('DB_NAME', 'jobp_db');

// Establish the database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check for connection errors
if ($conn->connect_error) {
    // Return a JSON error message for API requests
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

?>
