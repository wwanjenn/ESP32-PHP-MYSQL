<?php
$servername = "localhost";
$username = "root";
$password = "";
$database_name = "esp32_sensor";

// Create connection
$conn = new mysqli($servername, $username, $password, $database_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT temp_id, temp_value, humidity_value, date_collected FROM temp_data ORDER BY date_collected DESC";
$result = $conn->query($sql);

$timestamps = [];
$temperatures = [];
$humidities = [];
$rows = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $timestamps[] = $row['date_collected'];
        $temperatures[] = floatval($row['temp_value']);
        $humidities[] = floatval($row['humidity_value']);
        $rows[] = $row;
    }
} else {
    echo "No data found.";
    exit();
}

$conn->close();

// Calculations for stats
function trendArrow($current, $previous) {
    if ($current > $previous) return '↑';
    if ($current < $previous) return '↓';
    return '→';
}

$latestTemp = end($temperatures) ?? 'N/A';
$prevTemp = $temperatures[count($temperatures) - 2] ?? $latestTemp;
$tempTrend = trendArrow($latestTemp, $prevTemp);

$latestHumid = end($humidities) ?? 'N/A';
$prevHumid = $humidities[count($humidities) - 2] ?? $latestHumid;
$humidTrend = trendArrow($latestHumid, $prevHumid);

$totalEntries = count($rows);

$avgTemp = $totalEntries ? round(array_sum($temperatures)/$totalEntries, 2) : 'N/A';
$avgHumid = $totalEntries ? round(array_sum($humidities)/$totalEntries, 2) : 'N/A';

$minTemp = $totalEntries ? min($temperatures) : 'N/A';
$maxTemp = $totalEntries ? max($temperatures) : 'N/A';
$minHumid = $totalEntries ? min($humidities) : 'N/A';
$maxHumid = $totalEntries ? max($humidities) : 'N/A';

$lastUpdated = end($timestamps) ?? 'N/A';

