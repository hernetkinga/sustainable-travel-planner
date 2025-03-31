<?php
namespace App\Tests;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/GoogleMapsAPI.php';

use App\Classes\GoogleMapsAPI;
use PHPUnit\Framework\TestCase;
use phpmock\phpunit\PHPMock;

class GoogleMapsAPITest extends TestCase {
    use PHPMock;

    private $api;

    protected function setUp(): void {
        $this->api = new GoogleMapsAPI('FAKE_KEY');

        // Clear the private cache
        $reflection = new \ReflectionClass($this->api);
        $prop = $reflection->getProperty('storedCoordinates');
        $prop->setAccessible(true);
        $prop->setValue($this->api, []);
    }

    public function testGetCoordinatesFromCache() {
        $reflection = new \ReflectionClass($this->api);
        $prop = $reflection->getProperty('storedCoordinates');
        $prop->setAccessible(true);
        $prop->setValue($this->api, ['Test Address' => ['lat' => 1.0, 'lng' => 2.0]]);

        $coords = $this->api->getCoordinates('Test Address');
        $this->assertEquals(['lat' => 1.0, 'lng' => 2.0], $coords);
    }

    public function testGetCoordinatesFromApiSuccess() {
        $mock = $this->getFunctionMock('App\\Classes', 'file_get_contents');
        $mock->expects($this->once())->willReturn(json_encode([
            'results' => [['geometry' => ['location' => ['lat' => 3.0, 'lng' => 4.0]]]]
        ]));

        $coords = $this->api->getCoordinates('Some Address');
        $this->assertEquals(['lat' => 3.0, 'lng' => 4.0], $coords);
    }

    public function testGetCoordinatesFromApiFailure() {
        $mock = $this->getFunctionMock('App\\Classes', 'file_get_contents');
        $mock->expects($this->once())->willReturn(json_encode(['results' => []]));

        $coords = $this->api->getCoordinates('Invalid Address');
        $this->assertNull($coords);
    }

    public function testGetEcoRouteInvalidCoordinates() {
        $mock = $this->getFunctionMock('App\\Classes', 'file_get_contents');
        $mock->expects($this->any())->willReturn(json_encode(['results' => []]));

        $result = $this->api->getEcoRoute('Nowhere', 'Nowhere');
        $this->assertEquals('Invalid location coordinates.', $result['error']);
    }

    public function testGetEcoRouteDriveSuccess() {
        $this->mockCoordinates();
        $this->mockCurlExec(json_encode([
            'routes' => [[
                'distanceMeters' => 10000,
                'duration' => '3600s',
                'polyline' => ['encodedPolyline' => 'abc123'],
                'legs' => [[
                    'steps' => [[
                        'distanceMeters' => 10000,
                        'travelMode' => 'DRIVE'
                    ]]
                ]]
            ]]
        ]));

        $result = $this->api->getEcoRoute('Start', 'End', 'Car');
        $this->assertArrayHasKey('totalDistance', $result);
        $this->assertEquals(10.0, $result['totalDistance']);
        $this->assertEquals(['Car' => 10.0], $result['modeDistances']);
    }

    public function testGetEcoRouteWithTransitStep() {
        $this->mockCoordinates();
        $this->mockCurlExec(json_encode([
            'routes' => [[
                'distanceMeters' => 5000,
                'duration' => '1800s',
                'polyline' => ['encodedPolyline' => 'def456'],
                'legs' => [[
                    'steps' => [[
                        'distanceMeters' => 5000,
                        'travelMode' => 'TRANSIT',
                        'transitDetails' => [
                            'transitLine' => [
                                'vehicle' => ['type' => 'BUS'],
                                'nameShort' => 'Line 42',
                                'agencies' => [['name' => 'City Transit']]
                            ],
                            'localizedValues' => [
                                'departureTime' => ['time' => ['text' => '08:00 AM']],
                                'arrivalTime' => ['time' => ['text' => '08:30 AM']]
                            ],
                            'stopDetails' => [
                                'departureStop' => ['name' => 'Stop A'],
                                'arrivalStop' => ['name' => 'Stop B']
                            ]
                        ]
                    ]]
                ]]
            ]]
        ]));

        $result = $this->api->getEcoRoute('Stop A', 'Stop B', 'Bus');
        $this->assertArrayHasKey('modeDistances', $result);
        $this->assertEquals('Bus', array_keys($result['modeDistances'])[0]);
        $this->assertCount(1, $result['transitSegments']);
    }

    public function testGetEcoRouteNoRouteFound() {
        $this->mockCoordinates();
        $this->mockCurlExec(json_encode([
            'routes' => []
        ]));

        $result = $this->api->getEcoRoute('Start', 'End', 'Bike');
        $this->assertEquals('No route found.', $result['error']);
    }

    private function mockCoordinates() {
        $mock = $this->getFunctionMock('App\\Classes', 'file_get_contents');
        $mock->expects($this->any())->willReturn(json_encode([
            'results' => [['geometry' => ['location' => ['lat' => 10, 'lng' => 20]]]]
        ]));
    }

    private function mockCurlExec($return) {
        $this->getFunctionMock('App\\Classes', 'curl_exec')->expects($this->once())->willReturn($return);
        $this->getFunctionMock('App\\Classes', 'curl_init')->expects($this->any())->willReturn('curl');
        $this->getFunctionMock('App\\Classes', 'curl_setopt')->expects($this->any())->willReturn(true);
        $this->getFunctionMock('App\\Classes', 'curl_close')->expects($this->any())->willReturn(true);
    }
}
