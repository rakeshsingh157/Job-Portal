<?php
// Start a session to access the current user ID
session_start();

// Check if the user is logged in
if (!isset($_SESSION['cuser_id'])) {
    header("location:../recruitersignin.html");
    exit(); // Always exit after a header redirect
}

// NOTE: You are using require('../config.php');
// Make sure this file contains your database connection logic
// that creates a $conn variable using the object-oriented style.
require('../config.php');

// Get user ID from the session
$cuser_id = $_SESSION['cuser_id'];

// Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize and validate all form inputs
    $companyName = htmlspecialchars($_POST['companyName'] ?? '');
    $businessType = htmlspecialchars($_POST['businessType'] ?? '');
    $legalStatus = htmlspecialchars($_POST['legalStatus'] ?? '');
    $registrationNumber = htmlspecialchars($_POST['registrationNumber'] ?? '');
    $dateEstablished = htmlspecialchars($_POST['dateEstablished'] ?? '');
    $address = htmlspecialchars($_POST['address'] ?? '');
    $contactPerson = htmlspecialchars($_POST['contactPerson'] ?? '');
    $contactTitle = htmlspecialchars($_POST['contactTitle'] ?? '');
    $phone = htmlspecialchars($_POST['phone'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $website = htmlspecialchars($_POST['website'] ?? '');
    $natureOfBusiness = htmlspecialchars($_POST['natureOfBusiness'] ?? '');
    $productsServices = htmlspecialchars($_POST['productsServices'] ?? '');
    $hoursOfOperation = htmlspecialchars($_POST['hoursOfOperation'] ?? '');
    $numEmployees = htmlspecialchars($_POST['numEmployees'] ?? 0);
    $documents = isset($_POST['documents']) ? implode(", ", $_POST['documents']) : '';

   
    $sql = "INSERT INTO companies (
        cuser_id, company_name, business_type, legal_status, registration_number,
        date_established, physical_address, contact_person, contact_title,
        phone_number, email, website, nature_of_business, products_services,
        hours_of_operation, num_employees, documents
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("issssssssssssssis",
        $cuser_id,
        $companyName,
        $businessType,
        $legalStatus,
        $registrationNumber,
        $dateEstablished,
        $address,
        $contactPerson,
        $contactTitle,
        $phone,
        $email,
        $website,
        $natureOfBusiness,
        $productsServices,
        $hoursOfOperation,
        $numEmployees,
        $documents
    );


    if ($stmt->execute()) {
        echo "<h1>Verification form submitted successfully!</h1>";
        echo "<p>Your data has been saved with ID: " . $stmt->insert_id . "</p>";

  
        $sql_update_cuser = "UPDATE cuser SET cverified = 'pending' WHERE id = ?";
        $stmt_update_cuser = $conn->prepare($sql_update_cuser);
        
        if ($stmt_update_cuser === false) {
            echo "Error preparing cuser update statement: " . $conn->error;
        } else {
            $stmt_update_cuser->bind_param("i", $cuser_id);
            if ($stmt_update_cuser->execute()) {
                echo "<p>Your company's verification status has been set to 'pending'.</p>";
            } else {
                echo "<p>Error updating cuser status: " . $conn->error . "</p>";
            }
            $stmt_update_cuser->close();
        }

    } else {
        echo "<h1>Error submitting form.</h1>";
        echo "<p>Please try again. Error: " . $stmt->error . "</p>";
    }

    $stmt->close();
}


$conn->close();
?>