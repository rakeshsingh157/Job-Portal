<?php
// profile.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require('config.php'); // Your database connection

// Make sure a user is logged in
if (!isset($_SESSION['user_id'])) {
   http_response_code(401);
   echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
   exit();
}
$user_id = $_SESSION['user_id'];

// --- HANDLE POST REQUESTS (Updating data) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check for a specific action (e.g., add/delete language)
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add_language' && isset($_POST['newLanguage'])) {
            $new_language = trim(htmlspecialchars($_POST['newLanguage']));
            if (empty($new_language)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Language cannot be empty.']);
                exit;
            }

            $stmt = $conn->prepare("SELECT language FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $current_languages = explode(',', $stmt->get_result()->fetch_assoc()['language'] ?? '');
            $stmt->close();
            
            if (!in_array($new_language, $current_languages)) {
                $current_languages[] = $new_language;
            }
            // Filter out empty values and join
            $updated_languages = implode(',', array_filter($current_languages));

            $stmt = $conn->prepare("UPDATE users SET language = ? WHERE id = ?");
            $stmt->bind_param("si", $updated_languages, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Language added successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database update failed.']);
            }
            $stmt->close();
            exit;
        }

        if ($action === 'delete_language' && isset($_POST['languageName'])) {
            $language_to_delete = trim(htmlspecialchars($_POST['languageName']));
            
            $stmt = $conn->prepare("SELECT language FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $current_languages = explode(',', $stmt->get_result()->fetch_assoc()['language'] ?? '');
            $stmt->close();
            
            // Remove the specified language
            $updated_languages = array_diff($current_languages, [$language_to_delete]);
            $updated_languages_string = implode(',', array_filter($updated_languages));

            $stmt = $conn->prepare("UPDATE users SET language = ? WHERE id = ?");
            $stmt->bind_param("si", $updated_languages_string, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Language deleted successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database update failed.']);
            }
            $stmt->close();
            exit;
        }
    }

    // Handle profile photo upload
    if (isset($_FILES['profile_photo'])) {
        // ImgBB API Key - It's better to store this in an environment variable
        define('IMGBB_API_KEY', '8f23d9f5d1b5960647ba5942af8a1523'); 
        $file = $_FILES['profile_photo'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.imgbb.com/1/upload?key=" . IMGBB_API_KEY);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => base64_encode(file_get_contents($file['tmp_name']))]);

            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);

            if ($data && $data['success']) {
                $photo_url = $data['data']['url'];
                $stmt = $conn->prepare("UPDATE users SET profile_url = ? WHERE id = ?");
                $stmt->bind_param("si", $photo_url, $user_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Photo updated!', 'photo_url' => $photo_url]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Database update failed.']);
                }
                $stmt->close();
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Image upload to external service failed.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'File upload error code: ' . $file['error']]);
        }
        exit;
    }

    // Handle general profile update from edit form
    $first_name = htmlspecialchars($_POST['first_name'] ?? '');
    $last_name = htmlspecialchars($_POST['last_name'] ?? '');
    $address = htmlspecialchars($_POST['address'] ?? '');
    $employment_type = htmlspecialchars($_POST['employment_type'] ?? '');
    $shift_type = $employment_type === 'Full Time' ? htmlspecialchars($_POST['shift_type'] ?? '') : null;
    $part_time_hours = $employment_type === 'Part Time' ? htmlspecialchars($_POST['part_time_hours'] ?? '') : null;
    $gender = htmlspecialchars($_POST['gender'] ?? '');
    $bio = htmlspecialchars($_POST['bio'] ?? '');
    $age = !empty($_POST['age']) ? intval($_POST['age']) : null;

    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, address = ?, gender = ?, employment_type = ?, shift_type = ?, part_time_hours = ?, bio = ?, age = ? WHERE id = ?");
    $stmt->bind_param("ssssssssii", $first_name, $last_name, $address, $gender, $employment_type, $shift_type, $part_time_hours, $bio, $age, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// --- HANDLE GET REQUEST (Fetching data) ---
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile_data = $result->fetch_assoc();
$stmt->close();

if ($profile_data) {
    // Sanitize and set defaults for display
    $profile_data['name'] = trim(($profile_data['first_name'] ?? '') . ' ' . ($profile_data['last_name'] ?? ''));
    $profile_data['bio'] = $profile_data['bio'] ?: 'No bio available. Click "Edit Profile" to add one.';
    $profile_data['age'] = $profile_data['age'] ?: 'N/A';
    $profile_data['address'] = $profile_data['address'] ?: 'N/A';
    $profile_data['job_field'] = $profile_data['job_field'] ?: 'N/A';
    $profile_data['gender'] = $profile_data['gender'] ?: 'N/A';
    $profile_data['profile_url'] = $profile_data['profile_url'] ?: 'https://placehold.co/150x150/png?text=P';

    // Ensure skills and languages are always arrays for consistent frontend handling
    $profile_data['skills'] = !empty($profile_data['skills']) ? explode(',', $profile_data['skills']) : [];
    $profile_data['languages'] = !empty($profile_data['language']) ? explode(',', $profile_data['language']) : [];
    
    echo json_encode(['success' => true, 'profile_data' => $profile_data]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found.']);
}

$conn->close();
?>
