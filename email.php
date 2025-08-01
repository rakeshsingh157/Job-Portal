<?php
session_start();
if (isset($_SESSION['cuserId'])) {
   session_destroy(); // Clear any existing session data to prevent conflicts
}




ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


define('DB_HOST', 'photostore.ct0go6um6tj0.ap-south-1.rds.amazonaws.com'); 
define('DB_USER', 'admin');    
define('DB_PASS', 'DBpicshot');        
define('DB_NAME', 'jobp_db');  


define('FAST2SMS_API_KEY', 'j7PzBgITwA49QlS3iyXrqskCaYME08HvtfbLUGFhxD1VJmZ6ucjrFg5SVfye7NbH1Umlc80TKvZW6tLd');


function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error);
        http_response_code(500);
        echo json_encode(['message' => 'Internal server error: Database connection failed.']);
        exit();
    }
    return $conn;
}


function sendSmsOtp($phoneNumber, $otp) {
    if (empty(FAST2SMS_API_KEY)) { // Only check if it's empty
        error_log("Fast2SMS API Key is not set.");
        return ['success' => false, 'message' => 'SMS service not configured (API Key missing).'];
    }
    // 

    $smsApiUrl = "https://www.fast2sms.com/dev/bulkV2";
    $fields = array(
        "variables_values" => $otp,
        "route" => "otp",
        "numbers" => $phoneNumber,
        "flash" => "0"
    );

    $url = $smsApiUrl . '?' . http_build_query(array_merge($fields, ['authorization' => FAST2SMS_API_KEY]));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // WARNING: Set to 2 in production
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // WARNING: Set to 1 in production
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("cURL Error (Fast2SMS): " . $err);
        return ['success' => false, 'message' => 'Failed to connect to SMS service (cURL error).'];
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['return']) && $responseData['return'] === true) {
        return ['success' => true, 'message' => 'SMS OTP sent successfully.'];
    } else {
        error_log("Fast2SMS API Error Response: " . ($response ?: "Empty response"));
        return ['success' => false, 'message' => 'SMS service returned an error.'];
    }
}

