<?php

header('Content-Type: application/json');

require_once('config.php');

$response = ['success' => false, 'message' => ''];

if ($conn->connect_error) {
    $response['message'] = "Database connection failed: " . $conn->connect_error;
    echo json_encode($response);
    exit();
}

$job_id = isset($_GET['job_id']) ? $_GET['job_id'] : '';

if (empty($job_id)) {
    $response['message'] = 'Job ID not provided.';
    echo json_encode($response);
    exit();
}

$job_data = null;
$company_data = null;
$cuser_id = null;

$sql_job = "SELECT * FROM Jobs WHERE id = ?";

if ($stmt_job = $conn->prepare($sql_job)) {
    $stmt_job->bind_param("i", $job_id);
    if ($stmt_job->execute()) {
        $result_job = $stmt_job->get_result();
        if ($result_job->num_rows > 0) {
            $row_job = $result_job->fetch_assoc();
            $job_data = [
                'id' => $row_job['id'],
                'job_title' => $row_job['job_title'],
                'job_desc' => $row_job['job_desc'],
                'location' => $row_job['location'],
                'work_mode' => $row_job['work_mode'],
                'experience' => $row_job['experience'],
                'time' => $row_job['time'],
                'salary' => $row_job['salary'],
                'skills' => $row_job['skills'],
                'form_link' => $row_job['form_link'],
                'cuser_id' => $row_job['cuser_id']
            ];
            $cuser_id = (int)$row_job['cuser_id'];

        }
    }
    $stmt_job->close();
}


if ($job_data) {
    $sql_company = "SELECT company_name, overview, email, industry , profile_photo FROM cuser WHERE id = ?";
    if ($stmt_company = $conn->prepare($sql_company)) {
        $stmt_company->bind_param("i", $cuser_id);
        if ($stmt_company->execute()) {
            $result_company = $stmt_company->get_result();
            if ($result_company->num_rows > 0) {
                $row_company = $result_company->fetch_assoc();
                $company_data = [
                    'company_name' => $row_company['company_name'],
                    'overview' => $row_company['overview'],
                    'email' => $row_company['email'],
                    'industry' => $row_company['industry'],
                    'profile_photo' => $row_company['profile_photo']
                ];
            }
        }
        $stmt_company->close();
    }
}

if ($job_data) {
    $response['success'] = true;
    $response['job'] = $job_data;
    $response['company'] = $company_data;
} else {
    $response['message'] = 'Job not found.';
}

$conn->close();

echo json_encode($response);

?>