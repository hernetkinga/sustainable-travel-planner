<?php 
include 'includes/header.php'; 
include 'config/config.php';
include 'classes/CarbonCalculator.php';
include 'classes/GoogleMapsAPI.php';
include 'classes/WeatherAPI.php';

$co2Result = null;
$distance = null;
$weatherData = null;
$origin = '';
$destination = '';
$transport = isset($_POST['transport']) ? $_POST['transport'] : 'Car';
$routeData = []; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $origin = trim($_POST['origin']);
    $destination = trim($_POST['destination']);

    if (!empty($origin) && !empty($destination)) {
        $routeData = GoogleMapsAPI::getEcoRoute($origin, $destination, $transport);
        if (!empty($routeData['modeDistances'])) {
            $co2Result = 0;
            $distance = 0;
            foreach ($routeData['modeDistances'] as $mode => $dist) {
                $distance += $dist;
                $co2Result += CarbonCalculator::calculate($dist, $mode);
            }
        }

        $weatherData = WeatherAPI::getWeather($destination);
    }
}
?>

<section class="calculator">
    <div class="calculator-grid">

        <!-- Route Form -->
        <div class="card">
            <h2>Choose Your Route</h2>
            <form method="post" action="calculator.php">
                <input type="text" id="origin" name="origin" value="<?php echo htmlspecialchars($origin); ?>" placeholder="Enter your starting point" required>
                <input type="text" id="destination" name="destination" value="<?php echo htmlspecialchars($destination); ?>" placeholder="Enter your destination" required>

                <select id="transport" name="transport" required>
                    <option value="Car" <?= ($transport == 'Car') ? 'selected' : '' ?>>Car</option>
                    <option value="Motorcycle" <?= ($transport == 'Motorcycle') ? 'selected' : '' ?>>Motorcycle</option>
                    <option value="Public" <?= ($transport == 'Public') ? 'selected' : '' ?>>Public Transport (Mixed)</option>
                    <option value="On foot" <?= ($transport == 'On foot') ? 'selected' : '' ?>>On foot</option>
                    <option value="Bike" <?= ($transport == 'Bike') ? 'selected' : '' ?>>Bike</option>
                </select>

                <button type="submit">Calculate</button>
            </form>
        </div>

        <!-- Map + Transit Schedule Side by Side -->
        <div class="map-transit-container">
            <div class="card map-card">
                <h2>Your Route on Maps</h2>
                <div id="map"></div>
                <?php if ($transport === 'Public'): ?>
                <div id="legend">
                    <h4>Transport Legend</h4>
                    <div><span style="background-color: #ffc107"></span> Bus</div>
                    <div><span style="background-color: #17a2b8"></span> Subway</div>
                    <div><span style="background-color: #8e44ad"></span> Train / Rail</div>
                    <div><span style="background-color: #00bcd4"></span> Light Rail</div>
                    <div><span style="background-color: #5e35b1"></span> Heavy Rail</div>
                    <div><span style="background-color: #6c757d"></span> On foot</div>
                </div>
                <?php endif; ?>
            </div>

            <div class="card schedule-card">
                <?php if (!empty($routeData['transitSegments'])): ?>
                <div class="schedule-container">
                    <h2>Transit Schedule</h2>
                    <ul>
                        <?php foreach ($routeData['transitSegments'] as $segment): ?>
                            <li>
                                <strong><?= htmlspecialchars($segment['vehicle']) ?> <?= htmlspecialchars($segment['line']) ?></strong><br>
                                From <em><?= htmlspecialchars($segment['from']) ?></em> at <strong><?= htmlspecialchars($segment['departureTime']) ?></strong><br>
                                To <em><?= htmlspecialchars($segment['to']) ?></em> at <strong><?= htmlspecialchars($segment['arrivalTime']) ?></strong><br>
                                Distance: <?= round($segment['distance'], 2) ?> km
                            </li>
                            <hr>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Weather -->
        <div class="card">
            <h2>Weather Forecast</h2>
            <?php if($weatherData): ?>
                <div class="weather-box">
                    <p class="temperature"><?php echo round($weatherData['main']['temp']); ?>°C</p>
                    <p><?php echo date('l, d F', $weatherData['dt']); ?></p>
                    <p><?php echo $weatherData['name']; ?></p>
                    <p><?php echo ucfirst($weatherData['weather'][0]['description']); ?></p>
                </div>
            <?php else: ?>
                <p>No weather data available</p>
            <?php endif; ?>
        </div>

        <!-- Carbon -->
        <div class="card">
            <h2>Your Carbon Footprint (CO₂)</h2>
            <?php if ($co2Result !== null): ?>
                <p class="co2-result"><?php echo round($co2Result, 2); ?> kg</p>
                <p><?php echo round($co2Result, 2); ?> kg CO₂ is the same as cutting down 4–5 mature trees.</p>
            <?php else: ?>
                <p>No CO₂ calculation available. Check distance API response.</p>
            <?php endif; ?>
        </div>

        <!-- Coming Soon -->
        <div class="card">
            <h2>Coming Soon</h2>
            <p>This space will be used for future features!</p>
        </div>

    </div>
