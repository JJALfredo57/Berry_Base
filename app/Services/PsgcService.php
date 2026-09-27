<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class PsgcService
{
    private string $baseUrl = 'https://psgc.cloud/api/v2';

    public function provinces(?string $query = null, int $limit = 50): array
    {
        $items = $this->queryTable('psgc_provinces', $query, $limit, ['code', 'name', 'region_code', 'region_name']);
        if ($items) return $items;

        $remote = $this->remoteList('/provinces');
        $this->upsertProvinces($remote);
        return $this->filterItems($remote, $query, $limit, fn ($row) => [
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'region_code' => (string) data_get($row, 'region.code', ''),
            'region_name' => (string) (data_get($row, 'region.name') ?? ($row['region'] ?? '')),
        ]);
    }

    public function citiesMunicipalities(string $provinceCode, ?string $query = null, int $limit = 80): array
    {
        $provinceCode = trim($provinceCode);
        $items = $this->queryTable('psgc_cities_municipalities', $query, $limit, ['code', 'name', 'type', 'province_code', 'province_name'], ['province_code' => $provinceCode]);
        if ($items) return $items;

        $remote = $this->remoteList('/provinces/' . rawurlencode($provinceCode) . '/cities-municipalities');
        $this->upsertCitiesMunicipalities($remote);
        return $this->filterItems($remote, $query, $limit, fn ($row) => [
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'province_code' => (string) (data_get($row, 'province.code') ?? ($row['province_code'] ?? ($provinceCode ?: $this->deriveProvinceCode((string) ($row['code'] ?? ''))))),
            'province_name' => (string) (data_get($row, 'province.name') ?? ($row['province'] ?? '')),
        ]);
    }

    public function barangays(string $cityMunicipalityCode, ?string $query = null, int $limit = 120): array
    {
        $cityMunicipalityCode = trim($cityMunicipalityCode);
        $items = $this->queryTable('psgc_barangays', $query, $limit, ['code', 'name', 'city_municipality_code', 'city_municipality_name', 'province_code', 'province_name'], ['city_municipality_code' => $cityMunicipalityCode]);
        if ($items) return $items;

        $remote = $this->remoteList('/cities-municipalities/' . rawurlencode($cityMunicipalityCode) . '/barangays');
        $this->upsertBarangays($remote);
        return $this->filterItems($remote, $query, $limit, fn ($row) => [
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'city_municipality_code' => (string) (data_get($row, 'city_municipality.code') ?? ($row['city_municipality_code'] ?? ($cityMunicipalityCode ?: $this->deriveCityMunicipalityCode((string) ($row['code'] ?? ''))))),
            'city_municipality_name' => (string) (data_get($row, 'city_municipality.name') ?? ($row['city_municipality'] ?? '')),
            'province_code' => (string) (data_get($row, 'province.code') ?? ($row['province_code'] ?? $this->deriveProvinceCode((string) ($row['code'] ?? '')))),
            'province_name' => (string) (data_get($row, 'province.name') ?? ($row['province'] ?? '')),
        ]);
    }

    public function validateHierarchy(?string $provinceCode, ?string $cityCode, ?string $barangayCode): array
    {
        $provinceCode = trim((string) $provinceCode);
        $cityCode = trim((string) $cityCode);
        $barangayCode = trim((string) $barangayCode);

        if ($provinceCode === '' || $cityCode === '' || $barangayCode === '') {
            return ['ok' => false, 'message' => 'Please choose a province, city/municipality, and barangay from the list.'];
        }

        $province = $this->findProvince($provinceCode);
        if (!$province) return ['ok' => false, 'message' => 'Selected province is invalid. Please choose from the list.'];

        $city = $this->findCity($cityCode);
        if (!$city) {
            $this->citiesMunicipalities($provinceCode);
            $city = $this->findCity($cityCode);
        }
        if (!$city || (string) ($city->province_code ?? '') !== $provinceCode) {
            return ['ok' => false, 'message' => 'Selected city/municipality does not belong to the selected province.'];
        }

        $barangay = $this->findBarangay($barangayCode);
        if (!$barangay) {
            $this->barangays($cityCode);
            $barangay = $this->findBarangay($barangayCode);
        }
        if (!$barangay || (string) ($barangay->city_municipality_code ?? '') !== $cityCode) {
            return ['ok' => false, 'message' => 'Selected barangay does not belong to the selected city/municipality.'];
        }

        return [
            'ok' => true,
            'message' => null,
            'province' => ['code' => $provinceCode, 'name' => (string) $province->name],
            'city' => ['code' => $cityCode, 'name' => (string) $city->name],
            'barangay' => ['code' => $barangayCode, 'name' => (string) $barangay->name],
        ];
    }

    public function addressCodesFromRequest($request): array
    {
        $validated = $this->validateHierarchy(
            $request->input('address_province_code'),
            $request->input('address_city_code'),
            $request->input('address_barangay_code')
        );

        if (!$validated['ok']) return [];

        return [
            'province_code' => $validated['province']['code'],
            'province_name' => $validated['province']['name'],
            'city_municipality_code' => $validated['city']['code'],
            'city_municipality_name' => $validated['city']['name'],
            'barangay_code' => $validated['barangay']['code'],
            'barangay_name' => $validated['barangay']['name'],
        ];
    }

    public function syncAll(?callable $progress = null): array
    {
        $provinces = $this->remoteList('/provinces');
        $this->upsertProvinces($provinces);
        $progress && $progress('provinces', count($provinces));

        $cities = $this->remoteList('/cities-municipalities');
        $this->upsertCitiesMunicipalities($cities);
        $progress && $progress('cities_municipalities', count($cities));

        $progress && $progress('barangays', 0);

        return ['provinces' => count($provinces), 'cities_municipalities' => count($cities), 'barangays' => 0];
    }

    private function queryTable(string $table, ?string $query, int $limit, array $columns, array $where = []): array
    {
        if (!Schema::hasTable($table)) return [];

        $builder = DB::table($table)->select($columns);
        foreach ($where as $column => $value) $builder->where($column, $value);
        if ($query = trim((string) $query)) $builder->where('name', 'like', '%' . $query . '%');

        return $builder->orderBy('name')->limit($limit)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function remoteList(string $path): array
    {
        try {
            $response = Http::timeout(20)->retry(2, 250)->acceptJson()->get($this->baseUrl . $path);
            if (!$response->ok()) return [];
            $json = $response->json();
            if (is_array($json) && array_key_exists('data', $json) && is_array($json['data'])) return $json['data'];
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function filterItems(array $items, ?string $query, int $limit, callable $mapper): array
    {
        $query = strtolower(trim((string) $query));
        $filtered = [];
        foreach ($items as $item) {
            $mapped = $mapper($item);
            if (($mapped['code'] ?? '') === '' || ($mapped['name'] ?? '') === '') continue;
            if ($query !== '' && !str_contains(strtolower($mapped['name']), $query)) continue;
            $filtered[] = $mapped;
            if (count($filtered) >= $limit) break;
        }
        return $filtered;
    }

    private function findProvince(string $code): ?object
    {
        if (!Schema::hasTable('psgc_provinces')) return null;
        $province = DB::table('psgc_provinces')->where('code', $code)->first();
        if (!$province) {
            $this->provinces();
            $province = DB::table('psgc_provinces')->where('code', $code)->first();
        }
        return $province;
    }

    private function findCity(string $code): ?object
    {
        if (!Schema::hasTable('psgc_cities_municipalities')) return null;
        return DB::table('psgc_cities_municipalities')->where('code', $code)->first();
    }

    private function findBarangay(string $code): ?object
    {
        if (!Schema::hasTable('psgc_barangays')) return null;
        return DB::table('psgc_barangays')->where('code', $code)->first();
    }

    private function deriveProvinceCode(string $code): string
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';
        return strlen($digits) >= 5 ? substr($digits, 0, 5) . '00000' : '';
    }

    private function deriveCityMunicipalityCode(string $code): string
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';
        return strlen($digits) >= 7 ? substr($digits, 0, 7) . '000' : '';
    }
    private function upsertProvinces(array $rows): void
    {
        if (!Schema::hasTable('psgc_provinces') || !$rows) return;
        $now = now();
        collect($rows)->chunk(500)->each(function ($chunk) use ($now) {
            DB::table('psgc_provinces')->upsert($chunk->map(fn ($row) => [
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'region_code' => (string) data_get($row, 'region.code', ''),
                'region_name' => (string) (data_get($row, 'region.name') ?? ($row['region'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ])->filter(fn ($row) => $row['code'] !== '' && $row['name'] !== '')->all(), ['code'], ['name', 'region_code', 'region_name', 'updated_at']);
        });
    }

    private function upsertCitiesMunicipalities(array $rows): void
    {
        if (!Schema::hasTable('psgc_cities_municipalities') || !$rows) return;
        $now = now();
        collect($rows)->chunk(500)->each(function ($chunk) use ($now) {
            DB::table('psgc_cities_municipalities')->upsert($chunk->map(fn ($row) => [
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'type' => (string) ($row['type'] ?? ''),
                'province_code' => (string) (data_get($row, 'province.code') ?? ($row['province_code'] ?? $this->deriveProvinceCode((string) ($row['code'] ?? '')))),
                'province_name' => (string) (data_get($row, 'province.name') ?? ($row['province'] ?? '')),
                'region_code' => (string) data_get($row, 'region.code', ''),
                'region_name' => (string) (data_get($row, 'region.name') ?? ($row['region'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ])->filter(fn ($row) => $row['code'] !== '' && $row['name'] !== '')->all(), ['code'], ['name', 'type', 'province_code', 'province_name', 'region_code', 'region_name', 'updated_at']);
        });
    }

    private function upsertBarangays(array $rows): void
    {
        if (!Schema::hasTable('psgc_barangays') || !$rows) return;
        $now = now();
        collect($rows)->chunk(1000)->each(function ($chunk) use ($now) {
            DB::table('psgc_barangays')->upsert($chunk->map(fn ($row) => [
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'city_municipality_code' => (string) (data_get($row, 'city_municipality.code') ?? ($row['city_municipality_code'] ?? $this->deriveCityMunicipalityCode((string) ($row['code'] ?? '')))),
                'city_municipality_name' => (string) (data_get($row, 'city_municipality.name') ?? ($row['city_municipality'] ?? '')),
                'province_code' => (string) (data_get($row, 'province.code') ?? ($row['province_code'] ?? $this->deriveProvinceCode((string) ($row['code'] ?? '')))),
                'province_name' => (string) (data_get($row, 'province.name') ?? ($row['province'] ?? '')),
                'region_code' => (string) data_get($row, 'region.code', ''),
                'region_name' => (string) (data_get($row, 'region.name') ?? ($row['region'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ])->filter(fn ($row) => $row['code'] !== '' && $row['name'] !== '')->all(), ['code'], ['name', 'status', 'city_municipality_code', 'city_municipality_name', 'province_code', 'province_name', 'region_code', 'region_name', 'updated_at']);
        });
    }
}

