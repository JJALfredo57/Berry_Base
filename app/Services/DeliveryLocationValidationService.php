<?php

namespace App\Services;

class DeliveryLocationValidationService
{
    public function addressFromRequest($request, string $fallbackField = 'address'): string
    {
        $parts = $this->structuredParts($request);
        if ($this->hasStructuredInput($parts)) {
            return $this->composeStructuredAddress($parts);
        }

        return trim((string) $request->input($fallbackField, ''));
    }

    public function validateRequest($request, ?float $lat, ?float $lng, ?string $zone = null, bool $requireZoneMatch = false, string $fallbackField = 'address'): array
    {
        $parts = $this->structuredParts($request);
        $structuredRequired = $request->boolean('_structured_address') || $this->hasStructuredInput($parts);
        if ($structuredRequired) {
            $missing = $this->missingStructuredFields($parts);
            if ($missing) {
                return ['ok' => false, 'message' => 'Please complete these delivery address details: ' . implode(', ', $missing) . '.'];
            }
        }

        return $this->validate($this->addressFromRequest($request, $fallbackField), $lat, $lng, $zone, $requireZoneMatch);
    }

    private function structuredParts($request): array
    {
        return [
            'house' => trim((string) $request->input('address_house', '')),
            'street' => trim((string) $request->input('address_street', '')),
            'subdivision' => trim((string) $request->input('address_subdivision', '')),
            'barangay' => trim((string) $request->input('address_barangay', '')),
            'city' => trim((string) $request->input('address_city', '')),
            'province' => trim((string) $request->input('address_province', '')),
            'postal_code' => trim((string) $request->input('address_postal_code', '')),
            'landmark' => trim((string) $request->input('address_landmark', '')),
        ];
    }

    private function hasStructuredInput(array $parts): bool
    {
        return collect($parts)->filter(fn ($value) => $value !== '')->isNotEmpty();
    }

    private function missingStructuredFields(array $parts): array
    {
        $labels = [
            'house' => 'House / unit / building no.',
            'street' => 'Street / road',
            'barangay' => 'Barangay',
            'city' => 'City / municipality',
            'province' => 'Province',
            'landmark' => 'Landmark',
        ];

        $missing = [];
        foreach ($labels as $key => $label) {
            if (($parts[$key] ?? '') === '') $missing[] = $label;
        }
        return $missing;
    }

    private function composeStructuredAddress(array $parts): string
    {
        $main = array_filter([
            $parts['house'] ?? '',
            $parts['street'] ?? '',
            $parts['subdivision'] ?? '',
            ($parts['barangay'] ?? '') !== '' ? 'Barangay ' . $parts['barangay'] : '',
            $parts['city'] ?? '',
            $parts['province'] ?? '',
            $parts['postal_code'] ?? '',
        ]);

        $address = implode(', ', $main);
        if (($parts['landmark'] ?? '') !== '') {
            $address .= '. Landmark: ' . $parts['landmark'];
        }

        return trim($address);
    }
    public function validate(?string $address, ?float $lat, ?float $lng, ?string $zone = null, bool $requireZoneMatch = false): array
    {
        $address = trim((string) $address);
        $zone = trim((string) $zone);

        if ($address === '' || $lat === null || $lng === null) {
            return ['ok' => false, 'message' => 'Please pin your location on the map and enter your complete delivery address.'];
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || (abs($lat) < 0.000001 && abs($lng) < 0.000001)) {
            return ['ok' => false, 'message' => 'The pinned delivery location is invalid. Please pin your exact location again.'];
        }

        $plainAddress = $this->normalizeLocationText($address);
        $wordCount = count(array_filter(explode(' ', $plainAddress), fn ($word) => strlen($word) >= 2));
        if (strlen($plainAddress) < 18 || $wordCount < 4) {
            return ['ok' => false, 'message' => 'Please enter a complete delivery address with house/building, street or subdivision, barangay, and landmark.'];
        }

        if ($requireZoneMatch) {
            if ($zone === '') {
                return ['ok' => false, 'message' => 'Please pin a location inside this shop delivery coverage area or choose pickup.'];
            }

            if (!$this->addressMentionsZone($address, $zone)) {
                return ['ok' => false, 'message' => "Your typed address does not match the pinned delivery area ({$zone}). Please correct the address or move the map pin."];
            }
        }

        return ['ok' => true, 'message' => null];
    }

    private function normalizeLocationText(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function addressMentionsZone(string $address, string $zone): bool
    {
        $addressText = $this->normalizeLocationText($address);
        $zoneText = $this->normalizeLocationText($zone);
        if ($addressText === '' || $zoneText === '') return false;
        if (str_contains($addressText, $zoneText)) return true;

        $ignore = ['barangay', 'brgy', 'zone', 'area'];
        $zoneWords = array_values(array_filter(
            explode(' ', $zoneText),
            fn ($word) => strlen($word) >= 3 && !in_array($word, $ignore, true)
        ));
        if (!$zoneWords) return true;

        $matches = 0;
        foreach ($zoneWords as $word) {
            if (str_contains($addressText, $word)) $matches++;
        }

        return $matches >= min(2, count($zoneWords));
    }
}

