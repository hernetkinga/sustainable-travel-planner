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
$transport = isset($_POST['transport']) ? $_POST['transport'] : 'Car'; // Keep transport selection
$routeData = []; 
$polyline = ''; // Default empty polyline

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $origin = trim($_POST['origin']);
    $destination = trim($_POST['destination']);

    if (!empty($origin) && !empty($destination)) {
        $routeData = GoogleMapsAPI::getEcoRoute($origin, $destination, $transport); // Pass transport
        if (!empty($routeData['distance'])) {
            $distance = $routeData['distance'];
            $co2Result = CarbonCalculator::calculate($distance, $transport);
            $polyline = $routeData['polyline'];  // Store polyline
        }

        $weatherData = WeatherAPI::getWeather($destination);
    }
}
?>

<section class="calculator">
    <div class="calculator-grid">
        <div class="card">
            <h2>Choose Your Route</h2>
            <form method="post" action="calculator.php">
                <input type="text" id="origin" name="origin" value="<?php echo htmlspecialchars($origin); ?>" placeholder="Enter your starting point" required>
                <input type="text" id="destination" name="destination" value="<?php echo htmlspecialchars($destination); ?>" placeholder="Enter your destination" required>

                <select id="transport" name="transport" required onchange="updateRoute()">
                    <option value="Car" <?= ($transport == 'Car') ? 'selected' : '' ?>>Car</option>
                    <option value="Motorcycle" <?= ($transport == 'Motorcycle') ? 'selected' : '' ?>>Motorcycle</option>
                    <option value="Bus" <?= ($transport == 'Bus') ? 'selected' : '' ?>>Bus</option>
                    <option value="Train" <?= ($transport == 'Train') ? 'selected' : '' ?>>Train</option>
                    <option value="Tram" <?= ($transport == 'Tram') ? 'selected' : '' ?>>Tram</option>
                    <option value="On foot" <?= ($transport == 'On foot') ? 'selected' : '' ?>>On foot</option>
                    <option value="Bike" <?= ($transport == 'Bike') ? 'selected' : '' ?>>Bike</option>
                </select>

                <button type="submit">Calculate</button>
            </form>
        </div>

        <div class="card">
            <h2>Your Route on Maps</h2>
            <div id="map"></div>
        </div>

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

        <div class="card">
            <h2>Your Carbon Footprint (CO₂)</h2>
            <?php if ($co2Result !== null): ?>
                <p class="co2-result"><?php echo round($co2Result, 2); ?> kg</p>
                <p><?php echo round($co2Result, 2); ?> kg CO₂ is the same as cutting down 4–5 mature trees.</p>
            <?php else: ?>
                <p>No CO₂ calculation available. Check distance API response.</p>
                <pre><?php print_r($routeData); ?></pre> <?php endif; ?>
        </div>
    </div>
</section>

<script>
let map;
let directionsService;
let directionsRenderer;
let autocompleteOrigin;
let autocompleteDestination;

function initMap() {
    console.log("Initializing map...");
    map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: 52.2298, lng: 21.0122 }, // Default Warsaw
        zoom: 6,
    });

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({ map: map });

    // Enable Google Places Autocomplete for Origin and Destination
    autocompleteOrigin = new google.maps.places.Autocomplete(document.getElementById("origin"));
    autocompleteDestination = new google.maps.places.Autocomplete(document.getElementById("destination"));

    // Restrict search to a country (Optional - You can remove this line)
    autocompleteOrigin.setComponentRestrictions({ country: "PL" });
    autocompleteDestination.setComponentRestrictions({ country: "PL" });

    // Ensure we use precise place details
    autocompleteOrigin.setFields(["geometry", "formatted_address"]);
    autocompleteDestination.setFields(["geometry", "formatted_address"]);

    autocompleteOrigin.addListener("place_changed", function () {
        let place = autocompleteOrigin.getPlace();
        if (!place.geometry) {
            console.warn("No details available for input: '" + place.name + "'");
        }
    });

    autocompleteDestination.addListener("place_changed", function () {
        let place = autocompleteDestination.getPlace();
        if (!place.geometry) {
            console.warn("No details available for input: '" + place.name + "'");
        }
    });

    <?php if (!empty($polyline)): ?>
        displayPolyline('<?php echo $polyline; ?>');
    <?php endif; ?>
}

function displayPolyline(encodedPolyline) {
    if (!encodedPolyline || encodedPolyline.length === 0) {
        console.warn("No polyline available for this route.");
        return;
    }

    const decodedPath = google.maps.geometry.encoding.decodePath(encodedPolyline);
    const polyline = new google.maps.Polyline({
        path: decodedPath,
        geodesic: true,
        strokeColor: '#FF0000',
        strokeOpacity: 1.0,
        strokeWeight: 5
    });
    polyline.setMap(map);

    const bounds = new google.maps.LatLngBounds();
    decodedPath.forEach(latLng => bounds.extend(latLng));
    map.fitBounds(bounds);
}

// Display warning for certain modes
function showWarning(transport) {
    let warningMessage = "";

    if (transport === "Bike" || transport === "On foot" || transport === "Motorcycle") {
        warningMessage = "⚠️ Walking, bicycling, and two-wheel routes are in beta and might sometimes be missing clear sidewalks, pedestrian paths, or bicycling paths.";
    }

    document.getElementById("transport-warning").innerHTML = warningMessage;
}

</script>

<!-- Ensure Google Places API is enabled in the Maps API key -->
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap&libraries=places,geometry"></script>


<?php include 'includes/footer.php'; ?>