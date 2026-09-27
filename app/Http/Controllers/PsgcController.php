<?php

namespace App\Http\Controllers;

use App\Services\PsgcService;
use Illuminate\Http\Request;

class PsgcController extends Controller
{
    public function provinces(Request $request, PsgcService $psgc)
    {
        return response()->json([
            'items' => $psgc->provinces($request->query('q'), 80),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function citiesMunicipalities(Request $request, PsgcService $psgc)
    {
        $provinceCode = trim((string) $request->query('province_code', ''));
        if ($provinceCode === '') return response()->json(['items' => []]);

        return response()->json([
            'items' => $psgc->citiesMunicipalities($provinceCode, $request->query('q'), 120),
        ])->header('Cache-Control', 'public, max-age=1800');
    }

    public function barangays(Request $request, PsgcService $psgc)
    {
        $cityCode = trim((string) $request->query('city_municipality_code', ''));
        if ($cityCode === '') return response()->json(['items' => []]);

        return response()->json([
            'items' => $psgc->barangays($cityCode, $request->query('q'), 200),
        ])->header('Cache-Control', 'public, max-age=1800');
    }
}
