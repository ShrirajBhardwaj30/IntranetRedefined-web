<?php
     $http_client_ip=isset($_SERVER['HTTP_CLIENT_IP']);
     $http_x_forwarded_for=isset($_SERVER['HTTP_X_FORWARDED_FOR']);
     $remote_addr=$_SERVER['REMOTE_ADDR'];


     if(!empty($http_client_ip)){
        $ip_address=$http_client_ip;
     }
     else if(!empty($http_x_forwarded_for)){
        $ip_address=$http_x_forwarded_for;
     }
     else {
        $ip_address=$remote_addr;
     }
     if (!isset($_SESSION)) {
        session_start();
     }
     $_SESSION['ip_address'] = $ip_address;
     $_SESSION['login_time'] = date('m/d/Y h:i:s a', time());
?>