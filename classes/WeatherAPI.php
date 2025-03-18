<?php
class WeatherAPI {
    public static function getWeather($location) {
        $apiKey = WEATHER_API_KEY;
        
        $url = "https://api.openweathermap.org/data/2.5/weather?q=" 
             . urlencode($location) . "&units=metric&appid=" . $apiKey;
        
        $response = file_get_contents($url);
        if($response === FALSE) {
            return null;
        }
        $data = json_decode($response, true);

        
        if(isset($data['cod']) && $data['cod'] != 200) {
            return null;
        }
        return $data;
    }
}
?>
