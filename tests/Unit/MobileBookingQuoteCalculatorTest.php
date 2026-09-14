<?php

namespace Tests\Unit;

use InvalidArgumentException;
use Modules\CustomerPortalApi\Services\Pricing\MobileBookingQuoteCalculator;
use Modules\CustomerPortalApi\Services\Pricing\MobileBookingQuoteSigner;
use PHPUnit\Framework\TestCase;

class MobileBookingQuoteCalculatorTest extends TestCase
{
    public function test_it_recalculates_local_distance_and_price_on_the_server(): void
    {
        $result = (new MobileBookingQuoteCalculator())->calculate([
            'service' => 'local',
            'distanceKm' => 9999,
            'pickup' => ['latitude' => -15.3665, 'longitude' => 28.3206],
            'destination' => ['latitude' => -15.4162, 'longitude' => 28.3074],
            'vehicleType' => 'small_van',
            'schedule' => 'later_today',
            'cargo' => ['totalWeight' => 2, 'fragile' => true],
        ], [
            'local' => [
                'base_fee' => 55,
                'per_km' => 6,
                'per_kg' => 2,
                'fragile_fee' => 10,
                'vehicle_fees' => ['small_van' => 28],
                'schedule_fees' => ['later_today' => 5],
                'minutes_per_km' => 4,
            ],
        ]);

        $this->assertGreaterThan(0, $result['distanceKm']);
        $this->assertLessThan(20, $result['distanceKm']);
        $this->assertSame('priced', $result['pricingStatus']);
        $this->assertGreaterThan(55, $result['total']);
    }

    public function test_it_fails_closed_when_a_service_base_rate_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MobileBookingQuoteCalculator())->calculate([
            'service' => 'intercity',
            'pickup' => [],
            'destination' => [],
            'cargo' => ['fragile' => false],
        ], ['intercity' => ['per_km' => 0, 'per_kg' => 0]]);
    }

    public function test_quote_signatures_are_stable_for_equivalent_payload_order(): void
    {
        $signer = new MobileBookingQuoteSigner();
        $app = new \Illuminate\Foundation\Application();
        $app['config'] = new \Illuminate\Config\Repository(['app' => ['key' => 'test-signing-key']]);
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);

        $this->assertSame(
            $signer->sign(['total' => 100, 'route' => ['to' => 'Lusaka', 'from' => 'Roma']]),
            $signer->sign(['route' => ['from' => 'Roma', 'to' => 'Lusaka'], 'total' => 100])
        );
    }
}
