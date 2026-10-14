<?php

namespace App\Services;

use App\VisitLocationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Where and when the device says an operation happened.
 *
 * It is read leniently on purpose: a missing or malformed reading is recorded
 * as "no location" and never blocks the sale, delivery or payment it comes with.
 */
final readonly class VisitLocation
{
    private const int MAXIMUM_CLOCK_AHEAD_MINUTES = 10;

    private const int MAXIMUM_OFFLINE_DAYS = 30;

    public function __construct(
        public VisitLocationStatus $status,
        public ?string $latitude,
        public ?string $longitude,
        public ?string $accuracyMeters,
        public CarbonImmutable $occurredAt,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $input = $request->input('location');
        $input = is_array($input) ? $input : [];
        $latitude = self::coordinate($input['latitude'] ?? null, 90);
        $longitude = self::coordinate($input['longitude'] ?? null, 180);
        $hasPosition = $latitude !== null && $longitude !== null;
        $accuracy = $input['accuracy'] ?? null;

        return new self(
            status: match (true) {
                $hasPosition => VisitLocationStatus::Captured,
                ($input['status'] ?? null) === VisitLocationStatus::Denied->value => VisitLocationStatus::Denied,
                default => VisitLocationStatus::Unavailable,
            },
            latitude: $hasPosition ? $latitude : null,
            longitude: $hasPosition ? $longitude : null,
            accuracyMeters: $hasPosition && is_numeric($accuracy) && $accuracy >= 0 && $accuracy < 1_000_000
                ? number_format((float) $accuracy, 1, '.', '')
                : null,
            occurredAt: self::occurredAt($input['captured_at'] ?? null),
        );
    }

    /** Straight-line distance in meters to a known point, or null when either side has no coordinates. */
    public function distanceTo(mixed $latitude, mixed $longitude): ?int
    {
        if ($this->latitude === null || $this->longitude === null || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $fromLatitude = deg2rad((float) $this->latitude);
        $toLatitude = deg2rad((float) $latitude);
        $latitudeDelta = $toLatitude - $fromLatitude;
        $longitudeDelta = deg2rad((float) $longitude - (float) $this->longitude);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($fromLatitude) * cos($toLatitude) * sin($longitudeDelta / 2) ** 2;

        return (int) round(6_371_000 * 2 * asin(min(1, sqrt($haversine))));
    }

    private static function coordinate(mixed $value, int $limit): ?string
    {
        return is_numeric($value) && abs((float) $value) <= $limit
            ? number_format((float) $value, 7, '.', '')
            : null;
    }

    /** The device clock is trusted only inside a window that covers working offline for days. */
    private static function occurredAt(mixed $value): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        if (! is_string($value) || $value === '') {
            return $now;
        }

        try {
            $deviceTime = CarbonImmutable::parse($value)->setTimezone($now->getTimezone());
        } catch (\Throwable) {
            return $now;
        }

        return $deviceTime->greaterThan($now->addMinutes(self::MAXIMUM_CLOCK_AHEAD_MINUTES))
            || $deviceTime->lessThan($now->subDays(self::MAXIMUM_OFFLINE_DAYS))
            ? $now
            : $deviceTime;
    }
}
