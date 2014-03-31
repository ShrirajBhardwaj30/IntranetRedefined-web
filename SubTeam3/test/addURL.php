<?php
    $url = "www.youtube.com";
    $username = "user";
    
    $con = mysqli_connect('127.13.169.129', 'kunal15595', '', 'grp3');
    
    $sql = "select data from url_monitor where user = '$username'";
    $resp = mysqli_query($con, $sql);
    $data = mysqli_fetch_assoc($resp);
    
    $data = json_decode($data['data']);
    //print_r( $data);echo '<br>';
    
    $new=array();
    foreach($data as $key=>$value){
        $new[$key]=$value;
    }
    $new[$url]=md5(file_get_contents($url));
    $new = json_encode($new);
    
    $sql = "update url_monitor set data = '$new' where user = '$username'";
    $resp = mysqli_query($con, $sql);
?>