<?php

echo '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Home page</title>
    <script>
        function testPostAppSec() {
            // Get the value from the input field
            const requestBody = document.getElementById("request-body").value;
            // Create an XMLHttpRequest object
            const xhr = new XMLHttpRequest();
            // Set up the POST request to the same URL
            xhr.open("POST", window.location.href, true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

            // Handle the response
            xhr.onload = function () {
                document.getElementById("appsec-result").innerHTML = "Response status: " + xhr.status;
            };

            // Send the POST request with the desired body content
            xhr.send(requestBody);
        }
    </script>
</head>

<body>
    <h1>The way is clear!</h1>
    <p>In this example page, if you can see this text, the bouncer considers your IP as clean.</p>
    
    
    <p>Use the below form to send a POST request to the current url.</p>
    <label for="request-body">Request Body:</label>
    <input style="width:300px;" type="text" id="request-body" placeholder="class.module.classLoader.resources."/>
    
    <button id="appsec-post-button" onclick="testPostAppSec()">TEST POST APPSEC</button>
    
    <div id="appsec-result">INITIAL STATE</div>
</body>
</html>
';
