<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateHeroImageRequest;
use App\Models\SiteSetting;
use App\Services\PhotoStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private PhotoStorageService $photos)
    {
    }

    public function updateHeroImage(UpdateHeroImageRequest $request): JsonResponse
    {
        $path = $this->photos->replace(
            $request->file('photo'),
            'hero',
            SiteSetting::get(SiteSetting::KEY_HERO_IMAGE),
        );

        SiteSetting::set(SiteSetting::KEY_HERO_IMAGE, $path);

        return response()->json([
            'data' => [
                'hero_image' => $path,
            ],
        ]);
    }

    public function destroyHeroImage(): JsonResponse
    {
        $this->photos->delete(SiteSetting::get(SiteSetting::KEY_HERO_IMAGE));
        SiteSetting::set(SiteSetting::KEY_HERO_IMAGE, null);

        return response()->json([
            'data' => [
                'hero_image' => null,
            ],
        ]);
    }

    public function updateMapsQuery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        SiteSetting::set(SiteSetting::KEY_MAPS_QUERY, trim($validated['query']));

        return response()->json([
            'data' => [
                'maps_query' => trim($validated['query']),
            ],
        ]);
    }

    public function destroyMapsQuery(): JsonResponse
    {
        SiteSetting::set(SiteSetting::KEY_MAPS_QUERY, null);

        return response()->json([
            'data' => [
                'maps_query' => null,
            ],
        ]);
    }
}
