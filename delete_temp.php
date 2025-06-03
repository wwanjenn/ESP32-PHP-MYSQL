<?php
$servername = "localhost";
$username = "root";
$password = "";
$database_name = "esp32_sensor";

if (!isset($_GET['id'])) {
    die("No ID provided.");
}

$temp_id = intval($_GET['id']);

$conn = new mysqli($servername, $username, $password, $database_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "DELETE FROM temp_data WHERE temp_id = $temp_id";

if ($conn->query($sql) === TRUE) {
    echo "Entry deleted successfully.";
} else {
    echo "Error deleting entry: " . $conn->error;
}

$conn->close();
?>
