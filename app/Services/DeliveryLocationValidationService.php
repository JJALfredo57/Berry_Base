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

    public function validateRequest($request, ?float $lat, ?float $lng, ?string $zone = null, bool $requireZoneMatch = false, string $fallbackField = 'address', ?string $shopId = null): array
    {
        $parts = $this->structuredParts($request);
        $structuredRequired = $request->boolean('_structured_address') || $this->hasStructuredInput($parts);
        if ($structuredRequired) {
            $missing = $this->missingStructuredFields($parts);
            if ($missing) {
                return ['ok' => false, 'message' => 'Please complete these delivery address details: ' . implode(', ', $missing) . '.'];
            }

            $invalid = $this->invalidStructuredFields($parts);
            if ($invalid) {
                return ['ok' => false, 'message' => 'Please enter a real, readable delivery address. Check these fields: ' . implode(', ', $invalid) . '.'];
            }

            $psgcValidation = app(\App\Services\PsgcService::class)->validateHierarchy(
                $request->input('address_province_code'),
                $request->input('address_city_code'),
                $request->input('address_barangay_code')
            );
            if (!$psgcValidation['ok']) {
                return ['ok' => false, 'message' => $psgcValidation['message']];
            }

            if ($shopId !== null) {
                $coverageValidation = $this->validateShopCoverage($psgcValidation, $shopId);
                if (!$coverageValidation['ok']) {
                    return $coverageValidation;
                }
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

    private function invalidStructuredFields(array $parts): array
    {
        $labels = [
            'street' => 'Street / road',
            'barangay' => 'Barangay',
            'city' => 'City / municipality',
            'province' => 'Province',
            'landmark' => 'Landmark',
        ];

        $invalid = [];
        foreach ($labels as $key => $label) {
            if (!$this->looksLikeReadableLocationText($parts[$key] ?? '')) {
                $invalid[] = $label;
            }
        }

        if (($parts['postal_code'] ?? '') !== '' && !preg_match('/^\d{4}$/', $parts['postal_code'])) {
            $invalid[] = 'Postal Code';
        }

        return $invalid;
    }

    private function looksLikeReadableLocationText(string $value): bool
    {
        $text = $this->normalizeLocationText($value);
        if (strlen($text) < 4) return false;

        $words = array_values(array_filter(explode(' ', $text), fn ($word) => strlen($word) >= 2));
        if (!$words) return false;

        foreach ($words as $word) {
            if (preg_match('/[aeiou]/', $word) && !preg_match('/([bcdfghjklmnpqrstvwxyz])\1{2,}/', $word)) {
                return true;
            }
        }

        return false;
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

    private function validateShopCoverage(array $psgcValidation, string $shopId): array
    {
        $zoneColumns = ['barangay'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('delivery_zones', 'zone_address')) {
            $zoneColumns[] = 'zone_address';
        }
        $zones = \Illuminate\Support\Facades\DB::table('delivery_zones')
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->get($zoneColumns);

        if ($zones->isEmpty()) {
            return ['ok' => true, 'message' => null];
        }

        $provinceCode = (string) ($psgcValidation['province']['code'] ?? '');
        $provinceName = $this->normalizeLocationText((string) ($psgcValidation['province']['name'] ?? ''));
        $cityCode = (string) ($psgcValidation['city']['code'] ?? '');
        $cityName = $this->normalizeLocationText((string) ($psgcValidation['city']['name'] ?? ''));
        $barangayName = $this->normalizeLocationText((string) ($psgcValidation['barangay']['name'] ?? ''));

        $allowed = $this->allowedPsgcAreasFromZones($zones);
        if (!empty($allowed['province_codes']) && !in_array($provinceCode, $allowed['province_codes'], true)) {
            return ['ok' => false, 'message' => 'This shop does not deliver to the selected province. Please choose an address inside the shop delivery coverage or choose pickup.'];
        }
        if (empty($allowed['province_codes']) && !empty($allowed['province_names']) && !in_array($provinceName, $allowed['province_names'], true)) {
            return ['ok' => false, 'message' => 'This shop does not deliver to the selected province. Please choose an address inside the shop delivery coverage or choose pickup.'];
        }

        if (!empty($allowed['city_codes']) && !in_array($cityCode, $allowed['city_codes'], true)) {
            return ['ok' => false, 'message' => 'This shop does not deliver to the selected city/municipality. Please choose an address inside the shop delivery coverage or choose pickup.'];
        }
        if (empty($allowed['city_codes']) && !empty($allowed['city_names']) && !in_array($cityName, $allowed['city_names'], true)) {
            return ['ok' => false, 'message' => 'This shop does not deliver to the selected city/municipality. Please choose an address inside the shop delivery coverage or choose pickup.'];
        }

        foreach ($zones as $zone) {
            $zoneText = $this->normalizeLocationText(trim((string) ($zone->barangay ?? '') . ' ' . (string) ($zone->zone_address ?? '')));
            if ($zoneText !== '' && $barangayName !== '' && (str_contains($zoneText, $barangayName) || str_contains($barangayName, $zoneText))) {
                return ['ok' => true, 'message' => null];
            }
        }

        return ['ok' => false, 'message' => 'Selected barangay is not covered by this shop. Please choose a covered barangay or choose pickup.'];
    }

    private function allowedPsgcAreasFromZones($zones): array
    {
        $cityNames = [];
        foreach ($zones as $zone) {
            $text = trim((string) ($zone->barangay ?? '') . ', ' . (string) ($zone->zone_address ?? ''));
            foreach ($this->areaCandidates($text) as $candidate) {
                $cityNames[$candidate] = true;
            }
        }

        $allowed = [
            'city_names' => array_map(fn ($name) => $this->normalizeLocationText($name), array_keys($cityNames)),
            'city_codes' => [],
            'province_names' => [],
            'province_codes' => [],
        ];

        if (\Illuminate\Support\Facades\Schema::hasTable('psgc_cities_municipalities') && !empty($cityNames)) {
            $cities = \Illuminate\Support\Facades\DB::table('psgc_cities_municipalities')
                ->whereIn('name', array_keys($cityNames))
                ->get(['code', 'name', 'province_code', 'province_name']);
            foreach ($cities as $city) {
                $allowed['city_codes'][] = (string) $city->code;
                $allowed['city_names'][] = $this->normalizeLocationText((string) $city->name);
                if (!empty($city->province_code)) $allowed['province_codes'][] = (string) $city->province_code;
                if (!empty($city->province_name)) $allowed['province_names'][] = $this->normalizeLocationText((string) $city->province_name);
            }
        }

        $allowed['city_names'] = array_values(array_unique(array_filter($allowed['city_names'])));
        $allowed['city_codes'] = array_values(array_unique(array_filter($allowed['city_codes'])));
        $allowed['province_names'] = array_values(array_unique(array_filter($allowed['province_names'])));
        $allowed['province_codes'] = array_values(array_unique(array_filter($allowed['province_codes'])));

        return $allowed;
    }

    private function areaCandidates(string $text): array
    {
        $candidates = [];
        if (preg_match_all('/\(([^)]+)\)/', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $candidate = trim($match);
                if ($candidate !== '') $candidates[] = $candidate;
            }
        }

        foreach (explode(',', $text) as $index => $part) {
            if ($index === 0) continue;
            $candidate = trim(preg_replace('/\([^)]*\)/', '', $part) ?? '');
            if ($candidate !== '') $candidates[] = $candidate;
        }

        return array_values(array_unique($candidates));
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

