<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

include 'config.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$company_id = isset($_SESSION['cuser_id']) ? $_SESSION['cuser_id'] : null;

if (!$user_id && !$company_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    // Handle post deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_post') {
        if (!isset($_POST['post_id'])) {
            throw new Exception("Post ID not provided");
        }
        
        $post_id = (int)$_POST['post_id'];
        
        // Verify ownership using prepared statement
        $check_sql = "SELECT id FROM posts WHERE id = ? AND ";
        if ($user_id) {
            $check_sql .= "user_id = ? AND user_type = 'user'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $post_id, $user_id);
        } else {
            $check_sql .= "company_id = ? AND user_type = 'company'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $post_id, $company_id);
        }
        
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Use transactions for data integrity
            $conn->begin_transaction();
            try {
                // Delete related images
                $delete_images_stmt = $conn->prepare("DELETE FROM post_images WHERE post_id = ?");
                $delete_images_stmt->bind_param("i", $post_id);
                $delete_images_stmt->execute();
                
                // Delete related comments
                $delete_comments_stmt = $conn->prepare("DELETE FROM post_comments WHERE post_id = ?");
                $delete_comments_stmt->bind_param("i", $post_id);
                $delete_comments_stmt->execute();
                
                // Delete the post
                $delete_post_stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
                $delete_post_stmt->bind_param("i", $post_id);
                
                if ($delete_post_stmt->execute()) {
                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = "Post deleted successfully!";
                } else {
                    $conn->rollback();
                    throw new Exception("Error deleting post: " . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
        } else {
            throw new Exception("You don't have permission to delete this post.");
        }
        
        echo json_encode($response);
        exit;
    }

    // Handle post editing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_post') {
        if (!isset($_POST['post_id']) || !isset($_POST['content'])) {
            throw new Exception("Post ID or content not provided");
        }
        
        $post_id = (int)$_POST['post_id'];
        $content = $_POST['content'];
        
        // Verify ownership
        $check_sql = "SELECT id FROM posts WHERE id = ? AND ";
        if ($user_id) {
            $check_sql .= "user_id = ? AND user_type = 'user'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $post_id, $user_id);
        } else {
            $check_sql .= "company_id = ? AND user_type = 'company'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $post_id, $company_id);
        }
        
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $update_sql = "UPDATE posts SET content = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $content, $post_id);
            
            if ($update_stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Post updated successfully!";
            } else {
                throw new Exception("Error updating post: " . $conn->error);
            }
        } else {
            throw new Exception("You don't have permission to edit this post.");
        }
        
        echo json_encode($response);
        exit;
    }

    // Handle comment operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_comment') {
            if (!isset($_POST['post_id']) || !isset($_POST['comment_text'])) {
                throw new Exception("Post ID or comment text not provided");
            }
            
            $post_id = (int)$_POST['post_id'];
            $comment_text = trim($_POST['comment_text']);
            
            if (empty($comment_text)) {
                throw new Exception("Comment cannot be empty!");
            }
            
            if ($user_id) {
                $insert_sql = "INSERT INTO post_comments (post_id, user_id, user_type, comment) 
                               VALUES (?, ?, 'user', ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iis", $post_id, $user_id, $comment_text);
            } else {
                $insert_sql = "INSERT INTO post_comments (post_id, company_id, user_type, comment) 
                               VALUES (?, ?, 'company', ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iis", $post_id, $company_id, $comment_text);
            }
            
            if ($insert_stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Comment added successfully!";
            } else {
                throw new Exception("Error adding comment: " . $conn->error);
            }
            
            echo json_encode($response);
            exit;
        }
        
        if ($_POST['action'] === 'delete_comment') {
            if (!isset($_POST['comment_id'])) {
                throw new Exception("Comment ID not provided");
            }
            
            $comment_id = (int)$_POST['comment_id'];
            
            // Verify ownership
            $check_sql = "SELECT id FROM post_comments WHERE id = ? AND ";
            if ($user_id) {
                $check_sql .= "user_id = ? AND user_type = 'user'";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $comment_id, $user_id);
            } else {
                $check_sql .= "company_id = ? AND user_type = 'company'";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $comment_id, $company_id);
            }
            
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $delete_sql = "DELETE FROM post_comments WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $comment_id);
                
                if ($delete_stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = "Comment deleted successfully!";
                } else {
                    throw new Exception("Error deleting comment: " . $conn->error);
                }
            } else {
                throw new Exception("You don't have permission to delete this comment.");
            }
            
            echo json_encode($response);
            exit;
        }
    }

    throw new Exception("Invalid request");
    
} catch (Exception $e) {
    error_log("Error in post_operations: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>