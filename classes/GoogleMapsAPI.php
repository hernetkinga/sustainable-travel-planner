<?php
class GoogleMapsAPI {
    private static $apiKey = GOOGLE_MAPS_API_KEY;
    private static $storedCoordinates = []; // Make this a static property

    public static function getCoordinates($address) {
        // Check if coordinates for this address are already cached
        if (isset(self::$storedCoordinates[$address])) {
            return self::$storedCoordinates[$address];
        }

        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . self::$apiKey;
    
        $response = file_get_contents($url);
        $data = json_decode($response, true);
    
        if (!isset($data['results'][0]['geometry']['location'])) {
            return null;
        }

        // Cache the coordinates
        self::$storedCoordinates[$address] = $data['results'][0]['geometry']['location'];
    
        return $data['results'][0]['geometry']['location'];
    }

    public static function getEcoRoute($origin, $destination, $transport = "Car") {
        // Reset stored coordinates for each route calculation
        self::$storedCoordinates = [];

        // Always get fresh coordinates
        $originCoords = self::getCoordinates($origin);
        $destinationCoords = self::getCoordinates($destination);
        
        if (!$originCoords || !$destinationCoords) {
            return ["error" => "Invalid location coordinates."];
        } else {
            static $storedCoordinates = [];

            $originKey = is_array($origin) ? json_encode($origin) : $origin;
            $destinationKey = is_array($destination) ? json_encode($destination) : $destination;
            
            if (!isset($storedCoordinates[$originKey])) {
                $storedCoordinates[$originKey] = self::getCoordinates($origin);
            }
            if (!isset($storedCoordinates[$destinationKey])) {
                $storedCoordinates[$destinationKey] = self::getCoordinates($destination);
            }
            
            $origin = $storedCoordinates[$originKey];
            $destination = $storedCoordinates[$destinationKey];
            
            
        }
        
        if (!$origin || !$destination) {
            return ["error" => "Invalid location coordinates."];
        }

        $travelModes = [
            "Car" => "DRIVE",
            "Motorcycle" => "DRIVE",
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
            "travelMode" => $mode,
            "computeAlternativeRoutes" => true // Pozwala na wybór lepszej trasy
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
            return [
                "error" => "No route found for selected transport mode.",
                "polyline" => "", // Ensure polyline is empty to avoid JavaScript errors
            ];
        }
        
        $polyline = isset($data['routes'][0]['polyline']['encodedPolyline']) ? 
            $data['routes'][0]['polyline']['encodedPolyline'] : '';
        
        return [
            "distance" => $data['routes'][0]['distanceMeters'] / 1000, // Convert meters to km
            "duration" => $data['routes'][0]['duration'] ?? "Unknown",
            "polyline" => $polyline, // Store polyline
            "steps" => $data['routes'][0]['legs'][0]['steps'] ?? []
        ];
        
    }
}
?>
