<?php

session_start();


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('DB_HOST', 'photostore.ct0go6um6tj0.ap-south-1.rds.amazonaws.com'); // Your database host
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
        "variables_values" => $otp, // The OTP value to send
        "route" => "otp",           // Specifies the OTP route
        "numbers" => $phoneNumber,  // Recipient phone number(s)
        "flash" => "0"              // Non-flash SMS
    );

    // Build the complete URL with query parameters including the authorization key
    $url = $smsApiUrl . '?' . http_build_query(array_merge($fields, ['authorization' => FAST2SMS_API_KEY]));

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);      

    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 


    $response = curl_exec($ch);
    $err = curl_error($ch); 
    curl_close($ch);

    // Handle cURL errors
    if ($err) {
        error_log("cURL Error (Fast2SMS): " . $err);
        return ['success' => false, 'message' => 'Failed to connect to SMS service (cURL error).'];
    }

    // Decode the JSON response from Fast2SMS
    $responseData = json_decode($response, true);

    // Check Fast2SMS API response for success
    if (isset($responseData['return']) && $responseData['return'] === true) {
        return ['success' => true, 'message' => 'SMS OTP sent successfully.'];
    } else {
        // Log the full response for debugging if the API call was not successful
        error_log("Fast2SMS API Error Response: " . ($response ?: "Empty response"));
        return ['success' => false, 'message' => 'SMS service returned an error.'];
    }
}

// Set the content type header for JSON responses for all AJAX requests
header('Content-Type: application/json');

