<?php
    include_once '../config/config.php';

    if (!isset($_SESSION)) {
        session_start();
    }
    unset($_SESSION['user_nm']);
    unset($_SESSION['name']);
    unset($_SESSION['roll_number']);
    unset($_SESSION['class']);
    unset($_SESSION['pass_changed']);
    unset($_SESSION['last_date']);
    
    header("Location: ".constant("HOST")."/login.php");
?>
