
<?php
header('Content-Type: application/json');
session_start();

// Database connection
require_once('config.php');

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$response = ['success' => false, 'results' => []];

if (!isset($_GET['query']) || empty(trim($_GET['query']))) {
    $response['error'] = 'Empty search query';
    echo json_encode($response);
    exit();
}

$query = '%' . trim($_GET['query']) . '%';
$results = [];

// Search users
$user_sql = "SELECT id, first_name, last_name, profile_url, 'user' as type 
             FROM users 
             WHERE first_name LIKE ? OR last_name LIKE ? OR username LIKE ?
             LIMIT 5";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("sss", $query, $query, $query);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

while ($user = $user_result->fetch_assoc()) {
    $user['display_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $user['profile_pic'] = !empty($user['profile_url']) ? $user['profile_url'] : 'https://via.placeholder.com/50';
    $user['url'] = 'public/profile.html?user_id=' . $user['id'];
    $results[] = $user;
}
$user_stmt->close();

// Search companies
$company_sql = "SELECT id, company_name, profile_photo, 'company' as type 
                FROM cuser 
                WHERE company_name LIKE ? OR industry LIKE ? OR description LIKE ?
                LIMIT 5";
$company_stmt = $conn->prepare($company_sql);
$company_stmt->bind_param("sss", $query, $query, $query);
$company_stmt->execute();
$company_result = $company_stmt->get_result();

while ($company = $company_result->fetch_assoc()) {
    $company['display_name'] = $company['company_name'];
    $company['profile_pic'] = !empty($company['profile_photo']) ? $company['profile_photo'] : 'https://via.placeholder.com/50';
    $company['url'] = 'public/company-profile.html?cuser_id=' . $company['id'];
    $results[] = $company;
}
$company_stmt->close();

// Search posts
$post_sql = "SELECT p.id, p.content, p.created_at, 
                    COALESCE(u.first_name, c.company_name) as author_name,
                    COALESCE(u.profile_url, c.profile_photo) as author_pic,
                    CASE WHEN p.user_type = 'user' THEN 'user' ELSE 'company' END as author_type,
                    'post' as type
             FROM posts p
             LEFT JOIN users u ON p.user_id = u.id AND p.user_type = 'user'
             LEFT JOIN cuser c ON p.company_id = c.id AND p.user_type = 'company'
             WHERE p.content LIKE ?
             LIMIT 5";
$post_stmt = $conn->prepare($post_sql);
$post_stmt->bind_param("s", $query);
$post_stmt->execute();
$post_result = $post_stmt->get_result();

while ($post = $post_result->fetch_assoc()) {
    $post['display_name'] = 'Post by ' . $post['author_name'];
    $post['profile_pic'] = !empty($post['author_pic']) ? $post['author_pic'] : 'https://via.placeholder.com/50';
    $post['url'] = 'view-post.html?post_id=' . $post['id'];
    $results[] = $post;
}
$post_stmt->close();

$response['success'] = true;
$response['results'] = $results;

echo json_encode($response);
$conn->close();
?>