// Process only POST requests from the frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data and decode it from JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Extract common data from the request payload
    $action = $data['action'] ?? null; // Determines the specific backend action to perform
    $email = $data['email'] ?? null;
    $otpCodeEmail = $data['otpCodeEmail'] ?? null; // Email OTP provided by user
    $otpCodePhone = $data['otpCodePhone'] ?? null; // Phone OTP provided by user

    // Data specific to 'send_otp' action (user registration details for cuser)
    $companyName = $data['companyName'] ?? null; 
    $password = $data['password'] ?? null;
    $countryCode = $data['countryCode'] ?? '+91'; // Default to +91 for India
    $phoneNumber = $data['phoneNumber'] ?? null;

    // Gmail SMTP Credentials for PHPMailer
    // IMPORTANT: Replace with your actual Gmail address and 16-character App Password.
    // An App Password is required if you have 2-Factor Authentication enabled on your Gmail.
    $smtpUsername = 'jobportal00000@gmail.com';
    $smtpPassword = 'gztykeykurgggklb';


    // --- SIGNUP ACTIONS (for 'cuser' table) ---

    // Action to send OTPs (email and SMS) for new user registration
    if ($action === 'send_otp') {
        // Validate required input fields
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['message' => 'Valid email address is required.']);
            exit();
        }
        if (empty($phoneNumber) || !preg_match('/^[0-9]{10,15}$/', $phoneNumber)) { // Basic regex for 10-15 digits
            http_response_code(400);
            echo json_encode(['message' => 'Valid phone number is required.']);
            exit();
        }
        // Validate companyName, assuming it's a NOT NULL field in 'cuser' table
        if (empty($companyName)) { 
            http_response_code(400);
            echo json_encode(['message' => 'Company name is required.']);
            exit();
        }

        // Check if email or phone number already exists in the 'cuser' table
        $conn = getDbConnection();
        $stmt_check = $conn->prepare("SELECT id FROM cuser WHERE email = ? OR phone_number = ? LIMIT 1");
        $stmt_check->bind_param("ss", $email, $phoneNumber);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            http_response_code(409); // Conflict status code (resource already exists)
            echo json_encode(['message' => 'Email or Phone Number is already registered. Please sign in or use a different one.']);
            $stmt_check->close();
            $conn->close();
            exit();
        }
        $stmt_check->close();
        $conn->close(); // Close connection after check to free up resources

        // Generate random OTPs for email and phone
        $email_otp = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT); // 6-digit email OTP
        $phone_otp = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);     // 4-digit phone OTP

        // Store OTPs and their expiry times in the session
        $_SESSION['email_otp'] = $email_otp;
        $_SESSION['email_otp_email'] = $email;
        $_SESSION['email_otp_expiry'] = time() + (5 * 60); // OTP valid for 5 minutes

        $_SESSION['phone_otp'] = $phone_otp;
        $_SESSION['phone_otp_phone'] = $phoneNumber;
        $_SESSION['phone_otp_expiry'] = time() + (5 * 60);

        // Store all user registration data temporarily in session
        // This data will be used to insert into the database AFTER successful OTP verification.
        $_SESSION['temp_user_data'] = [
            'company_name' => $companyName, 
            'password' => $password, // Plain password (to be hashed before DB insert)
            'country_code' => $countryCode,
            'phone_number' => $phoneNumber,
            'cverified_value' => null // Default value for 'cverified' column
        ];

        $emailSent = false;
        $smsSent = false;
        $messages = []; // Array to collect messages for the frontend

        // --- Send Email OTP using PHPMailer ---
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUsername;
            $mail->Password   = $smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SMTPS for port 465
            $mail->Port       = 465;

            $mail->setFrom($smtpUsername, 'JobP OTP'); // Sender email and name
            $mail->addAddress($email); // Recipient email

            $mail->isHTML(true); // Set email format to HTML
            $mail->Subject = 'Your JobP Email OTP';
            $mail->Body    = "Your Email One-Time Password (OTP) for JobP is: <b>" . $email_otp . "</b>.<br>It is valid for 5 minutes. Do not share this code.";
            $mail->AltBody = "Your Email One-Time Password (OTP) for JobP is: " . $email_otp . ". It is valid for 5 minutes. Do not share this code.";

            $mail->send();
            $emailSent = true;
            $messages[] = 'Email OTP sent.';
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo); // Log detailed PHPMailer error
            $messages[] = 'Error sending email OTP.';
        }

        // --- Send Phone OTP using Fast2SMS API ---
        $fullPhoneNumberForSMS = ltrim($countryCode, '+') . $phoneNumber; // Remove '+' from country code if API requires
        $smsResult = sendSmsOtp($fullPhoneNumberForSMS, $phone_otp);
        if ($smsResult['success']) {
            $smsSent = true;
            $messages[] = 'SMS OTP sent.';
        } else {
            error_log('SMS Error: ' . $smsResult['message']); // Log detailed SMS error
            $messages[] = 'Error sending SMS OTP.';
        }

        // Respond to the frontend based on whether at least one OTP was sent successfully
        if ($emailSent || $smsSent) {
            http_response_code(200);
            echo json_encode(['message' => implode(' ', $messages)]); // Combine messages for the user
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to send any OTP. Please try again.']);
        }
        exit();

    // Action to verify OTPs and register the user in the database
    } elseif ($action === 'verify_otp') {
        // Validate required input fields from the frontend
        if (empty($email) || empty($otpCodeEmail) || empty($otpCodePhone)) {
            http_response_code(400);
            echo json_encode(['message' => 'All OTPs are required.']);
            exit();
        }

        // Retrieve stored session data for verification
        $session_email = $_SESSION['email_otp_email'] ?? null;
        $session_phone_number = $_SESSION['phone_otp_phone'] ?? null;

        // Check if session data is missing or if the email/phone number doesn't match
        if (empty($session_email) || empty($session_phone_number) || $session_email !== $email) {
            http_response_code(400);
            echo json_encode(['message' => 'Session data missing or expired. Please re-initiate signup.']);
            // Clear relevant session data to prevent reuse
            unset($_SESSION['email_otp'], $_SESSION['email_otp_email'], $_SESSION['email_otp_expiry']);
            unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']);
            unset($_SESSION['temp_user_data']);
            exit();
        }

        // --- Verify Email OTP ---
        if (time() > $_SESSION['email_otp_expiry'] || $_SESSION['email_otp'] !== $otpCodeEmail) {
            http_response_code(401); // Unauthorized
            echo json_encode(['message' => 'Invalid or expired Email OTP.']);
            unset($_SESSION['email_otp'], $_SESSION['email_otp_email'], $_SESSION['email_otp_expiry']); // Clear email OTP data on failure
            exit();
        }

        // --- Verify Phone OTP ---
        if (time() > $_SESSION['phone_otp_expiry'] || $_SESSION['phone_otp'] !== $otpCodePhone) {
            http_response_code(401); // Unauthorized
            echo json_encode(['message' => 'Invalid or expired Phone OTP.']);
            unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']); // Clear phone OTP data on failure
            exit();
        }

        // If both OTPs are correct and session data is intact, proceed with user registration
        if (isset($_SESSION['temp_user_data'])) {
            $userData = $_SESSION['temp_user_data'];

            $conn = getDbConnection(); // Establish database connection

            // Hash the user's password securely using password_hash()
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            $cverifiedValue = $userData['cverified_value']; // This will be null from current frontend

            // Prepare SQL INSERT statement to add user data to the 'cuser' table
            $stmt = $conn->prepare("INSERT INTO cuser (company_name, email, password_hash, country_code, phone_number, is_verified, cverified) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $is_verified = 1; // Set is_verified to TRUE (1) as OTPs are verified

            // Bind parameters to the prepared statement to prevent SQL injection
            // 'sssssis' specifies the data types: 5 strings (company_name, email, password_hash, country_code, phone_number), 1 integer (for is_verified), 1 string (for cverified)
            $stmt->bind_param(
                "sssssis",
                $userData['company_name'],
                $email, // Use email from payload/session for consistency
                $hashedPassword,
                $userData['country_code'],
                $userData['phone_number'],
                $is_verified,
                $cverifiedValue
            );

            try {
                // Execute the prepared statement
                if ($stmt->execute()) {
                    // Clear all session data related to OTPs and temporary user data after successful registration
                    unset($_SESSION['email_otp'], $_SESSION['email_otp_email'], $_SESSION['email_otp_expiry']);
                    unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']);
                    unset($_SESSION['temp_user_data']);

                    http_response_code(200); // OK
                    echo json_encode(['message' => 'Account created and verified successfully!']);
                } else {
                    // Handle database errors (e.g., duplicate entry for unique fields)
                    $errorMessage = $conn->errno == 1062 ? 'Email or phone number already registered.' : 'Database error during registration.';
                    error_log("DB INSERT Error: " . $stmt->error); // Log the actual database error for debugging
                    http_response_code(500); // Internal Server Error
                    echo json_encode(['message' => $errorMessage]);
                }
            } finally {
                $stmt->close(); // Close the statement
                $conn->close(); // Close the database connection
            }
        } else {
            // This block should ideally not be reached if the flow is correct
            error_log("Verify OTP: temp_user_data missing after successful OTP verification.");
            // Clear any remaining OTP session data
            unset($_SESSION['email_otp'], $_SESSION['email_otp_email'], $_SESSION['email_otp_expiry']);
            unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']);
            http_response_code(400); // Bad Request
            echo json_encode(['message' => 'OTPs verified, but user data missing. Please try signing up again.']);
        }
        exit();

    // --- LOGIN ACTIONS (for 'cuser' table) ---

    // Action to authenticate user login
    } elseif ($action === 'login') {
        $password_input = $data['password'] ?? null;

        // Input validation
        if (empty($email) || empty($password_input)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email and password are required.']);
            exit();
        }

        $conn = getDbConnection();
        // Retrieve user's hashed password and verification status from the 'cuser' table
        $stmt = $conn->prepare("SELECT id, password_hash, is_verified FROM cuser WHERE email = ? LIMIT 1");
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
                    // In a real application, you would create a secure session (e.g., set $_SESSION variables)
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

    // --- PASSWORD RECOVERY ACTIONS (for 'cuser' table) ---

    // Action to send OTP for password reset
    } elseif ($action === 'send_reset_otp') {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['message' => 'Valid email address is required.']);
            exit();
        }

        $conn = getDbConnection();
        // Check if the email exists in the 'cuser' table and retrieve associated phone number
        $stmt_check = $conn->prepare("SELECT phone_number FROM cuser WHERE email = ? LIMIT 1");
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
        // If Phone OTP is also required, frontend must send it and backend must verify it.
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

        // Update the user's password in the 'cuser' table
        $stmt = $conn->prepare("UPDATE cuser SET password_hash = ? WHERE email = ?");
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
