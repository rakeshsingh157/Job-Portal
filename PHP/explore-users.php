<?php
// Filename: explore-users.php
// This file handles the specific search for users and companies.
header('Content-Type: application/json');

// Database connection
require_once('config.php');

$response = ['success' => false, 'message' => ''];

if ($conn->connect_error) {
    $response['message'] = "Database connection failed: " . $conn->connect_error;
    echo json_encode($response);
    exit();
}

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
// Remove the '@' from the beginning of the search term
$searchTerm = ltrim($searchTerm, '@');
$keyword = '%' . $searchTerm . '%';

$results = [];

// Search for users
$sql_users = "SELECT id, first_name, last_name, profile_url FROM users WHERE first_name LIKE ? OR last_name LIKE ?";
$stmt_users = $conn->prepare($sql_users);
if ($stmt_users) {
    $stmt_users->bind_param("ss", $keyword, $keyword);
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    while ($row = $result_users->fetch_assoc()) {
        $results[] = [
            'type' => 'user',
            'id' => $row['id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'profile_photo' => $row['profile_url']
        ];
    }
    $stmt_users->close();
}

// Search for companies
$sql_companies = "SELECT id, company_name, profile_photo FROM cuser WHERE company_name LIKE ?";
$stmt_companies = $conn->prepare($sql_companies);
if ($stmt_companies) {
    $stmt_companies->bind_param("s", $keyword);
    $stmt_companies->execute();
    $result_companies = $stmt_companies->get_result();
    while ($row = $result_companies->fetch_assoc()) {
        $results[] = [
            'type' => 'company',
            'id' => $row['id'],
            'name' => $row['company_name'],
            'profile_photo' => $row['profile_photo']
        ];
    }
    $stmt_companies->close();
}

$response['success'] = true;
$response['results'] = $results;

echo json_encode($response);

$conn->close();
?>
