<?php

require_once "backbone.php";

$user = $_POST['user'];

$con = connectdb();

$user = sanitize($con, $user);

$resp = getResp($con, $user);

if(mysqli_num_rows($resp) > 0) {
    echo "User already exists.";
} else {
    $resp = addUser($con, $user);
    
    if($resp) {
        echo "User Added.";
    } else {
        echo "Connection error.";
    }
}

?>