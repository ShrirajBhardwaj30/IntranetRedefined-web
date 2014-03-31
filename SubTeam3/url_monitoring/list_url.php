<?php
    require_once 'backbone.php';

    $user = $_POST['user'];
    $con = connectdb();
    
    $user = sanitize($con, $user);
    
    $resp = getResp($con, $user);
    
    if(mysqli_num_rows($resp)>0) {   
        $data = convArray($resp);
        echo convString($data, false);
    } else {
        echo "User does not exist.";
    }
?>