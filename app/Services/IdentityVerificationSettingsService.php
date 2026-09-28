<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IdentityVerificationSettingsService
{
    public function defaultTypes(): array
    {
        return [
            ['name' => 'National ID', 'keywords' => ['PhilID', 'PhilSys', 'National Identification'], 'requires_back' => false],
            ['name' => "Driver's License", 'keywords' => ['Driver License', "Driver's License", 'Driver Licence', "Driver's Licence", 'Land Transportation Office', 'LTO'], 'requires_back' => true],
            ['name' => 'UMID', 'keywords' => ['Unified Multi-Purpose ID', 'UMID'], 'requires_back' => true],
            ['name' => 'Postal ID', 'keywords' => ['Postal ID', 'PHLPost'], 'requires_back' => true],
            ['name' => 'Student ID', 'keywords' => ['Student ID', 'School ID'], 'requires_back' => false],
            ['name' => 'Other Government ID', 'keywords' => ['Government ID', 'Valid ID'], 'requires_back' => true],
        ];
    }

    public function types(): array
    {
        $stored = null;

        if (Schema::hasTable('platform_settings') && Schema::hasColumn('platform_settings', 'verification_id_types')) {
            $stored = DB::table('platform_settings')->value('verification_id_types');
        }

        $decoded = is_string($stored) && $stored !== '' ? json_decode($stored, true) : null;
        $types = is_array($decoded) ? $decoded : $this->defaultTypes();

        return $this->sanitizeTypes($types) ?: $this->defaultTypes();
    }

    public function typeNames(): array
    {
        return array_values(array_map(fn ($type) => $type['name'], $this->types()));
    }

    public function typeMap(): array
    {
        $map = [];
        foreach ($this->types() as $type) {
            $map[$type['name']] = $type;
        }

        return $map;
    }

    public function requiresBack(string $name): bool
    {
        return (bool) (($this->typeMap()[$name]['requires_back'] ?? true));
    }

    public function selfieRequired(): bool
    {
        return true;
    }

    public function isAllowed(string $name): bool
    {
        return in_array($name, $this->typeNames(), true);
    }

    public function normalizeInput(array $names, array $keywords, array $requiresBack = []): array
    {
        $types = [];

        foreach ($names as $index => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $keywordLine = (string) ($keywords[$index] ?? '');
            $keywordItems = array_values(array_filter(array_map('trim', explode(',', $keywordLine))));

            $types[] = [
                'name' => $name,
                'keywords' => array_values(array_unique($keywordItems)),
                'requires_back' => !empty($requiresBack[$index]),
            ];
        }

        return $this->sanitizeTypes($types) ?: $this->defaultTypes();
    }

    private function sanitizeTypes(array $types): array
    {
        $clean = [];
        $seen = [];

        foreach ($types as $type) {
            $name = trim((string) ($type['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }

            $keywords = $type['keywords'] ?? [];
            if (!is_array($keywords)) {
                $keywords = [];
            }

            $clean[] = [
                'name' => mb_substr($name, 0, 60),
                'keywords' => array_values(array_unique(array_filter(array_map(
                    fn ($keyword) => mb_substr(trim((string) $keyword), 0, 80),
                    $keywords
                )))),
                'requires_back' => array_key_exists('requires_back', $type) ? (bool) $type['requires_back'] : true,
            ];
            $seen[$key] = true;
        }

        return array_slice($clean, 0, 20);
    }
}
