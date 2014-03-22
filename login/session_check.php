<?php
    if (!isset($_SESSION)) {
        session_start();
    }
    include_once '../config/config.php';
    if (!isset($_SESSION['user_nm'])) {
        header("Location: ".constant("HOST")."/login/login.php");
    }
?>