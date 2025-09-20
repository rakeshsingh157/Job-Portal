
<?php
header('Content-Type: application/json');


require_once('config.php');

$response = ['success' => false, 'message' => ''];

if ($conn->connect_error) {
    $response['message'] = "Database connection failed: " . $conn->connect_error;
    echo json_encode($response);
    exit();
}


$sql = "SELECT Jobs.*, cuser.company_name, cuser.profile_photo 
        FROM Jobs 
        JOIN cuser ON Jobs.cuser_id = cuser.id";

$conditions = [];
$params = [];
$types = '';


if (!empty($_GET['job_title'])) {
    $keyword = '%' . $_GET['job_title'] . '%';
    $conditions[] = "(Jobs.job_title LIKE ? OR Jobs.job_desc LIKE ? OR Jobs.location LIKE ? OR Jobs.work_mode LIKE ? OR Jobs.experience LIKE ? OR Jobs.time LIKE ? OR Jobs.salary LIKE ? OR Jobs.skills LIKE ? OR cuser.company_name LIKE ?)";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= 'sssssssss';
}


if (!empty($_GET['location'])) {
    $keyword = '%' . $_GET['location'] . '%';
    $conditions[] = "(Jobs.location LIKE ?)";
    $params[] = $keyword;
    $types .= 's';
}


if (!empty($_GET['experience'])) {
    $experiences = explode(',', $_GET['experience']);
    $experience_conditions = [];
    foreach ($experiences as $exp) {

        if ($exp === 'Student') {
            $experience_conditions[] = "Jobs.experience LIKE ?";
            $params[] = '%Fresher%';
            $types .= 's';
        } else if ($exp === 'Entry Level') {
            $experience_conditions[] = "Jobs.experience LIKE ?";
            $params[] = '%1 Year%';
            $types .= 's';
        } else if ($exp === 'Middle Level') {
            $experience_conditions[] = "Jobs.experience LIKE ?";
            $params[] = '%2-4 Years%';
            $types .= 's';
        } else if ($exp === 'Senior Level') {
            $experience_conditions[] = "Jobs.experience LIKE ?";
            $params[] = '%5-10 Years%';
            $types .= 's';
        } else if ($exp === 'Director') {
            $experience_conditions[] = "Jobs.experience LIKE ?";
            $params[] = '%10+%';
            $types .= 's';
        } else {

            $experience_conditions[] = "Jobs.experience LIKE ?";
            $params[] = '%' . $exp . '%';
            $types .= 's';
        }
    }
    if (!empty($experience_conditions)) {
        $conditions[] = "(" . implode(' OR ', $experience_conditions) . ")";
    }
}


if (!empty($_GET['time'])) {
    $job_types = explode(',', $_GET['time']);
    $job_type_placeholders = implode(',', array_fill(0, count($job_types), '?'));
    $conditions[] = "Jobs.time IN ($job_type_placeholders)";
    foreach ($job_types as $jt) {
        $params[] = $jt;
        $types .= 's';
    }
}


if (!empty($_GET['salary'])) {
    $salaries = explode(',', $_GET['salary']);
    $salary_conditions = [];
    foreach ($salaries as $sal) {
        $salary_conditions[] = "Jobs.salary LIKE ?";
        $params[] = '%' . $sal . '%';
        $types .= 's';
    }
    if (!empty($salary_conditions)) {
        $conditions[] = "(" . implode(' OR ', $salary_conditions) . ")";
    }
}


if (!empty($_GET['work_mode'])) {
    $work_modes = explode(',', $_GET['work_mode']);
    $work_mode_placeholders = implode(',', array_fill(0, count($work_modes), '?'));
    $conditions[] = "Jobs.work_mode IN ($work_mode_placeholders)";
    foreach ($work_modes as $wm) {
        $params[] = $wm;
        $types .= 's';
    }
}


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
