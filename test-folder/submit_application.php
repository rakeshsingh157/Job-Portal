<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'database-1.chcyc88wcx2l.eu-north-1.rds.amazonaws.com');
define('DB_USER', 'admin');
define('DB_PASS', 'DBpicshot');
define('DB_NAME', 'jobp_db');

function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error);
        http_response_code(500);
        echo json_encode(['message' => 'Internal server error: Database connection failed.']);
        exit();
    }
    return $conn;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

   
    $userId = $_SESSION['user_id'] ?? null;
   
    $education = $data['education'] ?? null;
    $workExperience = $data['workExperience'] ?? null;
    $jobField = $data['jobField'] ?? null;
    $employmentType = $data['employmentType'] ?? null;
    $shiftType = $data['shiftType'] ?? null;
    $minHours = $data['minHours'] ?? null;
    $maxHours = $data['maxHours'] ?? null;
    $quizScore = $data['quizScore'] ?? 0;

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['message' => 'User not authenticated. Please log in first.']);
        exit();
    }
    if (empty($education) || empty($jobField) || empty($employmentType)) {
        http_response_code(400);
        echo json_encode(['message' => 'Education, Job Field, and Employment Type are required.']);
        exit();
    }

    $conn = getDbConnection();

    $testPassStatus = ($quizScore >= 7) ? 'passed' : 'failed';

    $finalEmploymentType = $employmentType;
    $finalShiftType = null;
    $finalHours = null;

    if ($employmentType === 'Full Time') {
        $finalShiftType = $shiftType;
    } elseif ($employmentType === 'Part Time') {
        if ($minHours && $maxHours) {
            $finalHours = "$minHours-$maxHours";
        } elseif ($minHours) {
            $finalHours = "$minHours";
        } elseif ($maxHours) {
            $finalHours = "$maxHours"; 
        }
    }

    $stmt = $conn->prepare("UPDATE users SET
                            education = ?,
                            work_experience = ?,
                            job_field = ?,
                            employment_type = ?,
                            shift_type = ?,
                            part_time_hours = ?,
                            test_pass = ?
                            WHERE id = ?");

    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['message' => 'Database statement preparation failed.']);
        exit();
    }

    $stmt->bind_param("sssssssi",
        $education,
        $workExperience,
        $jobField,
        $finalEmploymentType,
        $finalShiftType,
        $finalHours,
        $testPassStatus,
        $userId
    );

    try {
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Application details updated successfully!', 'test_pass_status' => $testPassStatus]);
            } else {
                http_response_code(200);
                echo json_encode(['success' => false, 'message' => 'No new application details were updated (user ID might not exist or data was unchanged).', 'test_pass_status' => $testPassStatus]);
            }
        } else {
            error_log("Execute failed: " . $stmt->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update application details. Database error.']);
        }
    } finally {
        $stmt->close();
        $conn->close();
    }

} else {
    http_response_code(405);
    echo json_encode(['message' => 'Only POST requests are allowed.']);
}
?>
