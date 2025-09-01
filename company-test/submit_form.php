<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require('../PHP/config.php');
header('Content-Type: application/json');

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

$cuser_id = isset($_SESSION['cuser_id']) ? $_SESSION['cuser_id'] : 1; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $companyName = htmlspecialchars($_POST['company_name'] ?? '');
    $businessType = htmlspecialchars($_POST['business_type'] ?? '');
    $legalStatus = htmlspecialchars($_POST['legal_status'] ?? '');
    $registrationNumber = htmlspecialchars($_POST['registration_number'] ?? '');
    $dateEstablished = htmlspecialchars($_POST['date_established'] ?? '');
    $physicalAddress = htmlspecialchars($_POST['physical_address'] ?? '');
    $contactPerson = htmlspecialchars($_POST['contact_person'] ?? '');
    $contactTitle = htmlspecialchars($_POST['contact_title'] ?? '');
    $phone = htmlspecialchars($_POST['phone_number'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $website = htmlspecialchars($_POST['website'] ?? '');
    $natureOfBusiness = htmlspecialchars($_POST['nature_of_business'] ?? '');
    $productsServices = htmlspecialchars($_POST['products_services'] ?? '');
    $hoursOfOperation = htmlspecialchars($_POST['hours_of_operation'] ?? '');
    $numEmployees = filter_var($_POST['num_employees'] ?? 0, FILTER_VALIDATE_INT, array("options" => array("default" => 0)));

    if (empty($companyName) || empty($registrationNumber) || empty($physicalAddress) ||
        empty($contactPerson) || empty($phone) || empty($email) || empty($dateEstablished)) {
        echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
        $conn->close();
        exit();
    }

    $uploadedDocumentUrls = [];
    $filestack_key = "A3xfbwhHOSe25uFyU1V9Lz";

    if (!isset($filestack_key) || empty($filestack_key)) {
        error_log("Filestack API key is not configured.");
        echo json_encode(["success" => false, "message" => "Server error: Filestack API key is missing. Please contact support."]);
        $conn->close();
        exit();
    }

    if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
        $totalFiles = count($_FILES['documents']['name']);
        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = basename($_FILES['documents']['name'][$i]);
                $fileTmpName = $_FILES['documents']['tmp_name'][$i];
                $fileType = $_FILES['documents']['type'][$i];

                $endpoint = "https://www.filestackapi.com/api/store/S3?key={$filestack_key}&filename=" . urlencode($fileName);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                
                $cfile = new CURLFile($fileTmpName, $fileType, $fileName);
                curl_setopt($ch, CURLOPT_POSTFIELDS, ['fileUpload' => $cfile]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if (curl_errno($ch)) {
                    error_log("cURL Error uploading file '{$fileName}': " . curl_error($ch));
                } else if ($httpCode === 200) {
                    $responseData = json_decode($response, true);
                    if (isset($responseData['url'])) {
                        $uploadedDocumentUrls[] = $responseData['url'];
                    } else {
                        error_log("Filestack API Error: 'url' not found in response for file '{$fileName}'. Response: " . $response);
                    }
                } else {
                    error_log("Filestack API Error (HTTP {$httpCode}) for file '{$fileName}': " . $response);
                }
                curl_close($ch);
            } else if ($_FILES['documents']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                error_log("PHP File upload error for '{$fileName}': " . $_FILES['documents']['error'][$i]);
            }
        }
    }

    $documentsString = implode(", ", $uploadedDocumentUrls);

    $sql = "INSERT INTO companies (
        cuser_id, company_name, business_type, legal_status, registration_number,
        date_established, physical_address, contact_person, contact_title,
        phone_number, email, website, nature_of_business, products_services,
        hours_of_operation, num_employees, documents, status
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
    )";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Database error preparing statement: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Server error: Could not prepare database statement."]);
        $conn->close();
        exit();
    }

    $bindSuccess = $stmt->bind_param("issssssssssssssis",
        $cuser_id,
        $companyName,
        $businessType,
        $legalStatus,
        $registrationNumber,
        $dateEstablished,
        $physicalAddress,
        $contactPerson,
        $contactTitle,
        $phone,
        $email,
        $website,
        $natureOfBusiness,
        $productsServices,
        $hoursOfOperation,
        $numEmployees,
        $documentsString
    );

    if ($bindSuccess === false) {
        error_log("Error binding parameters: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Server error: Could not bind parameters."]);
        $stmt->close();
        $conn->close();
        exit();
    }

    if ($stmt->execute()) {
        $newCompanyId = $stmt->insert_id;

        $sql_update_cuser = "UPDATE cuser SET cverified = 'pending' WHERE id = ?";
        $stmt_update_cuser = $conn->prepare($sql_update_cuser);
        if ($stmt_update_cuser) {
            $stmt_update_cuser->bind_param("i", $cuser_id);
            if (!$stmt_update_cuser->execute()) {
                error_log("Error updating cuser status for ID {$cuser_id}: " . $stmt_update_cuser->error);
            }
            $stmt_update_cuser->close();
        } else {
            error_log("Error preparing cuser update statement: " . $conn->error);
        }

        echo json_encode(["success" => true, "message" => "Verification form submitted successfully! Your application ID is: #CV-" . $newCompanyId]);
    } else {
        error_log("Error executing company INSERT statement: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Error submitting form: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

$conn->close();
?>
