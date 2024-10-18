<?php
// Handle image upload if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['image'])) {
    header('Content-Type: application/json'); // Ensure JSON content-type

    // Check for upload errors
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        $uploadFile = $uploadDir . basename($_FILES['image']['name']);

        // Ensure the uploads directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Move the uploaded file to the uploads directory
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
            $response = ['success' => true, 'filepath' => $uploadFile];
        } else {
            $response = ['success' => false, 'message' => 'Failed to move uploaded file.'];
        }
    } else {
        $response = ['success' => false, 'message' => 'No image uploaded or there was an error uploading the file.'];
    }

    echo json_encode($response); // Output the JSON response
    exit; // Prevent further rendering of the page
}

// If it's not a POST request, render the HTML page as normal
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload</title>
</head>
<body>
<h2>Upload an Image</h2>

<form id="uploadForm" enctype="multipart/form-data" method="POST">
    <input type="file" id="imageInput" name="image" accept="image/*">
    <input type="submit" value="Upload Image">
</form>

<div id="result"></div>

<script>
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '', true); // Submit to the same file
        xhr.onload = function () {
            if (xhr.status === 200) {
                console.log(xhr.responseText);
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Display the uploaded image in the div
                        var resultDiv = document.getElementById('result');
                        resultDiv.innerHTML = `<img src="${response.filepath}" alt="Uploaded Image">`;
                    } else {
                        alert('Error: ' + response.message);
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    console.error(xhr.responseText);
                }
            } else {
                var resultDiv = document.getElementById('result');
                resultDiv.innerHTML = xhr.status;
            }
        };
        xhr.send(formData);
    });
</script>
</body>
</html>
