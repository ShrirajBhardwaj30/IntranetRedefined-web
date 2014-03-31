<?php
    require_once 'backbone.php';

    $user = $_POST['user'];
    
    $con = connectdb();
    
    $user = sanitize($con, $user);

    $resp = getResp($con, $user);
    
    if(mysqli_num_rows($resp)>0){
        $data = convArray($resp);
        $data = updateHash($data);
        
        $updated = json_encode($data['updated']);
        
        $resp = updateData($con, $user, $updated);
        
        if($resp) {
            if(isset($data['changes'])) {
                echo "Updated sites: <br>" . convString($data['changes'],false);
            } else {
                echo "No changes.";
            }
        } else {
            echo "Connection error.";
        }
    } else {
        echo "User does not exist.";
    }
?>