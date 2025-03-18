<?php
class GoogleMapsAPI {
    private static $apiKey = GOOGLE_MAPS_API_KEY;

    public static function getCoordinates($address) {
        $apiKey = self::$apiKey;
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . $apiKey;
    
        $response = file_get_contents($url);
        $data = json_decode($response, true);
    
        if (!isset($data['results'][0]['geometry']['location'])) {
            return null;
        }
    
        return $data['results'][0]['geometry']['location'];
    }

    public static function getEcoRoute($origin, $destination, $transport = "Car") {
        if (isset($_POST['origin_lat']) && isset($_POST['origin_lng']) && isset($_POST['destination_lat']) && isset($_POST['destination_lng'])) {
            $origin = ['lat' => $_POST['origin_lat'], 'lng' => $_POST['origin_lng']];
            $destination = ['lat' => $_POST['destination_lat'], 'lng' => $_POST['destination_lng']];
        } else {
            $origin = self::getCoordinates($origin);
            $destination = self::getCoordinates($destination);
        }
        
        if (!$origin || !$destination) {
            return ["error" => "Invalid location coordinates."];
        }

        $travelModes = [
            "Car" => "DRIVE",
            "Motorcycle" => "TWO_WHEELER",
            "Bus" => "TRANSIT",
            "Train" => "TRANSIT",
            "Tram" => "TRANSIT",
            "Subway" => "TRANSIT",
            "Light Rail" => "TRANSIT",
            "Rail" => "TRANSIT",
            "On foot" => "WALK",
            "Bike" => "BICYCLE"
        ];

        $mode = $travelModes[$transport] ?? "DRIVE";

        $postData = [
            "origin" => ["location" => ["latLng" => ["latitude" => $origin['lat'], "longitude" => $origin['lng']]]],
            "destination" => ["location" => ["latLng" => ["latitude" => $destination['lat'], "longitude" => $destination['lng']]]],
            "travelMode" => $mode
        ];

        // Add routing preference for driving
        if ($mode == "DRIVE") {
            $postData["routingPreference"] = "TRAFFIC_AWARE_OPTIMAL";
        }

        // Specify transit preferences for each transit mode
        if ($mode == "TRANSIT") {
            $allowedModes = [];
            switch ($transport) {
                case "Bus":
                    $allowedModes = ["BUS"];
                    break;
                case "Train":
                    $allowedModes = ["TRAIN"];
                    break;
                case "Tram":
                    $allowedModes = ["LIGHT_RAIL"];
                    break;
                case "Subway":
                    $allowedModes = ["SUBWAY"];
                    break;
                case "Light Rail":
                    $allowedModes = ["LIGHT_RAIL"];
                    break;
                case "Rail":
                    $allowedModes = ["RAIL"];
                    break;
                default:
                    $allowedModes = ["BUS", "SUBWAY", "TRAIN", "LIGHT_RAIL", "RAIL"];
            }

            $postData["transitPreferences"] = [
                "allowedTravelModes" => $allowedModes
            ];
            $postData["departureTime"] = date('c'); // Required for transit
        }

        $headers = [
            "Content-Type: application/json",
            "X-Goog-Api-Key: " . self::$apiKey,
            "X-Goog-FieldMask: routes.distanceMeters,routes.duration,routes.polyline"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://routes.googleapis.com/directions/v2:computeRoutes");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['routes'][0])) {
            return ["error" => "No route found for selected transport mode."];
        }

        return [
            "distance" => $data['routes'][0]['distanceMeters'] / 1000, // Convert meters to km
            "duration" => $data['routes'][0]['duration'] ?? "Unknown",
            "polyline" => $data['routes'][0]['polyline']['encodedPolyline'] ?? "",
            "steps" => $data['routes'][0]['legs'][0]['steps'] ?? []
        ];
    }
}
?>
