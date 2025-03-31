<?php 

require_once __DIR__ . '/vendor/autoload.php'; // ← important if not already included
require_once __DIR__ . '/config/config.php'; // <-- must come before you use the API keys
include __DIR__ . '/includes/header.php'; // 👈 TO JEST WAŻNE
use App\Classes\GoogleMapsAPI;
use App\Classes\CarbonCalculator;
use App\Classes\WeatherAPI;

$co2Result = null;
$distance = null;
$weatherData = null;
$origin = '';
$destination = '';
$transport = isset($_POST['transport']) ? $_POST['transport'] : 'Car';
$routeData = []; 
$maps = new GoogleMapsAPI(GOOGLE_MAPS_API_KEY);

function normalizeMode($mode) {
    $map = [
        'DRIVE' => 'Car',
        'CAR' => 'Car',
        'MOTORCYCLE' => 'Motorcycle',
        'TRANSIT' => 'Public',
        'BUS' => 'Bus',
        'TRAIN' => 'Train',
        'TRAM' => 'Tram',
        'SUBWAY' => 'Train',
        'RAIL' => 'Train',
        'LIGHT_RAIL' => 'Train',
        'HEAVY_RAIL' => 'Train',
        'WALK' => 'On foot',
        'BICYCLE' => 'Bike',
        'BIKE' => 'Bike'
    ];
    return $map[strtoupper($mode)] ?? 'Car'; // fallback to 'Car' just in case
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $origin = trim($_POST['origin']);
    $destination = trim($_POST['destination']);

    if (!empty($origin) && !empty($destination)) {
      $maps = new GoogleMapsAPI();
      $routeData = $maps->getEcoRoute($origin, $destination, $transport);
      
        if (!empty($routeData['modeDistances'])) {
            $co2Result = 0;
            $distance = array_sum($routeData['modeDistances']);
        
            if ($transport === 'On foot' || $transport === 'Bike') {
                $co2Result = 0;
            } else {
                foreach ($routeData['modeDistances'] as $mode => $dist) {
                    $normalizedMode = normalizeMode($mode);
        
                    // If it's a single-mode trip (like WALK only), trust the user's selection
                    if (count($routeData['modeDistances']) === 1) {
                        $normalizedMode = normalizeMode($transport);
                    }
        
                    $co2Result += CarbonCalculator::calculate($dist, $normalizedMode);
                }
            }
        }
        

        $weather = new WeatherAPI($maps); // inject the same instance of GoogleMapsAPI
        $weatherData = $weather->getWeather($destination);
        
    }
}
?>

<section class="calculator">
<div class="calculator-wrapper">
  <div class="card route-card">
    <!-- Route Selection -->
    <h2>Choose Your Route</h2>
    <form method="post" action="calculator.php">
      <input type="text" id="origin" name="origin" value="<?= htmlspecialchars($origin); ?>" placeholder="Enter your starting point" required>
      <input type="text" id="destination" name="destination" value="<?= htmlspecialchars($destination); ?>" placeholder="Enter your destination" required>
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

  <div class="card map-card">
    <!-- Google Map -->
    <h2>Your Route on Maps</h2>
    <div id="map"></div>
  </div>

  <div class="card schedule-card">
    <!-- Transit Schedule NOW comes before weather -->
    <h2>Transit Schedule</h2>
    <?php if (!empty($routeData['transitSegments'])): ?>
    <div class="schedule-container">
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
    <?php else: ?>
    <p>No transit available.</p>
    <?php endif; ?>
  </div>

  <div class="card weather-card">
  <!-- Weather box moved down one spot -->
  <h2>Weather Forecast</h2>
  <?php if($weatherData): ?>
  <?php 
    $iconCode = $weatherData['weather'][0]['icon'];
    $iconUrl = "https://openweathermap.org/img/wn/{$iconCode}@2x.png";
  ?>
  <div class="weather-box">
    <img src="<?= $iconUrl ?>" alt="Weather Icon">
    <p class="temperature"><?= round($weatherData['main']['temp']); ?>°C</p>
    <p><?= date('l, d F', $weatherData['dt']); ?></p>
    <p><?= $weatherData['name']; ?></p>
    <p><?= ucfirst($weatherData['weather'][0]['description']); ?></p>
  </div>
  <?php else: ?>
  <p>No weather data available</p>
  <?php endif; ?>
