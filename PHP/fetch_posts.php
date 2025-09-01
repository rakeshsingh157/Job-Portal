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

if ($user_id) {
    $current_user_type = 'user';
} elseif ($company_id) {
    $current_user_type = 'company';
}

$response = [
    'posts' => [],
    'currentUser' => [
        'id' => $user_id,
        'company_id' => $company_id,
        'type' => $current_user_type
    ],
    'message' => [
        'text' => '',
        'type' => ''
    ]
];

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post']) && isset($_POST['post_id'])) {
    $post_id = $conn->real_escape_string($_POST['post_id']);
    
    $check_sql = "SELECT * FROM posts WHERE id = '$post_id' AND ";
    if ($current_user_type == 'user') {
        $check_sql .= "user_id = '$user_id' AND user_type = 'user'";
    } else {
        $check_sql .= "company_id = '$company_id' AND user_type = 'company'";
    }
    
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        // Delete related images and comments first
        $delete_images_sql = "DELETE FROM post_images WHERE post_id = '$post_id'";
        $conn->query($delete_images_sql);
        
        $delete_comments_sql = "DELETE FROM post_comments WHERE post_id = '$post_id'";
        $conn->query($delete_comments_sql);
        
        // Delete the post
        $delete_post_sql = "DELETE FROM posts WHERE id = '$post_id'";
        if ($conn->query($delete_post_sql)) {
            $response['message']['text'] = "Post deleted successfully!";
            $response['message']['type'] = "success";
        } else {
            $response['message']['text'] = "Error deleting post: " . $conn->error;
            $response['message']['type'] = "error";
        }
    } else {
        $response['message']['text'] = "You don't have permission to delete this post.";
        $response['message']['type'] = "error";
    }
    
    echo json_encode($response);
    $conn->close();
    exit;
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment']) && isset($_POST['post_id']) && isset($_POST['comment_text'])) {
    $post_id = $conn->real_escape_string($_POST['post_id']);
    $comment_text = trim($conn->real_escape_string($_POST['comment_text']));
    
    if (empty($comment_text)) {
        $response['message']['text'] = "Comment cannot be empty!";
        $response['message']['type'] = "error";
    } else {
        if ($current_user_type == 'user') {
            $insert_sql = "INSERT INTO post_comments (post_id, user_id, user_type, comment) 
                           VALUES ('$post_id', '$user_id', 'user', '$comment_text')";
        } else {
            $insert_sql = "INSERT INTO post_comments (post_id, company_id, user_type, comment) 
                           VALUES ('$post_id', '$company_id', 'company', '$comment_text')";
        }
        
        if ($conn->query($insert_sql)) {
            $response['message']['text'] = "Comment added successfully!";
            $response['message']['type'] = "success";
        } else {
            $response['message']['text'] = "Error adding comment: " . $conn->error;
            $response['message']['type'] = "error";
        }
    }
    
    echo json_encode($response);
    $conn->close();
    exit;
}

// Handle search requests for the search dropdown
if (isset($_GET['search_query'])) {
    $search_query = trim($conn->real_escape_string($_GET['search_query']));
    $results = [];

    if (strpos($search_query, '@') === 0) {
        // Search for users or companies
        $clean_query = substr($search_query, 1);
        $search_term = "%{$clean_query}%";

        // Search users
        $user_sql = "SELECT id, first_name, last_name, profile_url FROM users WHERE first_name LIKE ? OR last_name LIKE ?";
        $stmt_user = $conn->prepare($user_sql);
        $stmt_user->bind_param("ss", $search_term, $search_term);
        $stmt_user->execute();
        $user_result = $stmt_user->get_result();
        while ($row = $user_result->fetch_assoc()) {
            $results[] = [
                'type' => 'user',
                'id' => $row['id'],
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'profile_url' => $row['profile_url']
            ];
        }
        
        // Search companies
        $company_sql = "SELECT id, company_name, profile_photo FROM cuser WHERE company_name LIKE ?";
        $stmt_company = $conn->prepare($company_sql);
        $stmt_company->bind_param("s", $search_term);
        $stmt_company->execute();
        $company_result = $stmt_company->get_result();
        while ($row = $company_result->fetch_assoc()) {
            $results[] = [
                'type' => 'company',
                'id' => $row['id'],
                'name' => $row['company_name'],
                'profile_url' => $row['profile_photo']
            ];
        }

    } else {
        // Search for posts (for the dropdown preview)
        $search_term = "%{$search_query}%";
        $post_sql = "SELECT id, content FROM posts WHERE content LIKE ?";
        $stmt_post = $conn->prepare($post_sql);
        $stmt_post->bind_param("s", $search_term);
        $stmt_post->execute();
        $post_result = $stmt_post->get_result();
        while ($row = $post_result->fetch_assoc()) {
            $results[] = [
                'type' => 'post',
                'id' => $row['id'],
                'name' => 'Post: ' . substr($row['content'], 0, 50) . '...'
            ];
        }
    }

    echo json_encode(['results' => $results]);
    $conn->close();
    exit;
}

// Main logic to fetch all posts or filtered posts
$sql = "SELECT p.*, 
               u.first_name, u.last_name, u.profile_url as user_profile,
               c.company_name, c.profile_photo as company_profile
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id AND p.user_type = 'user'
        LEFT JOIN cuser c ON p.company_id = c.id AND p.user_type = 'company'";

if (isset($_GET['post_search'])) {
    $search_term = '%' . $conn->real_escape_string($_GET['post_search']) . '%';
    $sql .= " WHERE p.content LIKE '$search_term'";
}

$sql .= " ORDER BY p.created_at DESC";

$result = $conn->query($sql);
$posts = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $post_id = $row['id'];
        
        // Get images for this post
        $images_sql = "SELECT image_url FROM post_images WHERE post_id = '$post_id' ORDER BY id";
        $images_result = $conn->query($images_sql);
        $images = [];
        if ($images_result->num_rows > 0) {
            while ($image_row = $images_result->fetch_assoc()) {
                $images[] = $image_row['image_url'];
            }
        }
        
        // Get comments for this post
        $comments_sql = "SELECT pc.*, 
                                u.first_name, u.last_name, u.profile_url as user_profile,
                                c.company_name, c.profile_photo as company_profile
                         FROM post_comments pc
                         LEFT JOIN users u ON pc.user_id = u.id AND pc.user_type = 'user'
                         LEFT JOIN cuser c ON pc.company_id = c.id AND pc.user_type = 'company'
                         WHERE pc.post_id = '$post_id'
                         ORDER BY pc.created_at ASC";
        
        $comments_result = $conn->query($comments_sql);
        $comments = [];
        if ($comments_result->num_rows > 0) {
            while ($comment_row = $comments_result->fetch_assoc()) {
                $comments[] = $comment_row;
            }
        }
        
        $row['images'] = $images;
        $row['comments'] = $comments;
        $posts[] = $row;
    }
}

$response['posts'] = $posts;

echo json_encode($response);
$conn->close();
?>