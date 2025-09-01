<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

require_once 'db_config.php';

// Get the cuser_id parameter (which is actually the id field in database)
$companyId = $_GET['cuser_id'] ?? '';
if (empty($companyId)) {
    echo json_encode(['error' => 'cuser_id is required.']);
    exit;
}

try {
    // Get company basic info - use id field since that's what exists in your table
    $stmt = $conn->prepare("SELECT * FROM cuser WHERE id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result->fetch_assoc();
    $stmt->close();

    if ($company) {
        // Fetch job posts from database - use the id as cuser_id
        $jobStmt = $conn->prepare("SELECT * FROM Jobs WHERE cuser_id = ?");
        $jobStmt->bind_param("s", $companyId);
        $jobStmt->execute();
        $jobResult = $jobStmt->get_result();
        
        $job_posts = [];
        while ($job = $jobResult->fetch_assoc()) {
            // Convert skills string to array
            $job['skills'] = !empty($job['skills']) ? explode(',', $job['skills']) : [];
            $job_posts[] = $job;
        }
        $jobStmt->close();
        
        $company['job_posts'] = $job_posts;

        // Remove sensitive fields
        unset($company['password_hash']);

        echo json_encode($company);
    } else {
        echo json_encode(['error' => 'Company not found.']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>