<?php

if (isset($_GET["temperature"]) && isset($_GET["humidity"])) {
    $temperature = $_GET["temperature"];
    $humidity = $_GET["humidity"];

    $servername = "localhost";
    $username = "root";
    $password = "";
    $database_name = "esp32_sensor";

    $connection = new mysqli($servername, $username, $password, $database_name);

    if ($connection->connect_error) {
        die("MySQL connection failed: " . $connection->connect_error);
    }

    // Insert both temperature and humidity
    $sql = "INSERT INTO temp_data (temp_value, humidity_value) VALUES ($temperature, $humidity)";

    if ($connection->query($sql) === TRUE) {
        echo "New record created successfully";
    } else {
        echo "Error: " . $sql . " => " . $connection->error;
    }

    $connection->close();
} else {
    echo "temperature and/or humidity is not set in the HTTP request";
}
?>
