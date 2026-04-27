<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliveryZonesSeeder extends Seeder
{
    public function run(): void
    {
        $shopId = 'zwn37a5y0g0h';
        $now    = now();

        DB::table('delivery_zones')->where('shop_id', $shopId)->delete();

        // ─── BAUTISTA barangays — FREE delivery (within the same municipality) ───
        $bautista = [
            'Baluyot', 'Buenlag', 'Burgos', 'Cacandungan', 'Caramutan',
            'La Paz', 'Maliwalo', 'Mapalad', 'Nalneran',
            'Nancamaliran East', 'Nancamaliran West', 'Nandacan', 'Noblong',
            'Palisoc', 'Poblacion East', 'Poblacion West', 'Pugaro',
            'Rajal Norte', 'Rajal Sur', 'San Pedro', 'San Vicente',
            'Songkoy', 'Talogtog', 'Tara', 'Tomling',
        ];

        // ─── BAYAMBANG barangays — grouped by distance from Bautista ───
        // Near border (₱30, ~20-30 min)
        $bayambangNear = [
            'Abot', 'Anolid', 'Bansing', 'Baro', 'Bayog',
            'Bobon A', 'Bobon B', 'Bobon C', 'Bobon D',
            'Bugtong Balo', 'Bugtong Cutol', 'Bugtong na Munti',
            'Calbueg', 'Canarem', 'Carbon',
            'Caturay Norte', 'Caturay Sur',
            'Darawey', 'Duera', 'Dusoc',
            'Galarion', 'Gamata', 'Guyuang',
            'Inceptuan', 'Lambakin', 'Langiran',
            'Lo-oc', 'Loomat',
        ];

        // Mid-distance (₱50, ~30-45 min) — Bayambang town center & surrounding
        $bayambangMid = [
            'Poblacion (Bayambang)', 'Poblacion Sur',
            'Malimpec East', 'Malimpec West', 'Malioer',
            'Managos', 'Manambong Norte', 'Manambong Parte', 'Manambong Sur',
            'Mangayao', 'Maseil-seil', 'Masibay', 'Masidem', 'Matamis',
            'New Cabugao', 'New Edque', 'New Espacio', 'New Fianza',
            'Nangalisan', 'Nansangaan', 'Olea',
            'Pacuan', 'Palacpac', 'Panaypay', 'Pandayan',
            'Pangopisan Norte', 'Pangopisan Sur',
            'Pogo', 'Polo', 'Puelay', 'Punglo',
            'Rabon', 'Ranao', 'Resurreccion',
            'Salinap', 'San Carlos', 'San Fabian',
            'San Miguel', 'San Nicolas', 'San Pedro (Bayambang)', 'San Ramon',
            'Sapang', 'Siling', 'Sining',
            'Sioasio East', 'Sioasio West',
            'Tabo-og', 'Talang', 'Tamaro',
            'Tampac A', 'Tampac B',
        ];

        // Far barangays (₱80, ~45-60 min)
        $bayambangFar = [
            'Alba', 'Amampeque', 'Atab', 'Bacnono', 'Baligayan',
            'Ballaigui', 'Bano', 'Bateng', 'Berber',
            'Bobon Pandan', 'Bobon Salak',
            'Bugallon Norte', 'Bugallon Proper', 'Bugallon Sur',
            'Esmeralda', 'Hacienda', 'Matayumcab',
            'Parayao', 'Quinaoayanan',
            'Ris', 'Sueco',
            'Tilos', 'Tococ East', 'Tococ West',
            'Todog', 'Tondol', 'Tongkol', 'Tricao',
        ];

        $rows    = [];
        $sort    = 1;

        foreach ($bautista as $brgy) {
            $rows[] = [
                'shop_id'           => $shopId,
                'barangay'          => $brgy . ', Bautista',
                'delivery_fee'      => 0.00,
                'estimated_minutes' => 15,
                'is_active'         => true,
                'sort_order'        => $sort++,
                'zone_type'         => 'near',
                'estimated_time'    => '10–20 mins',
                'created_at'        => $now,
            ];
        }

        foreach ($bayambangNear as $brgy) {
            $rows[] = [
                'shop_id'           => $shopId,
                'barangay'          => $brgy . ', Bayambang',
                'delivery_fee'      => 30.00,
                'estimated_minutes' => 25,
                'is_active'         => true,
                'sort_order'        => $sort++,
                'zone_type'         => 'near',
                'estimated_time'    => '20–30 mins',
                'created_at'        => $now,
            ];
        }

        foreach ($bayambangMid as $brgy) {
            $rows[] = [
                'shop_id'           => $shopId,
                'barangay'          => $brgy,
                'delivery_fee'      => 50.00,
                'estimated_minutes' => 40,
                'is_active'         => true,
                'sort_order'        => $sort++,
                'zone_type'         => 'mid',
                'estimated_time'    => '30–45 mins',
                'created_at'        => $now,
            ];
        }

        foreach ($bayambangFar as $brgy) {
            $rows[] = [
                'shop_id'           => $shopId,
                'barangay'          => $brgy . ', Bayambang',
                'delivery_fee'      => 80.00,
                'estimated_minutes' => 55,
                'is_active'         => true,
                'sort_order'        => $sort++,
                'zone_type'         => 'far',
                'estimated_time'    => '45–60 mins',
                'created_at'        => $now,
            ];
        }

        DB::table('delivery_zones')->insert($rows);

        $this->command->info('Inserted ' . count($rows) . ' delivery zones for shop ' . $shopId);
    }
}
