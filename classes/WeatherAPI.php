<?php
namespace App\Classes;

class WeatherAPI {
    private $apiKey;
    private $geoService;
    

    public function __construct($geoService = null, $apiKey = null) {
        $this->geoService = $geoService ?? new GoogleMapsAPI();
        $this->apiKey = $apiKey ?? WEATHER_API_KEY;
    }

    public function getWeather($location) {
        $coordinates = $this->geoService->getCoordinates($location);
        if (!$coordinates) {
            return null;
        }

        $lat = $coordinates['lat'];
        $lng = $coordinates['lng'];

        $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lng}&units=metric&appid=" . $this->apiKey;
        $response = @file_get_contents($url);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['cod']) || $data['cod'] != 200) {
            return null;
        }

        return $data;
    }
}
