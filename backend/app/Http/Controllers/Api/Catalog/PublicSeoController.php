<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeoPageResource;
use App\Models\SeoPage;
use App\Support\ApiResponse;
use App\Support\SeoPages;
use Illuminate\Http\JsonResponse;

class PublicSeoController extends Controller
{
    public function show(string $page): JsonResponse
    {
        if (! SeoPages::isValid($page)) {
            return ApiResponse::error(__('messages.not_found'), 404);
        }

        $record = SeoPage::query()->where('page_key', $page)->first();

        if ($record === null) {
            $record = new SeoPage([
                'page_key' => $page,
                'title' => 'حبر وأبعاد | خدمات التسويق والطباعة وتطوير الأعمال',
                'description' => 'منصة سعودية لخدمات تشخيص الأعمال والتسويق والمحتوى والتصوير والمتاجر والطباعة والتغليف وتنظيم المعارض والافتتاحات في جميع مدن المملكة.',
                'robots' => 'index,follow',
            ]);
        }

        return ApiResponse::success(SeoPageResource::make($record)->resolve());
    }
}
