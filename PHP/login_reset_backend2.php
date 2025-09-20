<?php
// Start the PHP session to manage OTPs and verification states
session_start();

// Enable error reporting for debugging (REMOVE OR DISABLE IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autoload Composer dependencies for PHPMailer
// Ensure 'vendor' folder is in the same directory as this script, or adjust the path.
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database Configuration
// IMPORTANT: Replace with your actual database credentials.
define('DB_HOST', 'database-1.chcyc88wcx2l.eu-north-1.rds.amazonaws.com'); // Your database host
define('DB_USER', 'admin');     // Your database username
define('DB_PASS', 'DBpicshot');         // Your database password
define('DB_NAME', 'jobp_db');  // Your database name

// Fast2SMS API Key (Set to empty string for Canvas runtime injection)
// In a real production environment, this should be a secure environment variable.
define('FAST2SMS_API_KEY', ''); 

// Function to establish database connection
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

// Function to send SMS OTP via Fast2SMS API (Included for completeness, though not used in current reset flow)
function sendSmsOtp($phoneNumber, $otp) {
    if (empty(FAST2SMS_API_KEY)) {
        error_log("Fast2SMS API Key is not set.");
        return ['success' => false, 'message' => 'SMS service not configured (API Key missing).'];
    }

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

// Set the content type header for JSON responses for all AJAX requests
header('Content-Type: application/json');

// Process only POST requests from the frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $action = $data['action'] ?? null; // Determines the specific backend action to perform
    $email = $data['email'] ?? null;
    $otpCodeEmail = $data['otpCodeEmail'] ?? null; // Email OTP provided by user

    // Gmail SMTP Credentials for PHPMailer
    $smtpUsername = 'jobportal00000@gmail.com';
    $smtpPassword = 'gztykeykurgggklb'; // Your Gmail App Password


    // --- LOGIN ACTION ---
    if ($action === 'login') {
        $password_input = $data['password'] ?? null;

        // Input validation
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

            // Verify the provided password against the stored hash
            if (password_verify($password_input, $hashedPassword)) {
                if ($isVerified) {
                    http_response_code(200); // OK
                    echo json_encode(['message' => 'Login successful!', 'userId' => $userId]);
                    // In a real application, create a secure session (e.g., set $_SESSION variables)
                    // or issue a JWT token here for subsequent authenticated requests.
                } else {
                    http_response_code(403); // Forbidden (account not verified)
                    echo json_encode(['message' => 'Account not verified. Please verify your email and phone number.']);
                }
            } else {
                http_response_code(401); // Unauthorized (invalid credentials)
                echo json_encode(['message' => 'Invalid email or password.']);
            }
        } else {
            http_response_code(401); // Unauthorized (user not found)
            echo json_encode(['message' => 'Invalid email or password.']);
        }
        $stmt->close();
        $conn->close();
        exit();

    // --- PASSWORD RECOVERY ACTIONS ---

    // Action to send OTP for password reset
    } elseif ($action === 'send_reset_otp') {
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

        // Generate and store password reset OTP in session
        $reset_otp = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['reset_otp_email'] = $reset_otp;
        $_SESSION['reset_otp_email_address'] = $email;
        $_SESSION['reset_otp_expiry'] = time() + (10 * 60); // OTP valid for 10 minutes

        // Store the registered phone number in session, potentially for future phone OTP reset verification
        $_SESSION['reset_phone_number'] = $registeredPhoneNumber;

        // Send email OTP for password reset
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
            http_response_code(200); // OK
            echo json_encode(['message' => 'Password reset OTP sent to your email.']);
        } catch (Exception $e) {
            error_log('Mailer Error (Password Reset): ' . $mail->ErrorInfo);
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Error sending password reset OTP.']);
        }
        exit();

    // Action to verify OTP for password reset
    } elseif ($action === 'verify_reset_otp') {
        // This action currently only verifies Email OTP for password reset.
        if (empty($email) || empty($otpCodeEmail)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email and OTP are required.']);
            exit();
        }

        // Check if reset OTP is in session and matches the provided email
        if (!isset($_SESSION['reset_otp_email']) || $_SESSION['reset_otp_email_address'] !== $email) {
            http_response_code(401); // Unauthorized
            echo json_encode(['message' => 'No reset OTP found or email mismatch.']);
            exit();
        }

        // Check if OTP has expired
        if (time() > $_SESSION['reset_otp_expiry']) {
            // Clear expired OTP data from session
            unset($_SESSION['reset_otp_email'], $_SESSION['reset_otp_email_address'], $_SESSION['reset_otp_expiry']);
            http_response_code(401); // Unauthorized
            echo json_encode(['message' => 'OTP has expired. Please request a new one.']);
            exit();
        }

        // Verify the provided OTP
        if ($_SESSION['reset_otp_email'] === $otpCodeEmail) {
            // OTP is correct! Mark the session as verified for password change
            $_SESSION['otp_verified_for_reset'] = true;
            http_response_code(200); // OK
            echo json_encode(['message' => 'OTP verified successfully. You can now set your new password.']);
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(['message' => 'Invalid OTP. Please try again.']);
        }
        exit();

    // Action to reset user's password
    } elseif ($action === 'reset_password') {
        $newPassword = $data['newPassword'] ?? null;

        // Input validation
        if (empty($email) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email and new password are required.']);
            exit();
        }

        // Ensure OTP was verified in the current session for this email
        if (!isset($_SESSION['otp_verified_for_reset']) || $_SESSION['reset_otp_email_address'] !== $email) {
            http_response_code(403); // Forbidden
            echo json_encode(['message' => 'Password reset not authorized. Please verify OTP first.']);
            exit();
        }

        $conn = getDbConnection();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT); // Hash the new password

        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);

        try {
            if ($stmt->execute()) {
                // Clear all reset-related session data after successful password update
                unset($_SESSION['reset_otp_email'], $_SESSION['reset_otp_email_address'], $_SESSION['reset_otp_expiry']);
                unset($_SESSION['otp_verified_for_reset'], $_SESSION['reset_phone_number']);
                http_response_code(200); // OK
                echo json_encode(['message' => 'Password has been reset successfully.']);
            } else {
                error_log("DB Update Error (Password Reset): " . $stmt->error); // Log database error
                http_response_code(500); // Internal Server Error
                echo json_encode(['message' => 'Failed to reset password. Database error.']);
            }
        } finally {
            $stmt->close(); // Close the statement
            $conn->close(); // Close the database connection
        }
        exit();
    }
}
// If the request method is not POST, the script simply finishes without outputting anything.
?>