</div>


<div class="card carbon-card">
  <h2>Your Carbon Footprint (CO₂)</h2>
  <?php if ($co2Result !== null): ?>
    <?php
      $co2PerKWh = 0.475;
      $laptopWattage = 50;
      $laptopKWh = $laptopWattage / 1000;
      $laptopHours = round(($co2Result / $co2PerKWh) / $laptopKWh);
    ?>
    <div class="carbon-box">
      <p class="co2-result"><?= round($co2Result, 2); ?> kg</p>
      <p class="co2-sub">CO₂ emitted for this journey</p>
      <ul class="carbon-list">
        <li>💻 <span>Same as using a laptop for <strong><?= $laptopHours ?> hours</strong></span></li>
      </ul>
    </div>
  <?php else: ?>
    <p>No CO₂ calculation available.</p>
  <?php endif; ?>
</div>


    <?php
    $tips = [
        "🧃 Bring a reusable water bottle or coffee cup — you'll reduce plastic waste *and* the emissions from production.",
        "🚶 Walk or bike for trips under 2 km — it's often faster than driving in cities and has zero emissions.",
        "👜 Keep a foldable bag in your backpack or coat — skipping a plastic bag saves ~0.1 kg CO₂ each time.",
        "🚌 Take the bus for your work commute once a week — even just one day cuts emissions by up to 20%.",
        "🌱 Eat one plant-based meal per day — it can cut your food-related carbon footprint by 25–30%.",
        "🔌 Unplug phone/laptop chargers when not in use — they still draw 'phantom power' and waste energy.",
        "📦 Consolidate online shopping deliveries — fewer shipments means fewer vans and less CO₂.",
        "👕 Wash clothes with cold water and air dry — this saves energy and extends clothing life.",
        "💡 Switch to LED bulbs — they use up to 85% less energy and last longer.",
        "🍽️ Plan your meals to avoid food waste — the average household throws away 30% of food!",
        "📧 Clean up old emails & cloud files — digital storage requires energy 24/7.",
    ];
    

    // Select one random tip
    $selectedTips = array_rand($tips, 3); // Get 3 random keys

    ?>

    <div class="card">
    <h2>Carbon-Saving Tips </h2>
    <ul class="tips-list">
        <?php foreach ($selectedTips as $key): ?>
        <li><?= htmlspecialchars($tips[$key]); ?></li>
        <?php endforeach; ?>
    </ul>
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

    const usedModes = new Set();
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

        usedModes.add(mode);
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
    updateLegend(Array.from(usedModes));
}
function updateLegend(usedModes) {
    const colorMap = {
        WALK: "#6c757d",
        BICYCLE: "#28a745",
        DRIVE: "#007bff",
        TRANSIT: "#ff5733",
        BUS: "#ffc107",
        SUBWAY: "#17a2b8",
        TRAIN: "#8e44ad",
        RAIL: "#8e44ad",
        LIGHT_RAIL: "#00bcd4",
        HEAVY_RAIL: "#5e35b1"
    };

    const legend = document.createElement('div');
    legend.id = "dynamic-legend";

    usedModes.forEach(mode => {
        const color = colorMap[mode] || "#000000";
        const row = document.createElement('div');
        row.className = "legend-row";

        const swatch = document.createElement('span');
        swatch.className = "legend-color";
        swatch.style.backgroundColor = color;

        const label = document.createElement('span');
        label.textContent = mode.replace('_', ' ');

        row.appendChild(swatch);
        row.appendChild(label);
        legend.appendChild(row);
    });

    map.controls[google.maps.ControlPosition.RIGHT_TOP].push(legend);
}

</script>

<style>
.weather-card {
  background: linear-gradient(135deg, #e0f7fa, #ffffff);
}

.weather-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 500;
  margin-top: auto;
  padding: 10px;
  text-align: center;
  color: #333;
}

.weather-box img {
  width: 80px;
  height: 80px;
  margin-bottom: 10px;
}

