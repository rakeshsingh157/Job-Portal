<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Assume config.php exists and provides a $conn variable for the database connection.
require_once 'config.php';

// Define the API key for imgBB
define('IMGBB_API_KEY', '8f23d9f5d1b5960647ba5942af8a1523');

// Function to handle database connection and error logging
function getDbConnection() {
    global $conn;
    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error: Database connection failed.']);
        exit();
    }
    return $conn;
}

// Check if a company user is logged in
if (!isset($_SESSION['cuser_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit();
}

$cuserId = $_SESSION['cuser_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Retrieve company profile and job posts
        $conn = getDbConnection();
        $profile = null;
        $jobs = [];

        // Fetch company profile
        $stmt = $conn->prepare("SELECT company_name, email, phone_number, headquarter, industry, company_type, website, overview, profile_photo, founded_year FROM cuser WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $cuserId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $profile = $result->fetch_assoc();
            }
            $stmt->close();
        }

        // Fetch job posts for the company
        $stmt_jobs = $conn->prepare("SELECT id, job_title, job_desc, location, work_mode, experience, time, salary, skills, form_link FROM Jobs WHERE cuser_id = ? ORDER BY id DESC");
        if ($stmt_jobs) {
            $stmt_jobs->bind_param("s", $cuserId);
            $stmt_jobs->execute();
            $result_jobs = $stmt_jobs->get_result();
            while ($row = $result_jobs->fetch_assoc()) {
                $jobs[] = $row;
            }
            $stmt_jobs->close();
        }
        $conn->close();

        http_response_code(200);
        echo json_encode(['success' => true, 'profile' => $profile, 'jobs' => $jobs]);
        break;

    case 'POST':
        // Handle profile updates and job additions
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data received.']);
            exit();
        }

        $action = $data['action'] ?? null;
        $conn = getDbConnection();

        if ($action === 'update_profile') {
            $company_name = $data['company_name'] ?? null;
            $email = $data['email'] ?? null;
            $phone_number = $data['phone_number'] ?? null;
            $headquarter = $data['headquarter'] ?? null;
            $industry = $data['industry'] ?? null;
            $company_type = $data['company_type'] ?? null;
            $website = $data['website'] ?? null;
            $overview = $data['overview'] ?? null;
            $founded_year = $data['founded_year'] ?? null;

            if (empty($company_name) || empty($email) || empty($headquarter) || empty($industry)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Company Name, Email, Headquarters, and Industry are required.']);
                $conn->close();
                exit();
            }

            $stmt = $conn->prepare("UPDATE cuser SET company_name = ?, email = ?, phone_number = ?, headquarter = ?, industry = ?, company_type = ?, website = ?, overview = ?, founded_year = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sssssssssi", $company_name, $email, $phone_number, $headquarter, $industry, $company_type, $website, $overview, $founded_year, $cuserId);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
                } else {
                    error_log("Profile update failed: " . $stmt->error);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to update profile. Database error.']);
                }
                $stmt->close();
            } else {
                error_log("Profile update prepare failed: " . $conn->error);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database statement preparation failed.']);
            }
        } elseif ($action === 'add_job') {
            $job_title = $data['job_title'] ?? null;
            $job_desc = $data['job_desc'] ?? null;
            $location = $data['location'] ?? null;
            $work_mode = $data['work_mode'] ?? null;
            $experience = $data['experience'] ?? null;
            $time = $data['time'] ?? null;
            $salary = $data['salary'] ?? null;
            $skills = $data['skills'] ?? null;
            $form_link = $data['form_link'] ?? null;

            if (empty($job_title) || empty($job_desc) || empty($location) || empty($work_mode)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Job Title, Description, Location, and Work Mode are required.']);
                $conn->close();
                exit();
            }
            
            $stmt = $conn->prepare("INSERT INTO Jobs (cuser_id, job_title, job_desc, location, work_mode, experience, time, salary, skills, form_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssssssssss", $cuserId, $job_title, $job_desc, $location, $work_mode, $experience, $time, $salary, $skills, $form_link);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Job post added successfully.']);
                } else {
                    error_log("Job post failed: " . $stmt->error);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to add job post. Database error.']);
                }
                $stmt->close();
            } else {
                error_log("Job post prepare failed: " . $conn->error);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database statement preparation failed.']);
            }
        } elseif ($action === 'upload_photo') {
            $image_data = $data['image_data'] ?? null;
            if (empty($image_data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Image data is required.']);
                $conn->close();
                exit();
            }

            // Remove the base64 prefix
            $image_data = str_replace('data:image/png;base64,', '', $image_data);
            $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);

            // Set up cURL request to imgBB
            $ch = curl_init();
            $post_data = ['image' => $image_data];
            
            curl_setopt($ch, CURLOPT_URL, "https://api.imgbb.com/1/upload?key=" . IMGBB_API_KEY);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                error_log("cURL Error: " . $err);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to upload image. cURL error: ' . $err]);
                exit();
            }

            $img_result = json_decode($response, true);
            
            if (isset($img_result['data']['url'])) {
                $photo_url = $img_result['data']['url'];

                $stmt = $conn->prepare("UPDATE cuser SET profile_photo = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $photo_url, $cuserId);
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Profile photo updated successfully.', 'photo_url' => $photo_url]);
                    } else {
                        error_log("Photo URL update failed: " . $stmt->error);
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Failed to save photo URL to database.']);
                    }
                    $stmt->close();
                } else {
                    error_log("Photo URL update prepare failed: " . $conn->error);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Database statement preparation failed.']);
                }
            } else {
                error_log("imgBB API Error: " . ($img_result['error']['message'] ?? 'Unknown error'));
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to upload photo to imgBB. ' . ($img_result['error']['message'] ?? 'Unknown error')]);
            }
        }
        $conn->close();
        break;

    case 'PUT':
        // Handle job post updates (assuming PUT is used for updates)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data received.']);
            exit();
        }

        $job_id = $data['job_id'] ?? null;
        $job_title = $data['job_title'] ?? null;
        $job_desc = $data['job_desc'] ?? null;
        $location = $data['location'] ?? null;
        $work_mode = $data['work_mode'] ?? null;
        $experience = $data['experience'] ?? null;
        $time = $data['time'] ?? null;
        $salary = $data['salary'] ?? null;
        $skills = $data['skills'] ?? null;
        $form_link = $data['form_link'] ?? null;

        if (empty($job_id) || empty($job_title) || empty($job_desc) || empty($location) || empty($work_mode)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'All job details and job ID are required for update.']);
            exit();
        }

        $conn = getDbConnection();
        $stmt = $conn->prepare("UPDATE Jobs SET job_title = ?, job_desc = ?, location = ?, work_mode = ?, experience = ?, time = ?, salary = ?, skills = ?, form_link = ? WHERE id = ? AND cuser_id = ?");
        if ($stmt) {
            $stmt->bind_param("sssssssssis", $job_title, $job_desc, $location, $work_mode, $experience, $time, $salary, $skills, $form_link, $job_id, $cuserId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Job post updated successfully.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Job post not found or no changes were made.']);
                }
            } else {
                error_log("Job update failed: " . $stmt->error);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update job post. Database error.']);
            }
            $stmt->close();
        } else {
            error_log("Job update prepare failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database statement preparation failed.']);
        }
        $conn->close();
        break;

    case 'DELETE':
        // Handle job post deletions
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data received.']);
            exit();
        }

        $job_id = $data['job_id'] ?? null;

        if (empty($job_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Job ID is required for deletion.']);
            exit();
        }

        $conn = getDbConnection();
        $stmt = $conn->prepare("DELETE FROM Jobs WHERE id = ? AND cuser_id = ?");
        if ($stmt) {
            $stmt->bind_param("is", $job_id, $cuserId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Job post deleted successfully.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Job post not found or you do not have permission to delete it.']);
                }
            } else {
                error_log("Job deletion failed: " . $stmt->error);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete job post. Database error.']);
            }
            $stmt->close();
        } else {
            error_log("Job deletion prepare failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database statement preparation failed.']);
        }
        $conn->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
        break;
}
?>