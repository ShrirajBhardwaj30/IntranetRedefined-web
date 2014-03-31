<?php
    require_once 'backbone.php';

    $user = $_POST['user'];
    $url = $_POST['url'];

    if(checkURL($url)) {
        $con = connectdb();
        
        $user = sanitize($con, $user);
        $url = sanitize($con, $url);
        
        $resp = getResp($con, $user);
        
        if(mysqli_num_rows($resp)>0){
            $data = convArray($resp);
            
            $data = addURL($data, $url);
            $data = json_encode($data);
            
            $resp = updateData($con, $user, $data);
            
            if($resp){
                echo "URL added.";
            } else {
                echo "Connection error.";
            }
        } else {
            echo "User does not exist.";
        }
    } else {
        echo "URL does not exist.";
    }
?>