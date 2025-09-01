<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'db_config.php';

$userId = $_GET['user_id'] ?? '';
if (empty($userId)) {
    echo json_encode(['error' => 'user_id is required.']);
    exit;
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    // Fetch education data from education table
    $educationData = [];
    $eduStmt = $conn->prepare("SELECT * FROM education WHERE userId = ?");
    $eduStmt->bind_param("i", $userId);
    $eduStmt->execute();
    $eduResult = $eduStmt->get_result();
    while ($eduRow = $eduResult->fetch_assoc()) {
        $educationData[] = [
            'institute' => $eduRow['instude_name'],
            'degree' => $eduRow['class'],
            'years' => $eduRow['years'],
            'percentage' => $eduRow['percentage']
        ];
    }
    $eduStmt->close();
    $user['education'] = $educationData;

    // Fetch experience data from experience table
    $experienceData = [];
    $expStmt = $conn->prepare("SELECT * FROM experience WHERE userId = ?");
    $expStmt->bind_param("i", $userId);
    $expStmt->execute();
    $expResult = $expStmt->get_result();
    while ($expRow = $expResult->fetch_assoc()) {
        $experienceData[] = [
            'company' => $expRow['company_name'],
            'position' => $expRow['postion'],
            'date' => $expRow['date']
        ];
    }
    $expStmt->close();
    $user['experience'] = $experienceData;

    // Process skills and languages
    $user['skills'] = !empty($user['skills']) ? array_map('trim', explode(',', $user['skills'])) : [];
    $user['languages'] = !empty($user['language']) ? array_map('trim', explode(',', $user['language'])) : [];
    
    // Remove sensitive or unnecessary fields before sending
    unset($user['password_hash'], $user['country_code'], $user['phone_number'], $user['is_verified'], $user['test_pass'], $user['created_at']);

    echo json_encode($user);
} else {
    echo json_encode(['error' => 'User not found.']);
}

$stmt->close();
$conn->close();
?>