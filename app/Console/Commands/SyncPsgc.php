<?php

namespace App\Console\Commands;

use App\Services\PsgcService;
use Illuminate\Console\Command;

class SyncPsgc extends Command
{
    protected $signature = 'psgc:sync';
    protected $description = 'Sync Philippine Standard Geographic Code provinces and cities/municipalities into the local cache. Barangays are cached on demand per selected city/municipality.';

    public function handle(PsgcService $psgc): int
    {
        $this->info('Syncing PSGC data...');

        $counts = $psgc->syncAll(function (string $type, int $count) {
            $this->line("Synced {$count} {$type}.");
        });

        if (($counts['provinces'] ?? 0) === 0) {
            $this->error('No PSGC data was received. Please check network access to https://psgc.cloud.');
            return self::FAILURE;
        }

        $this->info('PSGC sync complete.');
        return self::SUCCESS;
    }
}

