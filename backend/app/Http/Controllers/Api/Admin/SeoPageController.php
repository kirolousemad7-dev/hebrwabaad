<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seo\UpdateSeoPageRequest;
use App\Http\Resources\SeoPageResource;
use App\Models\SeoPage;
use App\Support\ApiResponse;
use App\Support\SeoPages;
use Illuminate\Http\JsonResponse;

class SeoPageController extends Controller
{
    public function index(): JsonResponse
    {
        $existing = SeoPage::query()->get()->keyBy('page_key');

        $pages = collect(SeoPages::KEYS)->map(function (string $key) use ($existing): SeoPage {
            return $existing->get($key) ?? new SeoPage([
                'page_key' => $key,
                'robots' => 'index,follow',
            ]);
        });

        return ApiResponse::success(SeoPageResource::collection($pages)->resolve());
    }

    public function show(string $page): JsonResponse
    {
        if (! SeoPages::isValid($page)) {
            return ApiResponse::error('Not found.', 404);
        }

        $record = SeoPage::query()->where('page_key', $page)->first()
            ?? new SeoPage(['page_key' => $page, 'robots' => 'index,follow']);

        return ApiResponse::success(SeoPageResource::make($record)->resolve());
    }

    public function update(UpdateSeoPageRequest $request, string $page): JsonResponse
    {
        if (! SeoPages::isValid($page)) {
            return ApiResponse::error('Not found.', 404);
        }

        $record = SeoPage::query()->firstOrNew(['page_key' => $page]);
        $record->fill($request->validated());
        $record->page_key = $page;
        $record->save();

        return ApiResponse::success(SeoPageResource::make($record)->resolve());
    }
}
