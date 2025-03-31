<?php
use PHPUnit\Framework\TestCase;
use App\Classes\CarbonCalculator;

require_once 'classes/CarbonCalculator.php';

class CarbonCalculatorTest extends TestCase
{
    public function testCalculateWithValidTransportTypes()
    {
        $this->assertEquals(2.0, CarbonCalculator::calculate(10, 'Car'));
        $this->assertEquals(1.0, CarbonCalculator::calculate(10, 'Motorcycle'));
        $this->assertEquals(0.5, CarbonCalculator::calculate(10, 'Bus'));
        $this->assertEquals(0.3, CarbonCalculator::calculate(10, 'Train'));
        $this->assertEquals(0.2, CarbonCalculator::calculate(10, 'Tram'));
        $this->assertEquals(0.0, CarbonCalculator::calculate(10, 'On foot'));
        $this->assertEquals(0.0, CarbonCalculator::calculate(10, 'Bike'));
    }

    public function testCalculateWithInvalidTransportType()
    {
        $this->assertEquals(0.0, CarbonCalculator::calculate(10, 'Spaceship'));
    }

    public function testCalculateWithZeroDistance()
    {
        $this->assertEquals(0.0, CarbonCalculator::calculate(0, 'Car'));
        $this->assertEquals(0.0, CarbonCalculator::calculate(0, 'Bike'));
        $this->assertEquals(0.0, CarbonCalculator::calculate(0, 'Bus'));
    }

    public function testCalculateWithNegativeDistance()
    {
        // Not explicitly handled in the class, but test to document current behavior
        $this->assertEquals(-2.0, CarbonCalculator::calculate(-10, 'Car'));
    }
}
