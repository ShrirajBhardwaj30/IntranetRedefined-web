<html>
    <head>
    </head>

    <body>
        <span>User : </span><input type="text" id="user"/><br/>
        <span>URL  : </span><input type="text" id="url"/><br/>
        <input type="button" value="Add User" id="addUser" />
        <input type="button" value="Add URL" id="addURL" />
        <input type="button" value="Check" id="check" />
        <input type="button" value="List all URLs" id="list" />
        
        <p id="results">
        </p>
        
        <script src="http://code.jquery.com/jquery-2.1.0.min.js"></script>
        <script>
            $(document).ready(function(){
                $('input#addURL').click(function(){
                    $.post("add_url.php", {user: $('input#user').val(), url: $('input#url').val()}, function(resp){
                        $('p#results').html(resp);
                    });
                });
                
                $('input#list').click(function(){
                    $.post("list_url.php", {user: $('input#user').val()}, function(resp){
                        $('p#results').html(resp);
                    });
                });
                
                $('input#addUser').click(function(){
                    $.post("add_user.php", {user: $('input#user').val()}, function(resp){
                        $('p#results').html(resp);
                    });
                });
                
                $('input#check').click(function(){
                    $.post("check.php", {user: $('input#user').val()}, function(resp){
                        $('p#results').html(resp);
                    });
                });
            });
        </script>
    </body>

</html>