<?php
$servername = "localhost";
$username = "root";
$password = "";
$database_name = "esp32_sensor";

// Create connection
$conn = new mysqli($servername, $username, $password, $database_name);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo "Connection failed: " . $conn->connect_error;
    exit();
}

$sql = "
DELETE FROM temp_data 
WHERE temp_id NOT IN (
    SELECT temp_id FROM (
        SELECT temp_id FROM temp_data ORDER BY date_collected DESC LIMIT 3
    ) x
);
";

if ($conn->query($sql) === TRUE) {
    echo "Old entries cleared, latest 3 kept.";
} else {
    http_response_code(500);
    echo "Error deleting records: " . $conn->error;
}

$conn->close();
?>
