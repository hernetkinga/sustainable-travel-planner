<?php
class WeatherAPI {
    public static function getWeather($location) {
        $apiKey = WEATHER_API_KEY;

        // Get coordinates from Google Maps API
        $coordinates = GoogleMapsAPI::getCoordinates($location);
        if (!$coordinates) {
            return null; // Fail gracefully if geocoding fails
        }

        $lat = $coordinates['lat'];
        $lng = $coordinates['lng'];

        // Use coordinates to get weather data
        $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lng}&units=metric&appid=" . $apiKey;

        $response = @file_get_contents($url); // Suppress warnings
        if ($response === FALSE) {
            return null;
        }

        $data = json_decode($response, true);

        // Check if the API returned an error
        if (!isset($data['cod']) || $data['cod'] != 200) {
            return null;
        }

        return $data;
    }
}
?>
