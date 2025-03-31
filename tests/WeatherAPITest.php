<?php
namespace App\Tests;

use App\Classes\WeatherAPI;
use PHPUnit\Framework\TestCase;
use phpmock\phpunit\PHPMock;

class WeatherAPITest extends TestCase {
    use PHPMock;

    public function testGetWeatherSuccess() {
        $geoMock = $this->createMock(\App\Classes\GoogleMapsAPI::class);
        $geoMock->method('getCoordinates')->willReturn(['lat' => 40.7128, 'lng' => -74.0060]);

        $mock = $this->getFunctionMock('App\\Classes', 'file_get_contents');
        $mock->expects($this->once())->willReturn(json_encode([
            'cod' => 200,
            'weather' => [['main' => 'Clear']],
            'main' => ['temp' => 23]
        ]));

        $api = new WeatherAPI($geoMock, 'FAKE_KEY');
        $result = $api->getWeather('New York');

        $this->assertIsArray($result);
        $this->assertEquals('Clear', $result['weather'][0]['main']);
    }

    public function testGetWeatherInvalidCoordinates() {
        $geoMock = $this->createMock(\App\Classes\GoogleMapsAPI::class);
        $geoMock->method('getCoordinates')->willReturn(null);

        $api = new WeatherAPI($geoMock, 'FAKE_KEY');
        $result = $api->getWeather('Invalid Place');

        $this->assertNull($result);
    }

    public function testGetWeatherApiFailure() {
        $geoMock = $this->createMock(\App\Classes\GoogleMapsAPI::class);
        $geoMock->method('getCoordinates')->willReturn(['lat' => 0, 'lng' => 0]);

        $mock = $this->getFunctionMock('App\\Classes', 'file_get_contents');
        $mock->expects($this->once())->willReturn(false);

        $api = new WeatherAPI($geoMock, 'FAKE_KEY');
        $result = $api->getWeather('Nowhere');

        $this->assertNull($result);
    }

    public function testGetWeatherInvalidResponse() {
        $geoMock = $this->createMock(\App\Classes\GoogleMapsAPI::class);
        $geoMock->method('getCoordinates')->willReturn(['lat' => 0, 'lng' => 0]);

        $mock = $this->getFunctionMock('App\\Classes', 'file_get_contents');
        $mock->expects($this->once())->willReturn(json_encode(['cod' => 500]));

        $api = new WeatherAPI($geoMock, 'FAKE_KEY');
        $result = $api->getWeather('Nowhere');

        $this->assertNull($result);
    }
}
