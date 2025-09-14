<?php
// profile_update.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require('config.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please sign in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle POST requests for adding/deleting data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_experience':
            $company_name = htmlspecialchars($_POST['company_name'] ?? '');
            $postion = htmlspecialchars($_POST['postion'] ?? '');
            $date = htmlspecialchars($_POST['date'] ?? '');

            if (empty($company_name) || empty($postion) || empty($date)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'All experience fields are required.']);
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO experience (userId, company_name, postion, date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $company_name, $postion, $date);
            break;

        case 'delete_experience':
            $id = intval($_POST['id'] ?? 0);
            if ($id === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid Experience ID.']);
                exit();
            }
            $stmt = $conn->prepare("DELETE FROM experience WHERE id = ? AND userId = ?");
            $stmt->bind_param("ii", $id, $user_id);
            break;

        case 'add_education':
            $instude_name = htmlspecialchars($_POST['instude_name'] ?? '');
            $class = htmlspecialchars($_POST['class'] ?? '');
            $years = htmlspecialchars($_POST['years'] ?? '');
            $percentage = htmlspecialchars($_POST['percentage'] ?? '');
            
            if (empty($instude_name) || empty($class) || empty($years)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Institute, class, and years are required.']);
                exit();
            }
            $stmt = $conn->prepare("INSERT INTO education (userId, instude_name, class, years, percentage) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $instude_name, $class, $years, $percentage);
            break;

        case 'delete_education':
            $id = intval($_POST['id'] ?? 0);
            if ($id === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid Education ID.']);
                exit();
            }
            $stmt = $conn->prepare("DELETE FROM education WHERE id = ? AND userId = ?");
            $stmt->bind_param("ii", $id, $user_id);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
            exit;
    }

    // Execute the prepared statement
    if ($stmt->execute()) {
        $message = str_contains($action, 'delete') ? 'Entry deleted successfully.' : 'Entry added successfully.';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Handle GET request to fetch experience and education data
$response_data = [];

// Fetch Experience
$stmt_exp = $conn->prepare("SELECT id, company_name, postion, `date` FROM experience WHERE userId = ? ORDER BY id DESC");
$stmt_exp->bind_param("i", $user_id);
$stmt_exp->execute();
$result_exp = $stmt_exp->get_result();
$response_data['experience'] = $result_exp->fetch_all(MYSQLI_ASSOC);
$stmt_exp->close();

// Fetch Education
$stmt_edu = $conn->prepare("SELECT id, instude_name, class, years, percentage FROM education WHERE userId = ? ORDER BY id DESC");
$stmt_edu->bind_param("i", $user_id);
$stmt_edu->execute();
$result_edu = $stmt_edu->get_result();
$response_data['education'] = $result_edu->fetch_all(MYSQLI_ASSOC);
$stmt_edu->close();

echo json_encode(['success' => true, 'data' => $response_data]);

$conn->close();
?>
