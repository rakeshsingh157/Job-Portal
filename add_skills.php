<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');

require('config.php');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access. Please log in.']);
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_POST['score']) || !isset($_POST['skill_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing score or skill_name.']);
    exit;
}

$score = intval($_POST['score']);
$skill_name = htmlspecialchars($_POST['skill_name']);

const PASSING_SCORE = 7;

if ($score >= PASSING_SCORE) {
   
    $stmt = $conn->prepare("SELECT skills FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        $current_skills = $user['skills'] ? explode(',', $user['skills']) : [];
        if (!in_array($skill_name, $current_skills)) {
            $current_skills[] = $skill_name;
            $updated_skills = implode(',', $current_skills);
            
            $stmt = $conn->prepare("UPDATE users SET skills = ? WHERE id = ?");
            $stmt->bind_param("si", $updated_skills, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Skill added successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database update failed: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => true, 'message' => 'Skill already exists.']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found.']);
    }

} else {
    echo json_encode([
        'success' => false,
        'message' => "You need a score of at least " . PASSING_SCORE . " to add this skill. Please try again or choose another path."
    ]);
}
$conn->close();
?>
