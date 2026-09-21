<?php

namespace Tests\Feature;

use Illuminate\Validation\ValidationException;
use Modules\CustomerPortalApi\Services\BookingServiceArea;
use Tests\TestCase;

class BookingServiceAreaTest extends TestCase
{
    public function test_domestic_boundaries_and_unrestricted_services()
    {
        $area = new BookingServiceArea();
        $this->assertTrue($area->contains('local', -15.3875, 28.3228));
        $this->assertTrue($area->contains('intercity', -15.3875, 28.3228));
        $this->assertTrue($area->contains('intercity', -12.8024, 28.2132));
        $this->assertFalse($area->contains('local', -12.8024, 28.2132));
        $this->assertFalse($area->contains('intercity', -17.8252, 31.0335));
        $this->assertFalse($area->contains('local', null, null));
        $this->assertFalse($area->contains('intercity', 100, 28));
        $this->assertTrue($area->contains('import', 25.2, 55.27));
        $this->assertTrue($area->contains('custom', null, null));
    }

    public function test_both_endpoints_are_validated_without_erasing_valid_endpoint()
    {
        $area = new BookingServiceArea();
        $lusaka = ['latitude' => -15.3875, 'longitude' => 28.3228];
        $kitwe = ['latitude' => -12.8024, 'longitude' => 28.2132];
        $area->validate('intercity', $lusaka, $kitwe);
        foreach (['pickup', 'destination'] as $invalid) {
            try {
                $area->validate('local', $invalid === 'pickup' ? $kitwe : $lusaka, $invalid === 'destination' ? $kitwe : $lusaka);
                $this->fail('Out-of-zone location accepted');
            } catch (ValidationException $exception) {
                $this->assertSame([$invalid], array_keys($exception->errors()));
            }
        }
    }
}
