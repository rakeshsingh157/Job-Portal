<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header('Content-Type: application/json');
session_start();

// Database connection
define('DB_SERVER', 'photostore.ct0go6um6tj0.ap-south-1.rds.amazonaws.com');
define('DB_USERNAME', 'admin'); 
define('DB_PASSWORD', 'DBpicshot'); 
define('DB_NAME', 'jobp_db');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$company_id = isset($_SESSION['cuser_id']) ? $_SESSION['cuser_id'] : null;
$current_user_type = null;
$current_user_profile = null;

if ($user_id) {
    $current_user_type = 'user';
    // Fetch user profile info
    $stmt = $conn->prepare("SELECT first_name, last_name, profile_url FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_user_profile = $row;
    }
} elseif ($company_id) {
    $current_user_type = 'company';
    // Fetch company profile info
    $stmt = $conn->prepare("SELECT company_name, profile_photo FROM cuser WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_user_profile = $row;
    }
}

if (!$current_user_type) {
    echo json_encode(['posts' => [], 'currentUser' => null, 'message' => 'Not logged in']);
    exit;
}

// Fetch posts by the current user
$sql = "SELECT p.*, 
               u.first_name, u.last_name, u.profile_url as user_profile,
               c.company_name, c.profile_photo as company_profile
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id AND p.user_type = 'user'
        LEFT JOIN cuser c ON p.company_id = c.id AND p.user_type = 'company'
        WHERE ";

if ($current_user_type === 'user') {
    $sql .= "p.user_id = ? AND p.user_type = 'user'";
} else {
    $sql .= "p.company_id = ? AND p.user_type = 'company'";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
if ($current_user_type === 'user') {
    $stmt->bind_param("i", $user_id);
} else {
    $stmt->bind_param("i", $company_id);
}

$stmt->execute();
$result = $stmt->get_result();
$posts = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $post_id = $row['id'];
        
        // Get images for this post
        $images_stmt = $conn->prepare("SELECT image_url FROM post_images WHERE post_id = ? ORDER BY id");
        $images_stmt->bind_param("i", $post_id);
        $images_stmt->execute();
        $images_result = $images_stmt->get_result();
        $images = [];
        while ($image_row = $images_result->fetch_assoc()) {
            $images[] = $image_row['image_url'];
        }
        $images_stmt->close();
        
        // Get comments for this post
        $comments_stmt = $conn->prepare("
            SELECT pc.*, 
                   u.first_name, u.last_name, u.profile_url as user_profile,
                   c.company_name, c.profile_photo as company_profile
            FROM post_comments pc
            LEFT JOIN users u ON pc.user_id = u.id AND pc.user_type = 'user'
            LEFT JOIN cuser c ON pc.company_id = c.id AND pc.user_type = 'company'
            WHERE pc.post_id = ?
            ORDER BY pc.created_at ASC
        ");
        $comments_stmt->bind_param("i", $post_id);
        $comments_stmt->execute();
        $comments_result = $comments_stmt->get_result();
        $comments = [];
        while ($comment_row = $comments_result->fetch_assoc()) {
            $comments[] = $comment_row;
        }
        $comments_stmt->close();
        
        $row['images'] = $images;
        $row['comments'] = $comments;
        $posts[] = $row;
    }
}
$stmt->close();

$response = [
    'posts' => $posts,
    'currentUser' => [
        'id' => $user_id,
        'company_id' => $company_id,
        'type' => $current_user_type,
        'profile' => $current_user_profile
    ]
];

echo json_encode($response);
$conn->close();
?>