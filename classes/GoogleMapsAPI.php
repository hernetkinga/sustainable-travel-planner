<?php
namespace App\Classes;

class GoogleMapsAPI {
    private $apiKey;
    private $storedCoordinates = [];

    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?? GOOGLE_MAPS_API_KEY;
    }

    public function getCoordinates($address) {
        if (isset($this->storedCoordinates[$address])) {
            return $this->storedCoordinates[$address];
        }

        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . $this->apiKey;
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (!isset($data['results'][0]['geometry']['location'])) {
            return null;
        }

        $this->storedCoordinates[$address] = $data['results'][0]['geometry']['location'];
        return $data['results'][0]['geometry']['location'];
    }

    public function getEcoRoute($origin, $destination, $transport = "Car") {
        $this->storedCoordinates = [];

        $originCoords = $this->getCoordinates($origin);
        $destinationCoords = $this->getCoordinates($destination);

        if (!$originCoords || !$destinationCoords) {
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
            "Bike" => "BICYCLE",
            "Public" => "TRANSIT"
        ];

        $mode = $travelModes[$transport] ?? "DRIVE";

        $postData = [
            "origin" => ["location" => ["latLng" => ["latitude" => $originCoords['lat'], "longitude" => $originCoords['lng']]]],
            "destination" => ["location" => ["latLng" => ["latitude" => $destinationCoords['lat'], "longitude" => $destinationCoords['lng']]]],
            "travelMode" => $mode,
            "computeAlternativeRoutes" => false
        ];

        if ($mode == "DRIVE") {
            $postData["routingPreference"] = "TRAFFIC_AWARE_OPTIMAL";
        }

        if ($mode == "TRANSIT") {
            $postData["departureTime"] = date('c');
        }

        $headers = [
            "Content-Type: application/json",
            "X-Goog-Api-Key: " . $this->apiKey,
            "X-Goog-FieldMask: routes.distanceMeters,routes.duration,routes.polyline,routes.legs.steps"
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
                "error" => "No route found.",
                "polyline" => "",
            ];
        }

        $route = $data['routes'][0];
        $steps = $route['legs'][0]['steps'] ?? [];
        $modeDistances = [];
        $transitSegments = [];

        foreach ($steps as $step) {
            $stepDistanceKm = ($step['distanceMeters'] ?? 0) / 1000;
            $mode = $step['travelMode'] ?? 'UNKNOWN';

            if ($mode === 'TRANSIT' && isset($step['transitDetails']['transitLine']['vehicle']['type'])) {
                $vehicleType = $step['transitDetails']['transitLine']['vehicle']['type'];
                switch ($vehicleType) {
                    case 'BUS': $mode = 'Bus'; break;
                    case 'SUBWAY': $mode = 'Subway'; break;
                    case 'TRAIN': $mode = 'Train'; break;
                    case 'LIGHT_RAIL': $mode = 'Light Rail'; break;
                    case 'RAIL': $mode = 'Rail'; break;
                    case 'TRAM': $mode = 'Tram'; break;
                    default: $mode = 'Transit'; break;
                }

                $transit = $step['transitDetails'];
                $line = $transit['transitLine'];

                $transitSegments[] = [
                    'vehicle' => $line['vehicle']['type'] ?? 'TRANSIT',
                    'line' => $line['nameShort'] ?? $line['name'] ?? 'N/A',
                    'agency' => $line['agencies'][0]['name'] ?? '',
                    'departureTime' => $transit['localizedValues']['departureTime']['time']['text'] ?? '',
                    'arrivalTime' => $transit['localizedValues']['arrivalTime']['time']['text'] ?? '',
                    'from' => $transit['stopDetails']['departureStop']['name'] ?? '',
                    'to' => $transit['stopDetails']['arrivalStop']['name'] ?? '',
                    'distance' => $stepDistanceKm
                ];
            } elseif ($mode === 'WALK') {
                $mode = 'On foot';
            } elseif ($mode === 'BICYCLE') {
                $mode = 'Bike';
            } elseif ($mode === 'DRIVE') {
                $mode = 'Car';
            }

            if (!isset($modeDistances[$mode])) {
                $modeDistances[$mode] = 0;
            }

            $modeDistances[$mode] += $stepDistanceKm;
        }

        return [
            "totalDistance" => $route['distanceMeters'] / 1000,
            "duration" => $route['duration'] ?? "Unknown",
            "polyline" => $route['polyline']['encodedPolyline'] ?? '',
            "steps" => $steps,
            "modeDistances" => $modeDistances,
            "transitSegments" => $transitSegments
        ];
    }
}
