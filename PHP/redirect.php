<?php 
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ../profile.html');
    exit();
} elseif (isset($_SESSION['cuser_id'])) {
    header('Location: ../company-profile.html');
    exit();
} else {
    header('Location: ../index.html');
    exit();
}
?>