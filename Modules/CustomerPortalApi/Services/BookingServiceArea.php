<?php

namespace Modules\CustomerPortalApi\Services;

use Illuminate\Validation\ValidationException;
use Location\Coordinate;
use Location\Polygon;

class BookingServiceArea
{
    public function contains(string $service, $latitude, $longitude): bool
    {
        if (!in_array($service, ['local', 'intercity'], true)) return true;
        if (!is_numeric($latitude) || !is_numeric($longitude) || !is_finite((float) $latitude) || !is_finite((float) $longitude) || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) return false;
        static $areas;
        $areas ??= json_decode(file_get_contents(module_path('CustomerPortalApi', 'Resources/data/booking-service-areas.json')), true, 512, JSON_THROW_ON_ERROR);
        $geometry = $areas[$service]['geometry'];
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $point = new Coordinate((float) $latitude, (float) $longitude);
        foreach ($polygons as $rings) {
            $inside = false;
            foreach ($rings as $index => $ring) {
                $polygon = new Polygon();
                foreach ($ring as [$lng, $lat]) $polygon->addPoint(new Coordinate($lat, $lng));
                if ($index === 0) $inside = $polygon->contains($point);
                elseif ($polygon->contains($point)) $inside = false;
            }
            if ($inside) return true;
        }
        return false;
    }

    public function validate(string $service, array $pickup, array $destination): void
    {
        if (!in_array($service, ['local', 'intercity'], true)) return;
        $errors = [];
        foreach (['pickup' => $pickup, 'destination' => $destination] as $field => $point) {
            if (!$this->contains($service, $point['latitude'] ?? null, $point['longitude'] ?? null)) {
                $errors[$field] = $service === 'local' ? 'Local delivery is currently available within Lusaka only. Select a location in Lusaka.'
                    : 'City-to-city delivery is available within Zambia only. Select a location in Zambia.';
            }
        }
        if ($errors) throw ValidationException::withMessages($errors);
    }
}
