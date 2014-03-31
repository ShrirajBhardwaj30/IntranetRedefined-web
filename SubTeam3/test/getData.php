<?php
    $username = 'user';
    
    $con = mysqli_connect('127.13.169.129', 'kunal15595', '', 'grp3');
    $sql = "select data from url_monitor where user = '$username'";
    $resp = mysqli_query($con, $sql);
    
    $data = mysqli_fetch_assoc($resp);
    $data = json_decode($data['data']);
    
    $new = array();
    
    foreach($data as $key=>$value) {
        if($value != md5(file_get_contents($key))){
            echo "$key changed from $value<br/>";
            $new[$key]=md5(file_get_contents($key));
            
        } else {
            echo "$key unchanged!<br/>";
            $new[$key]=$value;
        }
    }
    
    $new = json_encode($new);
    $sql = "update url_monitor set data = '$new' where user = '$username'";
    $resp = mysqli_query($con, $sql);
?>