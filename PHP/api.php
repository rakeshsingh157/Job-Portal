<?php
header('Content-Type: application/json');

// Database connection
require_once('config.php');

$response = ['success' => false, 'message' => ''];

if ($conn->connect_error) {
    $response['message'] = "Database connection failed: " . $conn->connect_error;
    echo json_encode($response);
    exit();
}

// Start building the SQL query
$sql = "SELECT Jobs.*, cuser.company_name, cuser.profile_photo 
        FROM Jobs 
        JOIN cuser ON Jobs.cuser_id = cuser.id";

$conditions = [];
$params = [];
$types = '';

// Handle keyword/job title search
if (!empty($_GET['job_title'])) {
    $keyword = '%' . $_GET['job_title'] . '%';
    $conditions[] = "(Jobs.job_title LIKE ? OR Jobs.skills LIKE ? OR cuser.company_name LIKE ?)";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= 'sss';
}

// Handle location search/filter
if (!empty($_GET['location'])) {
    $keyword = '%' . $_GET['location'] . '%';
    $conditions[] = "(Jobs.location LIKE ?)";
    $params[] = $keyword;
    $types .= 's';
}

// Handle checkbox filters for 'experience'
if (!empty($_GET['experience'])) {
    $experiences = explode(',', $_GET['experience']);
    $experience_placeholders = implode(',', array_fill(0, count($experiences), '?'));
    $conditions[] = "Jobs.experience IN ($experience_placeholders)";
    foreach ($experiences as $exp) {
        $params[] = $exp;
        $types .= 's';
    }
}

// Handle checkbox filters for 'job type' (time)
if (!empty($_GET['time'])) {
    $job_types = explode(',', $_GET['time']);
    $job_type_placeholders = implode(',', array_fill(0, count($job_types), '?'));
    $conditions[] = "Jobs.time IN ($job_type_placeholders)";
    foreach ($job_types as $jt) {
        $params[] = $jt;
        $types .= 's';
    }
}

// Handle checkbox filters for 'salary'
if (!empty($_GET['salary'])) {
    $salaries = explode(',', $_GET['salary']);
    $salary_placeholders = implode(',', array_fill(0, count($salaries), '?'));
    $conditions[] = "Jobs.salary IN ($salary_placeholders)";
    foreach ($salaries as $sal) {
        $params[] = $sal;
        $types .= 's';
    }
}

// Handle checkbox filters for 'work_mode'
if (!empty($_GET['work_mode'])) {
    $work_modes = explode(',', $_GET['work_mode']);
    $work_mode_placeholders = implode(',', array_fill(0, count($work_modes), '?'));
    $conditions[] = "Jobs.work_mode IN ($work_mode_placeholders)";
    foreach ($work_modes as $wm) {
        $params[] = $wm;
        $types .= 's';
    }
}

// Append WHERE clause if conditions exist
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY Jobs.id DESC";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $response['message'] = "Prepare failed: " . $conn->error;
    echo json_encode($response);
    exit();
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
}

$response['success'] = true;
$response['jobs'] = $jobs;

echo json_encode($response);

$stmt->close();
$conn->close();
?>
