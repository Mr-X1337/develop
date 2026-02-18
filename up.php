<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['files'])) {

    foreach ($_FILES['files']['name'] as $index => $name) {

        // Check for upload errors
        if ($_FILES['files']['error'][$index] === 0) {

            // Target path = current folder
            $target = __DIR__ . "/" . basename($name);

            if (move_uploaded_file($_FILES['files']['tmp_name'][$index], $target)) {
                $messages[] = "Uploaded: $name";
            } else {
                $messages[] = "Failed: $name";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Multi-File Uploader</title>
</head>
<body>

<h3>Select files to upload</h3>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="files[]" multiple required>
    <br><br>
    <button type="submit">Upload</button>
</form>

<?php
if (!empty($messages)) {
    echo "<hr>";
    foreach ($messages as $msg) {
        echo "<div>$msg</div>";
    }
}
?>

</body>
</html>
