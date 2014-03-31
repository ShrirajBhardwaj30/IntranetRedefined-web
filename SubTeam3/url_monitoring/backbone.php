<?php

    //connection to database
    function connectdb() {
        return mysqli_connect('127.13.169.129', 'kunal15595', '', 'grp3');   
    }
    
    //run a select query and return response
    function getResp($con, $user){
        $sql = "select * from url_monitor where user = '$user'";
        return mysqli_query($con, $sql);
    }
    
    //converts reponse to array with json data then to array
    function convArray($resp){
        $data = mysqli_fetch_assoc($resp);
        return json_decode($data['data']);
    }
    
    //adds url to given list and returns the final list
    function addURL($olddata, $url){
        $updated=array();
        
        foreach($olddata as $key=>$value){
            $updated[$key]=$value;
        }
        
        $updated[$url]=md5(file_get_contents($url));
        return $updated;
    }
    
    //runs query to update data and returns response
    function updateData($con, $user ,$data) {
        $sql = "update url_monitor set data = '$data' where user = '$user'";
        return mysqli_query($con, $sql);
    }
    
    //converts array to appropriate string to display, depending whether value is required or not
    function convString($data, $val=true) {
        $str = "";
        
        foreach($data as $key=>$value) {
            if($val)
                $str = $str . $key . "=>" . $value . '<br/>';
            else
                $str = $str . $key . '<br/>';
        }
        
        return $str;
    }
    
    //takes input as old array returns updated hash values and changes
    function updateHash($olddata) {
        $new = array();
        
        foreach($olddata as $key=>$value) {
            $hash = md5(file_get_contents($key));
            if($hash != $value)
                $new['changes'][$key]=$hash;    
            $new['updated'][$key]=$hash;
        }
        
        return $new;
    }
    
    //check if url exists
    function checkURL($url) {
        $headers = @get_headers($url);
        if(strpos($headers[0],'404')===true)
            return false;
        else
            return true;
    }
    
    //sanitize user input
    function sanitize($con, $input) {
        return mysqli_real_escape_string($con, $input);
    }
    
    //add new user and reurn response
    function addUser($con, $user) {
        $sql = "insert into url_monitor (user) values ('$user')";
        return mysqli_query($con, $sql);
    }
?>