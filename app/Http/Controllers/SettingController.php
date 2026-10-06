<?php

namespace App\Http\Controllers;

use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;

class SettingController extends Controller
{
    public function index(): SiteSettingResource
    {
        return new SiteSettingResource(SiteSetting::payload());
    }
}