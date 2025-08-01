<?php
// profile_update.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require('config.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please sign in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and get the action from the request
    $action = isset($_POST['action']) ? htmlspecialchars($_POST['action']) : '';

    switch ($action) {
        case 'add_experience':
            $company_name = isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : '';
            $postion = isset($_POST['postion']) ? htmlspecialchars($_POST['postion']) : '';
            $date = isset($_POST['date']) ? htmlspecialchars($_POST['date']) : '';

            // Validate inputs
            if (empty($company_name) || empty($postion) || empty($date)) {
                http_response_code(400);
                echo json_encode(['error' => 'Company name, position, and date are required.']);
                exit();
            }

            // Prepare and execute the insert statement
            $stmt = $conn->prepare("INSERT INTO experience (userId, company_name, postion, date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $company_name, $postion, $date);

            if ($stmt->execute()) {
                // Get the last inserted ID to return to the client
                $new_id = $stmt->insert_id;
                echo json_encode(['success' => true, 'message' => 'Experience added successfully.', 'id' => $new_id]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
            break;

        case 'delete_experience':
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            if ($id === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Experience ID is required.']);
                exit();
            }

            // Ensure the user owns the experience record before deleting
            $stmt = $conn->prepare("DELETE FROM experience WHERE id = ? AND userId = ?");
            $stmt->bind_param("ii", $id, $user_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Experience deleted successfully.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Experience not found or you do not have permission to delete it.']);
                }
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
            break;

        case 'add_education':
            $instude_name = isset($_POST['instude_name']) ? htmlspecialchars($_POST['instude_name']) : '';
            $class = isset($_POST['class']) ? htmlspecialchars($_POST['class']) : '';
            $years = isset($_POST['years']) ? htmlspecialchars($_POST['years']) : '';
            $percentage = isset($_POST['percentage']) ? htmlspecialchars($_POST['percentage']) : '';
            
            // Validate inputs
            if (empty($instude_name) || empty($class) || empty($years)) {
                http_response_code(400);
                echo json_encode(['error' => 'Institute name, class, and years are required.']);
                exit();
            }

            // Prepare and execute the insert statement
            $stmt = $conn->prepare("INSERT INTO education (userId, instude_name, class, years, percentage) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $instude_name, $class, $years, $percentage);

            if ($stmt->execute()) {
                // Get the last inserted ID
                $new_id = $stmt->insert_id;
                echo json_encode(['success' => true, 'message' => 'Education added successfully.', 'id' => $new_id]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
            break;

        case 'delete_education':
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

            if ($id === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Education ID is required.']);
                exit();
            }

            // Ensure the user owns the education record before deleting
            $stmt = $conn->prepare("DELETE FROM education WHERE id = ? AND userId = ?");
            $stmt->bind_param("ii", $id, $user_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Education deleted successfully.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Education not found or you do not have permission to delete it.']);
                }
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action specified.']);
            break;
    }
} else {
    // Handle GET request to fetch experience and education data
    $response_data = [];
    $total_experience_years = 0;

    // Fetch Experience
    $stmt_exp = $conn->prepare("SELECT id, company_name, postion, date FROM experience WHERE userId = ? ORDER BY date DESC");
    $stmt_exp->bind_param("i", $user_id);
    $stmt_exp->execute();
    $result_exp = $stmt_exp->get_result();
    $experience_list = [];
    while ($row = $result_exp->fetch_assoc()) {
        $experience_list[] = $row;
        
        // Calculate total experience
        $dates = preg_split('/[\s—-]+/', $row['date']);
        if (count($dates) == 2) {
            $start_year = trim($dates[0]);
            $end_year = trim($dates[1]);

            if (strtolower($end_year) === 'present') {
                $end_year = date('Y');
            }

            if (is_numeric($start_year) && is_numeric($end_year)) {
                $total_experience_years += (int)$end_year - (int)$start_year;
            }
        }
    }
    $response_data['experience'] = $experience_list;
    $response_data['total_experience_years'] = $total_experience_years;
    $stmt_exp->close();

    // Fetch Education
    $stmt_edu = $conn->prepare("SELECT id, instude_name, class, years, percentage FROM education WHERE userId = ? ORDER BY years DESC");
    $stmt_edu->bind_param("i", $user_id);
    $stmt_edu->execute();
    $result_edu = $stmt_edu->get_result();
    $education_list = [];
    while ($row = $result_edu->fetch_assoc()) {
        $education_list[] = $row;
    }
    $response_data['education'] = $education_list;
    $stmt_edu->close();

    echo json_encode(['success' => true, 'data' => $response_data]);
}

$conn->close();
?>
