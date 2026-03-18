<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_name    = mysqli_real_escape_string($conn, $_POST["item_name"]);
    $description  = mysqli_real_escape_string($conn, $_POST["description"]);
    $location     = mysqli_real_escape_string($conn, $_POST["location"]);
    $contact      = mysqli_real_escape_string($conn, $_POST["contact"]);
    $status       = mysqli_real_escape_string($conn, $_POST["status"]);
    $date_reported = mysqli_real_escape_string($conn, $_POST["date_reported"]);

    $sql = "INSERT INTO items (item_name, description, location, contact, status, date_reported)
            VALUES ('$item_name', '$description', '$location', '$contact', '$status', '$date_reported')";

    if (mysqli_query($conn, $sql)) {
        header("Location: index.php?success=1");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

mysqli_close($conn);
?>