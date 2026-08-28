<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'hero_image' => SiteSetting::get(SiteSetting::KEY_HERO_IMAGE),
                'maps_query' => SiteSetting::get(SiteSetting::KEY_MAPS_QUERY),
            ],
        ]);
    }
}
