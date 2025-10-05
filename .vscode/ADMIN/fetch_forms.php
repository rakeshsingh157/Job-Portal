<?php
require("../PHP/config.php");

$sql = "SELECT id, cuser_id, company_name, created_at, status FROM companies ORDER BY created_at DESC";
$result = $conn->query($sql);

$forms = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $forms[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($forms);

$conn->close();
?>