.tips-list {
  list-style: none;
  padding-left: 0;
  margin-top: 12px;
  font-size: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tips-list li {
  background: #f6fdf7;
  border: 1px solid #e1efe2;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 15px;
  line-height: 1.5;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}

.tips-list li::before {
  font-size: 16px;
  flex-shrink: 0;
  margin-top: 1px;
}


.daily-tip {
  background: #e9fce9;
  border-left: 5px solid #28a745;
  padding: 16px;
  border-radius: 10px;
  font-size: 15px;
  color: #2e5939;
  font-weight: 500;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
  position: relative;
  margin-top: 12px;
  line-height: 1.5;
}

.daily-tip::before {
  content: "🌱";
  font-size: 20px;
  position: absolute;
  top: 16px;
  left: 12px;
}

.card h2 {
  font-size: 16px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}



.weather-box .temperature {
  font-size: 32px;
  font-weight: bold;
  margin: 5px 0;
  color: #007bff;
}

.weather-box p {
  margin: 3px 0;
  font-size: 14px;
}

#dynamic-legend {
  background: white;
  border: 1px solid #ccc;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
  margin: 10px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.legend-row {
  display: flex;
  align-items: center;
  margin-bottom: 6px;
}

.legend-color {
  display: inline-block;
  width: 14px;
  height: 14px;
  margin-right: 8px;
  border-radius: 3px;
}

.calculator-wrapper {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    max-width: 1440px;
    margin: 30px auto;
    padding: 20px;
    width: 95%;
}



.card {
  background: white;
  padding: 16px; /* reduced from 20px */
  border-radius: 10px;
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  min-height: 380px; /* reduced from 460px */
  height: 100%;
  box-sizing: border-box;
  font-size: 15px;
}

.card h2 {
  font-size: 18px;
  margin-bottom: 10px;
}

.route-card form {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
  flex-grow: 1;
  justify-content: center;
}

button {
  padding: 10px;
  font-size: 14px;
}

input,
select {
  font-size: 14px;
  padding: 10px;
}
.calculator-wrapper {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    max-width: 1440px;
    margin: 30px auto;
    padding: 20px;
    width: 95%;
}

.card {
  background: white;
  padding: 14px; /* reduced */
  border-radius: 10px;
  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.08);
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  min-height: 320px; /* tighter height */
  font-size: 14px; /* slightly smaller font */
}

.card h2 {
  font-size: 16px;
  margin-bottom: 8px;
}

input, select, button {
  font-size: 14px;
  padding: 8px;
}

button {
  padding: 10px;
}

.co2-result {
  font-size: 32px;
  margin: 16px 0 10px;
}

.weather-box p {
  margin: 4px 0;
  font-size: 14px;
}


.route-card form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 20px;
  flex-grow: 1;
  justify-content: center;
}

#map {
  width: 100%;
  flex-grow: 1;
  height: 100%;
  border-radius: 8px;
  margin-top: 10px;
}

.schedule-container {
  flex-grow: 1;
  overflow-y: auto;
  padding: 10px;
  font-size: 14px;
}

.schedule-container ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.schedule-container li {
  margin-bottom: 10px;
}

.weather-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  font-size: 18px;
  font-weight: bold;
  margin-top: auto;
}

.co2-result {
  font-size: 40px;
  font-weight: bold;
  color: #333;
  margin: 20px 0 10px;
}


/* RESPONSIVE */
@media (max-width: 1000px) {
  .calculator-wrapper {
    grid-template-columns: 1fr;
  }

  .card {
    max-width: 90%;
    margin: auto;
  }
}
@media (max-width: 1300px) {
  .calculator-wrapper {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .calculator-wrapper {
    grid-template-columns: 1fr;
  }

  .card {
    max-width: 90%;
    margin: auto;
  }

  .route-card form input,
  .route-card form select,
  .route-card form button {
    width: 100%;
  }

  .card h2 {
    font-size: 14px;
  }

  .co2-result {
    font-size: 28px;
  }

  .tips-list li {
    font-size: 13px;
  }

  #dynamic-legend {
    position: static !important;
    margin-top: 10px;
  }
}

</style>

<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap&libraries=places,geometry"></script>

<?php include 'includes/footer.php'; ?>