</section>

<?php if (!empty($routeData['steps'])): ?>
<script>
const routeSteps = <?php echo json_encode($routeData['steps']); ?>;
const useSteps = true;
</script>
<?php elseif (!empty($routeData['polyline'])): ?>
<script>
const routePolyline = "<?php echo $routeData['polyline']; ?>";
const useSteps = false;
</script>
<?php endif; ?>

<script>
let map;

function initMap() {
    map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: 52.2298, lng: 21.0122 },
        zoom: 6,
    });

    const originInput = document.getElementById("origin");
    const destinationInput = document.getElementById("destination");

    const autocompleteOrigin = new google.maps.places.Autocomplete(originInput);
    const autocompleteDestination = new google.maps.places.Autocomplete(destinationInput);

    autocompleteOrigin.setComponentRestrictions({ country: "PL" });
    autocompleteDestination.setComponentRestrictions({ country: "PL" });

    autocompleteOrigin.setFields(["geometry", "formatted_address"]);
    autocompleteDestination.setFields(["geometry", "formatted_address"]);

    if (typeof useSteps !== 'undefined') {
        if (useSteps) {
            displaySteps(routeSteps);
        } else {
            displayPolyline(routePolyline);
        }
    }
}

function displayPolyline(encodedPolyline) {
    if (!encodedPolyline || encodedPolyline.length === 0) return;

    const decodedPath = google.maps.geometry.encoding.decodePath(encodedPolyline);

    const polyline = new google.maps.Polyline({
        path: decodedPath,
        geodesic: true,
        strokeColor: "#007bff",
        strokeOpacity: 1.0,
        strokeWeight: 5
    });

    polyline.setMap(map);

    const bounds = new google.maps.LatLngBounds();
    decodedPath.forEach(latLng => bounds.extend(latLng));
    map.fitBounds(bounds);
}

function displaySteps(steps) {
    const colors = {
        WALK: "#6c757d",
        BICYCLE: "#28a745",
        DRIVE: "#007bff",
        TRANSIT: "#ff5733",
        BUS: "#ffc107",
        SUBWAY: "#17a2b8",
        TRAIN: "#8e44ad",
        RAIL: "#8e44ad",
        LIGHT_RAIL: "#00bcd4",
        HEAVY_RAIL: "#5e35b1",
        DEFAULT: "#000000"
    };

    const bounds = new google.maps.LatLngBounds();

    steps.forEach(step => {
        const encoded = step.polyline?.encodedPolyline;
        if (!encoded) return;

        const path = google.maps.geometry.encoding.decodePath(encoded);
        let mode = step.travelMode;

        if (mode === "TRANSIT" && step.transitDetails) {
            const type = step.transitDetails.transitLine?.vehicle?.type;
            if (type && colors[type]) {
                mode = type;
            }
        }

        const color = colors[mode] || colors.DEFAULT;

        const polyline = new google.maps.Polyline({
            path: path,
            geodesic: true,
            strokeColor: color,
            strokeOpacity: 1.0,
            strokeWeight: 5
        });

        polyline.setMap(map);
        path.forEach(p => bounds.extend(p));
    });

    map.fitBounds(bounds);
}
</script>

<style>
.map-transit-container {
    display: flex;
    flex-direction: row; /* left to right */
    align-items: flex-start;
    gap: 20px;
    flex-wrap: nowrap; /* Prevent wrap to new line */
}

.map-card {
    flex: 2;
    min-width: 400px;
}

.schedule-card {
    flex: 1;
    max-width: 350px;
    min-width: 300px;
}

.map-transit-container .map-card {
    flex: 2;
    min-width: 320px;
}

.map-transit-container .schedule-card {
    flex: 1;
    min-width: 300px;
}

#map {
    width: 100%;
    height: 400px;
    border-radius: 8px;
}

.schedule-container {
    flex-grow: 1;
    height: 100%;
    overflow-y: auto;
}


.schedule-container ul {
    list-style: none;
    padding: 0;
}

.schedule-container li {
    margin-bottom: 10px;
}

#legend {
    position: absolute;
    top: 10px;
    right: 10px;
    background: white;
    border: 1px solid #ccc;
    padding: 10px 15px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    font-size: 14px;
    z-index: 1000;
}

#legend h4 {
    margin: 0 0 10px;
    font-size: 16px;
}

#legend div {
    margin-bottom: 6px;
    display: flex;
    align-items: center;
}

#legend span {
    display: inline-block;
    width: 18px;
    height: 18px;
    margin-right: 8px;
    border-radius: 3px;
}
</style>

<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap&libraries=places,geometry"></script>

<?php include 'includes/footer.php'; ?>
