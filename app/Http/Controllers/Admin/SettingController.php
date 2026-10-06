<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateContactRequest;
use App\Http\Requests\Setting\UpdateHeroImageRequest;
use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use App\Services\PhotoStorageService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private PhotoStorageService $photos)
    {
    }

    public function updateHeroImage(UpdateHeroImageRequest $request): SiteSettingResource
    {
        $path = $this->photos->replace(
            $request->file('photo'),
            'hero',
            SiteSetting::get(SiteSetting::KEY_HERO_IMAGE),
        );

        SiteSetting::set(SiteSetting::KEY_HERO_IMAGE, $path);

        return new SiteSettingResource(SiteSetting::payload());
    }

    public function destroyHeroImage(): SiteSettingResource
    {
        $this->photos->delete(SiteSetting::get(SiteSetting::KEY_HERO_IMAGE));
        SiteSetting::set(SiteSetting::KEY_HERO_IMAGE, null);

        return new SiteSettingResource(SiteSetting::payload());
    }

    public function updateMapsQuery(Request $request): SiteSettingResource
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        SiteSetting::set(SiteSetting::KEY_MAPS_QUERY, trim($validated['query']));

        return new SiteSettingResource(SiteSetting::payload());
    }

    public function destroyMapsQuery(): SiteSettingResource
    {
        SiteSetting::set(SiteSetting::KEY_MAPS_QUERY, null);

        return new SiteSettingResource(SiteSetting::payload());
    }

    public function updateContact(UpdateContactRequest $request): SiteSettingResource
    {
        SiteSetting::contact()->fill($request->validated())->save();

        return new SiteSettingResource(SiteSetting::payload());
    }

    public function destroyContact(): SiteSettingResource
    {
        SiteSetting::contact()->fill([
            'address' => null,
            'phone' => null,
            'email' => null,
            'hours' => null,
        ])->save();

        return new SiteSettingResource(SiteSetting::payload());
    }
}