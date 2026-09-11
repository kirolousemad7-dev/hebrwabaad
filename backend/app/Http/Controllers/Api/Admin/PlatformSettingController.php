<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSettingController extends Controller
{
    public function __construct(
        private readonly PlatformSettingService $settings,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->settings->manage($request->user())
        );
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'brand' => ['sometimes', 'array'],
            'business' => ['sometimes', 'array'],
            'contact' => ['sometimes', 'array'],
            'social' => ['sometimes', 'array'],
            'website' => ['sometimes', 'array'],
            'homepage' => ['sometimes', 'array'],
            'cta' => ['sometimes', 'array'],
            'printing' => ['sometimes', 'array'],
            'events' => ['sometimes', 'array'],
            'customer' => ['sometimes', 'array'],
            'seo' => ['sometimes', 'array'],
        ]);

        return ApiResponse::success(
            $this->settings->update($request->user(), $payload)
        );
    }

    public function uploadBrandAsset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot' => ['required', 'string', 'in:logo,logo_secondary,mark,favicon,logo_dark,logo_light,og_image'],
            'file' => ['required', 'file', 'max:5120', 'mimes:png,jpg,jpeg,webp,svg,ico'],
        ]);

        return ApiResponse::success(
            $this->settings->storeBrandAsset(
                $request->user(),
                $data['slot'],
                $request->file('file'),
            )
        );
    }
}