// Ensure the response is always JSON for AJAX requests
header('Content-Type: application/json');

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $action = $data['action'] ?? null;
    $email = $data['email'] ?? null;
    $otpCodeEmail = $data['otpCodeEmail'] ?? null;
    $otpCodePhone = $data['otpCodePhone'] ?? null; // Used in signup verification

    // Data received from frontend for 'send_otp' (signup)
    $firstName = $data['firstName'] ?? null;
    $lastName = $data['lastName'] ?? null;
    $password = $data['password'] ?? null;
    $countryCode = $data['countryCode'] ?? '+91';
    $phoneNumber = $data['phoneNumber'] ?? null;

    // Gmail SMTP Credentials for PHPMailer
    $smtpUsername = 'kumarpatelrakesh222@gmail.com';
    $smtpPassword = 'acshpmbiyrybsigu'; // Your Gmail App Password


    // --- SIGNUP ACTIONS ---
    if ($action === 'send_otp') { // Action for initial signup form submission
        // Input Validation
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['message' => 'Valid email address is required.']);
            exit();
        }
        if (empty($phoneNumber) || !preg_match('/^[0-9]{10,15}$/', $phoneNumber)) {
            http_response_code(400);
            echo json_encode(['message' => 'Valid phone number is required.']);
            exit();
        }

        // Check for existing user
        $conn = getDbConnection();
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone_number = ? LIMIT 1");
        $stmt_check->bind_param("ss", $email, $phoneNumber);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            http_response_code(409); // Conflict status code
            echo json_encode(['message' => 'Email or Phone Number is already registered. Please sign in or use a different one.']);
            $stmt_check->close();
            $conn->close();
            exit();
        }
        $stmt_check->close();
        $conn->close();

        // Generate and Store OTPs in Session
        $email_otp = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $phone_otp = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        $_SESSION['email_otp'] = $email_otp;
        $_SESSION['email_otp_email'] = $email;
        $_SESSION['email_otp_expiry'] = time() + (5 * 60);

        $_SESSION['phone_otp'] = $phone_otp;
        $_SESSION['phone_otp_phone'] = $phoneNumber;
        $_SESSION['phone_otp_expiry'] = time() + (5 * 60);

        // Store ALL user data temporarily in session until OTPs are verified
        $_SESSION['temp_user_data'] = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => $password, // Plain password, will be hashed before DB insert
            'country_code' => $countryCode,
            'phone_number' => $phoneNumber,
            'test_pass_value' => null // Default to null for 'test_pass' column
        ];

        $emailSent = false;
        $smsSent = false;
        $messages = [];

        // Send Email OTP
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUsername;
            $mail->Password   = $smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom($smtpUsername, 'JobP OTP');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your JobP Email OTP';
            $mail->Body    = "Your Email One-Time Password (OTP) for JobP is: <b>" . $email_otp . "</b>.<br>It is valid for 5 minutes. Do not share this code.";
            $mail->AltBody = "Your Email One-Time Password (OTP) for JobP is: " . $email_otp . ". It is valid for 5 minutes. Do not share this code.";

            $mail->send();
            $emailSent = true;
            $messages[] = 'Email OTP sent.';
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            $messages[] = 'Error sending email OTP.';
        }

        // Send Phone OTP
        $fullPhoneNumberForSMS = ltrim($countryCode, '+') . $phoneNumber;
        $smsResult = sendSmsOtp($fullPhoneNumberForSMS, $phone_otp);
        if ($smsResult['success']) {
            $smsSent = true;
            $messages[] = 'SMS OTP sent.';
        } else {
            error_log('SMS Error: ' . $smsResult['message']);
            $messages[] = 'Error sending SMS OTP.';
        }

        if ($emailSent || $smsSent) {
            http_response_code(200);
            echo json_encode(['message' => implode(' ', $messages)]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to send any OTP.']);
        }
        exit();

    } elseif ($action === 'verify_otp') { // Action for signup OTP verification
        // Input Validation
        if (empty($email) || empty($otpCodeEmail) || empty($otpCodePhone)) {
            http_response_code(400);
            echo json_encode(['message' => 'All OTPs are required.']);
            exit();
        }

        // Retrieve stored user data from session for verification and database insertion
        $session_email = $_SESSION['email_otp_email'] ?? null;
        $session_phone_number = $_SESSION['phone_otp_phone'] ?? null;

        if (empty($session_email) || empty($session_phone_number) || $session_email !== $email) {
            http_response_code(400);
            echo json_encode(['message' => 'Session data missing or expired. Please re-initiate signup.']);
            unset($_SESSION['email_otp'], $_SESSION['email_otp_email'], $_SESSION['email_otp_expiry']);
            unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']);
            unset($_SESSION['temp_user_data']);
            exit();
        }

        // Verify Email OTP
        if (time() > $_SESSION['email_otp_expiry'] || $_SESSION['email_otp'] !== $otpCodeEmail) {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid or expired Email OTP.']);
            unset($_SESSION['email_otp'], $_SESSION['email_otp_email'], $_SESSION['email_otp_expiry']);
            exit();
        }

        // Verify Phone OTP
        if (time() > $_SESSION['phone_otp_expiry'] || $_SESSION['phone_otp'] !== $otpCodePhone) {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid or expired Phone OTP.']);
            unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']);
            exit();
        }

        // Both OTPs are correct! Proceed with user registration
        if (isset($_SESSION['temp_user_data'])) {
            $userData = $_SESSION['temp_user_data'];

            $conn = getDbConnection();
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            $testPassValue = $userData['test_pass_value'];

            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, country_code, phone_number, is_verified, test_pass) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $is_verified = 1;
            $stmt->bind_param(
                "ssssssis",
                $userData['first_name'],
                $userData['last_name'],
                $email,
                $hashedPassword,
                $userData['country_code'],
                $userData['phone_number'],
                $is_verified,
                $testPassValue
            );

            try {
                if ($stmt->execute()) {
                    // Clear all session data related to OTPs and temporary user data after successful registration
                    unset($_SESSION['email_otp'], $_SESSION['email_otp_email'], $_SESSION['email_otp_expiry']);
                    unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']);
                    unset($_SESSION['temp_user_data']);

                    http_response_code(200);
                    echo json_encode(['message' => 'Account created and verified successfully!']);
                } else {
                    $errorMessage = $conn->errno == 1062 ? 'Email or phone number already registered.' : 'Database error during registration.';
                    error_log("DB INSERT Error: " . $stmt->error);
                    http_response_code(500);
                    echo json_encode(['message' => $errorMessage]);
                }
            } finally {
                $stmt->close();
                $conn->close();
            }
        } else {
            error_log("Verify OTP: temp_user_data missing after successful OTP verification.");
            unset($_SESSION['email_otp'], $_SESSION['email_otp_email'], $_SESSION['email_otp_expiry']);
            unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']);
            http_response_code(400);
            echo json_encode(['message' => 'OTPs verified, but user data missing. Please try signing up again.']);
        }
        exit();

    // --- LOGIN ACTIONS ---
   } elseif ($action === 'login') {
        $password_input = $data['password'] ?? null;

        if (empty($email) || empty($password_input)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email and password are required.']);
            exit();
        }

        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT id, password_hash, is_verified FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($userId, $hashedPassword, $isVerified);
            $stmt->fetch();
            $stmt->close(); // Close statement as soon as results are fetched

            if (password_verify($password_input, $hashedPassword)) {
                // Password is correct
                if ($isVerified) {
                    $_SESSION['user_id'] = $userId; // Store user ID in session
                    error_log("Login successful. User ID {$userId} set in session."); 
                   
                    http_response_code(200);
                    echo json_encode(['message' => 'Login successful!', 'redirect' => 'verify.php']);
                    
                } else {
                    http_response_code(403); // Forbidden
                    echo json_encode(['message' => 'Account not verified. Please verify your email and phone number.']);
                }
            } elseif (password_needs_rehash($hashedPassword, PASSWORD_DEFAULT)) {
                // Password needs rehashing
                $newHashedPassword = password_hash($password_input, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newHashedPassword, $userId);
                if ($updateStmt->execute()) {
                    $_SESSION['user_id'] = $userId; // Store user ID in session
                    error_log("Login successful. Password rehashed. User ID {$userId} set in session."); // Added for debugging
                    http_response_code(200);
                    echo json_encode(['message' => 'Login successful! Password updated.', 'redirect' => 'verify.php']);
                } else {
                    error_log("DB Update Error (Password Rehash): " . $updateStmt->error);
                    http_response_code(500);
                    echo json_encode(['message' => 'Failed to update password. Database error.']);
                }
                $updateStmt->close();
            } else {
                // Password is incorrect
                http_response_code(401); // Unauthorized
                echo json_encode(['message' => 'Invalid email or password.']);
            }
        } else {
            // Email not found
            http_response_code(401); // Unauthorized
            echo json_encode(['message' => 'Invalid email or password.']);
        }
        $conn->close(); // Close connection here
        exit(); // Always exit after sending JSON response
    
        
    }elseif ($action === 'send_reset_otp') {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['message' => 'Valid email address is required.']);
            exit();
        }

        $conn = getDbConnection();
        $stmt_check = $conn->prepare("SELECT phone_number FROM users WHERE email = ? LIMIT 1");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['message' => 'Email not found in our records.']);
            $stmt_check->close();
            $conn->close();
            exit();
        }
        $stmt_check->bind_result($registeredPhoneNumber);
        $stmt_check->fetch();
        $stmt_check->close();
        $conn->close();

        $reset_otp = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['reset_otp_email'] = $reset_otp;
        $_SESSION['reset_otp_email_address'] = $email;
        $_SESSION['reset_otp_expiry'] = time() + (10 * 60); // 10 minutes for reset OTP

        // Store phone number associated with reset for later verification if needed
        $_SESSION['reset_phone_number'] = $registeredPhoneNumber;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUsername;
            $mail->Password   = $smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom($smtpUsername, 'JobP Password Reset');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your JobP Password Reset OTP';
            $mail->Body    = "Your One-Time Password (OTP) for password reset is: <b>" . $reset_otp . "</b>.<br>It is valid for 10 minutes. Do not share this code.";
            $mail->AltBody = "Your One-Time Password (OTP) for password reset is: " . $reset_otp . ". It is valid for 10 minutes. Do not share this code.";

            $mail->send();
            http_response_code(200);
            echo json_encode(['message' => 'Password reset OTP sent to your email.']);
        } catch (Exception $e) {
            error_log('Mailer Error (Password Reset): ' . $mail->ErrorInfo);
            http_response_code(500);
            echo json_encode(['message' => 'Error sending password reset OTP.']);
        }
        exit();

    } elseif ($action === 'verify_reset_otp') {
        if (empty($email) || empty($otpCodeEmail)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email and OTP are required.']);
            exit();
        }

        if (!isset($_SESSION['reset_otp_email']) || $_SESSION['reset_otp_email_address'] !== $email) {
            http_response_code(401);
            echo json_encode(['message' => 'No reset OTP found or email mismatch.']);
            exit();
        }

        if (time() > $_SESSION['reset_otp_expiry']) {
            unset($_SESSION['reset_otp_email'], $_SESSION['reset_otp_email_address'], $_SESSION['reset_otp_expiry']);
            http_response_code(401);
            echo json_encode(['message' => 'OTP has expired. Please request a new one.']);
            exit();
        }

        if ($_SESSION['reset_otp_email'] === $otpCodeEmail) {
            // OTP is correct! Mark session as verified for password change
            $_SESSION['otp_verified_for_reset'] = true;
            http_response_code(200);
            echo json_encode(['message' => 'OTP verified successfully. You can now set your new password.']);
        } else {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid OTP. Please try again.']);
        }
        exit();

    } elseif ($action === 'reset_password') {
        $newPassword = $data['newPassword'] ?? null;

        if (empty($email) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email and new password are required.']);
            exit();
        }

        // Ensure OTP was verified in the current session
        if (!isset($_SESSION['otp_verified_for_reset']) || $_SESSION['reset_otp_email_address'] !== $email) {
            http_response_code(403); // Forbidden
            echo json_encode(['message' => 'Password reset not authorized. Please verify OTP first.']);
            exit();
        }

        $conn = getDbConnection();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);

        try {
            if ($stmt->execute()) {
                // Clear all reset-related session data
                unset($_SESSION['reset_otp_email'], $_SESSION['reset_otp_email_address'], $_SESSION['reset_otp_expiry']);
                unset($_SESSION['otp_verified_for_reset'], $_SESSION['reset_phone_number']); // Also clear phone number if stored
                http_response_code(200);
                echo json_encode(['message' => 'Password has been reset successfully.']);
            } else {
                error_log("DB Update Error (Password Reset): " . $stmt->error);
                http_response_code(500);
                echo json_encode(['message' => 'Failed to reset password. Database error.']);
            }
        } finally {
            $stmt->close();
            $conn->close();
        }
        exit();
    }
}
// If the request method is not POST, the script simply finishes without outputting anything.
?>