<?php
    $con = mysqli_connect('127.13.169.129', 'kunal15595', '', 'grp3');
    $sql = "select * from url_monitor";
    $resp = mysqli_query($con, $sql);
    echo mysqli_num_rows($resp);

// '1' aa raha hai
?>