// ✅ Added for humidity 56 check
$humidity56Count = 0;
foreach ($humidities as $h) {
    if ($h == 56.0) {
        $humidity56Count++;
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>ESP32 Sensor Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<style>
    /* Reset and base */
    * {
        box-sizing: border-box;
    }
    body {
        margin: 0; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #121212;
        color: #eee;
    }
    h1, h2, h3 {
        margin: 0;
    }
    /* Header */
    header {
        background: #1f2937;
        padding: 20px 40px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 6px rgba(0,0,0,0.6);
    }
    header h1 {
        font-size: 28px;
        color: #4ade80;
        font-weight: 700;
        letter-spacing: 1.2px;
    }
    header .fa-thermometer-half {
        margin-right: 12px;
        color: #4ade80;
    }

    /* Main container */
    main {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px 60px 20px;
    }

    /* Info Cards */
    .info-cards {
        display: flex;
        gap: 20px;
        margin-bottom: 40px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .card {
        background: #1e293b;
        padding: 20px 30px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        flex: 1 1 220px;
        color: #d1d5db;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: background-color 0.3s ease;
        position: relative;
    }
    .card:hover {
        background: #4ade80;
        color: #121212;
        cursor: default;
    }
    .card .icon {
        font-size: 36px;
        flex-shrink: 0;
    }
    .card .content {
        display: flex;
        flex-direction: column;
    }
    .card .content .label {
        font-weight: 600;
        font-size: 16px;
        opacity: 0.7;
    }
    .card .content .value {
        font-size: 28px;
        font-weight: 700;
    }
    .card .trend {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 24px;
        user-select: none;
    }
    .trend.up { color: #22c55e; }
    .trend.down { color: #ef4444; }
    .trend.flat { color: #9ca3af; }

    /* Dashboard Panels */
    .dashboard {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-bottom: 50px;
    }
    .panel {
        background: #1e293b;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        padding: 20px 25px;
        color: #d1d5db;
    }
    .panel h2 {
        color: #4ade80;
        margin-bottom: 20px;
        font-weight: 700;
        font-size: 22px;
        text-align: center;
    }
    .panel canvas {
        width: 100% !important;
        height: 300px !important;
    }

    /* Tables container */
    .tables {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 30px;
    }
    .table-block {
        background: #1e293b;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        padding: 20px;
        color: #d1d5db;
        overflow-x: auto;
    }
    .table-block h2 {
        color: #4ade80;
        margin-bottom: 15px;
        font-weight: 700;
        font-size: 20px;
        text-align: center;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        color: #d1d5db;
    }
    th, td {
        padding: 10px;
        border-bottom: 1px solid #334155;
        text-align: center;
    }
    th {
        background-color: #4ade80;
        color: #121212;
        position: sticky;
        top: 0;
    }
    tr:hover {
        background-color: #22c55e;
        color: #121212;
    }
    /* Buttons */
    .button-container {
        text-align: center;
        margin-bottom: 15px;
    }
    .collect-btn {
        background-color: #22c55e;
        color: #121212;
        border: none;
        padding: 10px 18px;
        font-size: 14px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.3s ease;
    }
    .collect-btn:hover {
        background-color: #16a34a;
    }
    button.delete-btn {
        background: none;
        border: none;
        cursor: pointer;
    }
    button.delete-btn i {
        color: #ef4444;
        font-size: 18px;
        transition: color 0.2s ease;
    }
    button.delete-btn:hover i {
        color: #b91c1c;
    }

    /* Responsive */
    @media (max-width: 600px) {
        header {
            flex-direction: column;
            gap: 10px;
        }
        .info-cards {
            flex-direction: column;
            gap: 15px;
        }
        .dashboard, .tables {
            grid-template-columns: 1fr;
        }
    }
</style>
    <meta http-equiv="refresh" content="15">
</head>
<body>

<header>
    <h1><i class="fas fa-thermometer-half"></i> ESP32 Sensor Dashboard</h1>
</header>

<main>
    <!-- Info cards -->
    <section class="info-cards" aria-label="Summary information cards">
        <div class="card" role="region" aria-label="Latest temperature value">
            <div class="icon"><i class="fas fa-temperature-high"></i></div>
            <div class="content">
                <div class="label">Latest Temperature</div>
                <div class="value"><?php echo htmlspecialchars($latestTemp); ?> °C</div>
            </div>
            <div class="trend <?php echo $tempTrend === '↑' ? 'up' : ($tempTrend === '↓' ? 'down' : 'flat'); ?>" aria-label="Temperature trend"><?php echo $tempTrend; ?></div>
        </div>

        <div class="card" role="region" aria-label="Latest humidity value">
            <div class="icon"><i class="fas fa-tint"></i></div>
            <div class="content">
                <div class="label">Latest Humidity</div>
                <div class="value"><?php echo htmlspecialchars($latestHumid); ?> %</div>
            </div>
            <div class="trend <?php echo $humidTrend === '↑' ? 'up' : ($humidTrend === '↓' ? 'down' : 'flat'); ?>" aria-label="Humidity trend"><?php echo $humidTrend; ?></div>
        </div>

        <div class="card" role="region" aria-label="Total entries recorded">
            <div class="icon"><i class="fas fa-database"></i></div>
            <div class="content">
                <div class="label">Total Entries</div>
                <div class="value"><?php echo $totalEntries; ?></div>
            </div>
        </div>

        <div class="card" role="region" aria-label="Average temperature value">
            <div class="icon"><i class="fas fa-chart-area"></i></div>
            <div class="content">
                <div class="label">Average Temperature</div>
                <div class="value"><?php echo $avgTemp; ?> °C</div>
            </div>
        </div>

        <div class="card" role="region" aria-label="Average humidity value">
            <div class="icon"><i class="fas fa-chart-area"></i></div>
            <div class="content">
                <div class="label">Average Humidity</div>
                <div class="value"><?php echo $avgHumid; ?> %</div>
            </div>
        </div>

        <div class="card" role="region" aria-label="Temperature min and max values">
            <div class="icon"><i class="fas fa-temperature-low"></i></div>
            <div class="content">
                <div class="label">Temp Min / Max</div>
                <div class="value"><?php echo $minTemp; ?>°C / <?php echo $maxTemp; ?>°C</div>
            </div>
        </div>

        <div class="card" role="region" aria-label="Humidity min and max values">
            <div class="icon"><i class="fas fa-water"></i></div>
            <div class="content">
                <div class="label">Humidity Min / Max</div>
                <div class="value"><?php echo $minHumid; ?>% / <?php echo $maxHumid; ?>%</div>
            </div>
        </div>

        <div class="card" role="region" aria-label="Last data update timestamp">
            <div class="icon"><i class="fas fa-clock"></i></div>
            <div class="content">
                <div class="label">Last Updated</div>
                <div class="value"><?php echo htmlspecialchars($lastUpdated); ?></div>
            </div>
        </div>
    <div class="card" role="region" aria-label="Humidity value 56 count" style="text-align: center;">
        <div class="icon"><i class="fas fa-water"></i></div>
        <div class="content">
            <div class="label">Entries with 56% Humidity</div>
            <div class="value"><?php echo $humidity56Count; ?></div>
        </div>
    </div>

        </section>

    <!-- Dashboard charts -->
    <section class="dashboard" aria-label="Sensor data charts">
        <div class="panel">
            <h2><i class="fas fa-chart-bar"></i> Temperature Monitoring</h2>
            <canvas id="tempChart" aria-label="Temperature over time bar chart" role="img"></canvas>
        </div>

        <div class="panel">
            <h2><i class="fas fa-chart-bar"></i> Humidity Monitoring</h2>
            <canvas id="humidChart" aria-label="Humidity over time bar chart" role="img"></canvas>
        </div>
    </section>

    <!-- Tables -->
    <section class="tables" aria-label="Sensor data tables">
        <div class="table-block" aria-live="polite">
            <h2>Temperature Data</h2>

            <div class="button-container">
                <button class="collect-btn" style="background-color: #ef4444;" onclick="clearOldEntries('temp')">
                    <i class="fas fa-trash-alt"></i> Clear Temperature Entries
                </button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Temp ID</th>
                        <th>Temperature (°C)</th>
                        <th>Date Collected</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['temp_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['temp_value']); ?></td>
                        <td><?php echo htmlspecialchars($row['date_collected']); ?></td>
                        <td>
                            <button class="delete-btn" aria-label="Delete temperature entry <?php echo $row['temp_id']; ?>" 
                                    onclick="deleteEntry(<?php echo $row['temp_id']; ?>, 'temp')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-block" aria-live="polite">
            <h2>Humidity Data</h2>

            <div class="button-container">
                <button class="collect-btn" style="background-color: #ef4444;" onclick="clearOldEntries('humid')">
                    <i class="fas fa-trash-alt"></i> Clear Humidity Entries
                </button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Humidity ID</th>
                        <th>Humidity (%)</th>
                        <th>Date Collected</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['temp_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['humidity_value']); ?></td>
                        <td><?php echo htmlspecialchars($row['date_collected']); ?></td>
                        <td>
                            <button class="delete-btn" aria-label="Delete humidity entry <?php echo $row['temp_id']; ?>" 
                                    onclick="deleteEntry(<?php echo $row['temp_id']; ?>, 'humid')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
    const labels = <?php echo json_encode($timestamps); ?>;
    const tempData = <?php echo json_encode($temperatures); ?>;
    const humidData = <?php echo json_encode($humidities); ?>;

    // Bar Chart Config for Temperature
    const tempChartConfig = {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Temperature (°C)',
                data: tempData,
                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                borderColor: 'rgba(22, 163, 74, 1)',
                borderWidth: 1,
                hoverBackgroundColor: 'rgba(34, 197, 94, 1)'
            }]
        },
        options: {
            responsive: true,
            animation: {
                duration: 800,
                easing: 'easeOutQuad'
            },
            scales: {
                x: {
                    title: { display: true, text: 'Timestamp' },
                    ticks: { maxRotation: 45, minRotation: 30 }
                },
                y: {
                    title: { display: true, text: 'Temperature (°C)' },
                    min: 0
                }
            },
            plugins: {
                legend: { display: true, position: 'top' }
            }
        }
    };
    new Chart(document.getElementById('tempChart'), tempChartConfig);

    // Bar Chart Config for Humidity
    const humidChartConfig = {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Humidity (%)',
                data: humidData,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(37, 99, 235, 1)',
                borderWidth: 1,
                hoverBackgroundColor: 'rgba(59, 130, 246, 1)'
            }]
        },
        options: {
            responsive: true,
            animation: {
                duration: 800,
                easing: 'easeOutQuad'
            },
            scales: {
                x: {
                    title: { display: true, text: 'Timestamp' },
                    ticks: { maxRotation: 45, minRotation: 30 }
                },
                y: {
                    title: { display: true, text: 'Humidity (%)' },
                    min: 0,
                    max: 100
                }
            },
            plugins: {
                legend: { display: true, position: 'top' }
            }
        }
    };
    new Chart(document.getElementById('humidChart'), humidChartConfig);

    // Delete an entry (type can be 'temp' or 'humid')
    function deleteEntry(id, type) {
        if (!confirm("Delete this " + (type === 'temp' ? 'temperature' : 'humidity') + " entry?")) return;
        fetch(`delete_temp.php?id=${id}&type=${type}`)
            .then(res => res.text())
            .then(data => {
                alert("Server Response:\n" + data);
                location.reload();
            })
            .catch(err => alert("Error:\n" + err));
    }

    // Clear old entries (keep last 2) for temperature or humidity
    function clearOldEntries(type) {
        if (!confirm("Delete all but the latest 3 " + (type === 'temp' ? 'temperature' : 'humidity') + " entries?")) return;
        fetch(`clear_entries.php?type=${type}`)
            .then(res => res.text())
            .then(data => {
                alert("Server Response:\n" + data);
                location.reload();
            })
            .catch(err => alert("Error:\n" + err));
    }
</script>

</body>
</html>
