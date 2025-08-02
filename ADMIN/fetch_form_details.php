<?php
require("../config.php");

// Check if form ID is provided and is a valid number
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $form_id = $_GET['id'];

    // Fetch all details for the given form ID
    $sql = "SELECT * FROM companies WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $form_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($row);
    } else {
        header('Content-Type: application/json');
        echo json_encode(["error" => "No record found for the given ID."]);
    }

    $stmt->close();
} else {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Invalid form ID provided."]);
}

$conn->close();
?>
