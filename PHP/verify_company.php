<?php 

session_start();
include('config.php');

if (!isset($_SESSION['cuser_id'])) {
    header('Location: ../recruitersignin.html');
    exit();
}
else if ( isset($_SESSION['cuser_id'])) {
    $cuserId = $_SESSION['cuser_id'];
    $stmt = $conn->prepare("SELECT cverified FROM cuser WHERE id = ?");
    $stmt->bind_param("i", $cuserId);
    $stmt->execute();

    $stmt->bind_result($cverifiedStatus);
    $stmt->fetch();
    $stmt->close();
    $conn->close();

    if ($cverifiedStatus === 'TRUE') {
        header('Location: ../company-profile.html');
        exit();
    } else if ($cverifiedStatus === 'pending'){
        header('Location: ../wait.html');
        exit();
    }
    else if ($cverifiedStatus === 'FALSE'){
        header('Location: ../rejected.html');
        exit();
    }
    else if ($cverifiedStatus === 'null'){
        header('Location: ../company-test/index.html');
        exit();
    }else{
        header('Location: ../company-test/index.html');
        exit();
    }

}
?>