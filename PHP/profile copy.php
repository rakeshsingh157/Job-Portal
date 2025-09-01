<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require('config.php');

if (!isset($_SESSION['user_id'])) {
   header('Location: wokersignin.html');
    exit();

    
}

$user_id = $_SESSION['user_id'];

const IMGBB_API_KEY = '8f23d9f5d1b5960647ba5942af8a1523'; 
if (isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_path = $file['tmp_name'];
        $file_data = file_get_contents($file_path);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.imgbb.com/1/upload?key=" . IMGBB_API_KEY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => base64_encode($file_data)]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['data']['url'])) {
                $photo_url = $data['data']['url'];
                
                // Update the profile_url in the database
                $stmt = $conn->prepare("UPDATE users SET profile_url = ? WHERE id = ?");
                $stmt->bind_param("si", $photo_url, $user_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'photo_url' => $photo_url]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Database update failed: ' . $stmt->error]);
                }
                $stmt->close();
                $conn->close();
                exit;
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'ImgBB upload failed: ' . ($data['error']['message'] ?? 'Unknown error.')]);
                exit;
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'ImgBB API call failed: ' . ($curl_error ?: 'HTTP Code ' . $http_code)]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'File upload error: ' . $file['error']]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '';
    $address = isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '';
    $phone_number = isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : '';
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
    $education = isset($_POST['education']) ? htmlspecialchars($_POST['education']) : '';
    $work_experience = isset($_POST['work_experience']) ? htmlspecialchars($_POST['work_experience']) : '';
    $job_field = isset($_POST['job_field']) ? htmlspecialchars($_POST['job_field']) : '';
    $employment_type = isset($_POST['employment_type']) ? htmlspecialchars($_POST['employment_type']) : '';
    $shift_type = isset($_POST['shift_type']) ? htmlspecialchars($_POST['shift_type']) : '';
    $part_time_hours = isset($_POST['part_time_hours']) ? htmlspecialchars($_POST['part_time_hours']) : '';
    
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, education = ?, work_experience = ?, address = ?, job_field = ?, employment_type = ?, shift_type = ?, part_time_hours = ? WHERE id = ?");
    $stmt->bind_param("sssssssssssi", $first_name, $last_name, $email, $phone_number, $education, $work_experience, $address, $job_field, $employment_type, $shift_type, $part_time_hours, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database update failed: ' . $stmt->error]);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile_data = $result->fetch_assoc();

if ($profile_data) {
    $profile_data['bio'] = 'something about yourself or something you would write for your bio or experience.';
    $profile_data['age'] = "none";
    $profile_data['experience_years'] = "No Experience";
    $profile_data['skills'] = ['No Skills'];
    $profile_data['languages'] = ['No Language'];
    $profile_data['profile_picture_url'] = $profile_data['profile_url'] ?? 'https://placehold.co/150x150/png?text=P';
    
    $profile_data['name'] = $profile_data['first_name'] . ' ' . $profile_data['last_name'];
    
    echo json_encode(['success' => true, 'profile_data' => $profile_data]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'User not found.']);
}

$stmt->close();
$conn->close();
?>
