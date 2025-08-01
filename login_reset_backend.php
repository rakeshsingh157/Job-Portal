<?php
session_start();
if (isset($_SESSION['user_id']) || isset($_SESSION['cuser_id'])) {
    session_destroy();
    session_start();
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

    $url = $smsApiUrl . '?' . http_build_query($fields) . '&authorization=' . FAST2SMS_API_KEY;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
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

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data received.']);
        exit();
    }

    $action = $data['action'] ?? null;
    $email = $data['email'] ?? null;
    $otpCodeEmail = $data['otpCodeEmail'] ?? null;

    $smtpUsername = 'kumarpatelrakesh222@gmail.com';
    $smtpPassword = 'acshpmbiyrybsigu';


    if ($action === 'login') {
        $password_input = $data['password'] ?? null;

        if (empty($email) || empty($password_input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
            exit();
        }

        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT id, password_hash, is_verified FROM cuser WHERE email = ? LIMIT 1");
        if ($stmt === false) {
            error_log("Login prepare failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database statement preparation failed for login.']);
            exit();
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $bindResult = $stmt->bind_result($cuserId, $hashedPassword, $isVerified);
            if ($bindResult === false) {
                error_log("Login bind_result failed: " . $stmt->error);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error fetching user data during login.']);
                $stmt->close();
                $conn->close();
                exit();
            }
            $stmt->fetch();

            if (password_verify($password_input, $hashedPassword)) {
                if ($isVerified) {
                     $_SESSION['cuser_id'] = $cuserId; 
                     http_response_code(200);
                     echo json_encode(['success' => true, 'message' => 'Login successful!', 'cuserId' => $cuserId]);
             } else {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Account not verified. Please verify your email and phone number.']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
        $stmt->close();
        $conn->close();
        exit();

    } elseif ($action === 'send_reset_otp') {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid email address is required.']);
            exit();
        }

        $conn = getDbConnection();
        $stmt_check = $conn->prepare("SELECT id, phone_number FROM cuser WHERE email = ? LIMIT 1");
        if ($stmt_check === false) {
            error_log("send_reset_otp prepare failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database statement preparation failed.']);
            exit();
        }
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Email not found in our records.']);
            $stmt_check->close();
            $conn->close();
            exit();
        }
        $bindResult = $stmt_check->bind_result($cuserIdForReset, $registeredPhoneNumber);
        if ($bindResult === false) {
            error_log("send_reset_otp bind_result failed: " . $stmt_check->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error fetching user data for OTP send.']);
            $stmt_check->close();
            $conn->close();
            exit();
        }
        $stmt_check->fetch();
        $stmt_check->close();
        $conn->close();

        $reset_otp = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['reset_otp_email'] = $reset_otp;
        $_SESSION['reset_otp_email_address'] = $email;
        $_SESSION['reset_otp_expiry'] = time() + (10 * 60);
        $_SESSION['cuser_id_for_reset'] = $cuserIdForReset;
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
            echo json_encode(['success' => true, 'message' => 'Password reset OTP sent to your email.']);
        } catch (Exception $e) {
            error_log('Mailer Error (Password Reset): ' . $mail->ErrorInfo);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error sending password reset OTP.']);
        }
        exit();

    } elseif ($action === 'verify_reset_otp') {
        if (empty($email) || empty($otpCodeEmail)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email and OTP are required.']);
            exit();
        }

        if (!isset($_SESSION['reset_otp_email']) || $_SESSION['reset_otp_email_address'] !== $email) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No reset OTP found or email mismatch. Please request a new OTP.']);
            exit();
        }

        if (time() > $_SESSION['reset_otp_expiry']) {
            unset($_SESSION['reset_otp_email'], $_SESSION['reset_otp_email_address'], $_SESSION['reset_otp_expiry'], $_SESSION['cuser_id_for_reset']);
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
            exit();
        }

        if ($_SESSION['reset_otp_email'] === $otpCodeEmail) {
            $_SESSION['otp_verified_for_reset'] = true;
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'OTP verified successfully. You can now set your new password.']);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        }
        exit();

    } elseif ($action === 'reset_password') {
        $newPassword = $data['newPassword'] ?? null;

        if (empty($email) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email and new password are required.']);
            exit();
        }

        if (!isset($_SESSION['otp_verified_for_reset']) || $_SESSION['reset_otp_email_address'] !== $email || !isset($_SESSION['cuser_id_for_reset'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Password reset not authorized. Please verify OTP first.']);
            exit();
        }

        $conn = getDbConnection();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $cuserId = $_SESSION['cuser_id_for_reset'];

        $stmt = $conn->prepare("UPDATE cuser SET password_hash = ? WHERE id = ? AND email = ?");
        if ($stmt === false) {
            error_log("reset_password prepare failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database statement preparation failed for password reset.']);
            exit();
        }

        $stmt->bind_param("sis", $hashedPassword, $cuserId, $email);

        try {
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    unset($_SESSION['reset_otp_email'], $_SESSION['reset_otp_email_address'], $_SESSION['reset_otp_expiry']);
                    unset($_SESSION['otp_verified_for_reset'], $_SESSION['reset_phone_number'], $_SESSION['cuser_id_for_reset']);
                    http_response_code(200);
                    echo json_encode(['success' => true, 'message' => 'Password has been reset successfully.']);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Failed to reset password. User not found or password was unchanged.']);
                }
            } else {
                error_log("DB Update Error (Password Reset): " . $stmt->error);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to reset password. Database error.']);
            }
        } finally {
            $stmt->close();
            $conn->close();
        }
        exit();
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Invalid request method or action.']);