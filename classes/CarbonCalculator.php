<?php
namespace App\Classes;
require_once __DIR__ . '/../vendor/autoload.php';

class CarbonCalculator {

    // Współczynniki emisji (kg CO2/km) dla poszczególnych środków transportu

    private static $emissionFactors = [
        'Car' => 0.2,
        'Motorcycle' => 0.1,
        'Bus' => 0.05,
        'Train' => 0.03,
        'Tram' => 0.02,
        'On foot' => 0,
        'Bike' => 0
    ];

    public static function calculate($distance, $transportType) {
        if (!isset(self::$emissionFactors[$transportType])) {
            return 0;
        }
        return $distance * self::$emissionFactors[$transportType];
    }
}
?>
