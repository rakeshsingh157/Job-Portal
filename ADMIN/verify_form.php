<?php
require("../config.php");

// Check if form ID and action are provided
if (isset($_POST['id']) && is_numeric($_POST['id']) && isset($_POST['action'])) {
    $form_id = $_POST['id'];
    $action = $_POST['action']; // Expected values: 'done', 'rejected', or 'pending'

    $sql = "";
    $message = "";
    
    // Set the SQL query and message based on the action
    if ($action === 'done') {
        $sql = "UPDATE companies SET status = 'done' WHERE id = ?";
        $message = "Form ID " . $form_id . " has been verified.";
    } elseif ($action === 'rejected') {
        $sql = "UPDATE companies SET status = 'rejected' WHERE id = ?";
        $message = "Form ID " . $form_id . " has been rejected.";
    } elseif ($action === 'pending') {
        $sql = "UPDATE companies SET status = 'pending' WHERE id = ?";
        $message = "Form ID " . $form_id . " has been set back to pending.";
    } else {
        echo json_encode(["success" => false, "message" => "Invalid action provided."]);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        echo json_encode(["success" => false, "message" => "Error preparing statement: " . $conn->error]);
        $conn->close();
        exit;
    }

    $stmt->bind_param("i", $form_id);

    if ($stmt->execute()) {
        // Handle updates to the cuser table
        $sql_get_cuser = "SELECT cuser_id FROM companies WHERE id = ?";
        $stmt_get_cuser = $conn->prepare($sql_get_cuser);
        $stmt_get_cuser->bind_param("i", $form_id);
        $stmt_get_cuser->execute();
        $result = $stmt_get_cuser->get_result();
        $row = $result->fetch_assoc();
        $cuser_id = $row['cuser_id'];
        $stmt_get_cuser->close();
        
        $cverified_status = ($action === 'done') ? 'TRUE' : 'FALSE';
        
        $sql_update_cuser = "UPDATE cuser SET cverified = ? WHERE id = ?";
        $stmt_update_cuser = $conn->prepare($sql_update_cuser);
        $stmt_update_cuser->bind_param("si", $cverified_status, $cuser_id);
        $stmt_update_cuser->execute();
        $stmt_update_cuser->close();
        
        echo json_encode(["success" => true, "message" => $message]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating record: " . $conn->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid form ID or action provided."]);
}

$conn->close();
?>
