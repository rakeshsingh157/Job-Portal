<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'cuser_id_exists' => isset($_SESSION['cuser_id']),
    'cuser_id' => $_SESSION['cuser_id'] ?? null,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'php_input' => file_get_contents('php://input')
]);
?>