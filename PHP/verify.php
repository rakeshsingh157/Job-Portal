<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'photostore.ct0go6um6tj0.ap-south-1.rds.amazonaws.com');
define('DB_USER', 'admin');
define('DB_PASS', 'DBpicshot');
define('DB_NAME', 'jobp_db');

function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error);
        die('Database connection failed.');
    }
    return $conn;
}

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT test_pass FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($testPassStatus);
    $stmt->fetch();
    $stmt->close();
    $conn->close();

    if ($testPassStatus === 'passed' || $testPassStatus === 'null') {
        header('Location: ../profile.html'); 
        exit();
    } else {
        header('Location: ../test-folder/index.html'); 
        exit();
    }
} 
else if (isset($_SESSION['cuser_id'])) {
    $userId = $_SESSION['cuser_id'];
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT is_verified FROM cuser WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($testPassStatus);
    $stmt->fetch();
    $stmt->close();
    $conn->close();

    if ($testPassStatus === True) {
        header('Location: ../profile.html'); 
        exit();
    } else {
        header('Location: ../company-test/index.html'); 
        exit();
    }
} else {
    header('Location: ../index.php'); 
    exit();
}
?>