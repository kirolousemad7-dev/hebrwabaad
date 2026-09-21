<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\CmsPageResource;
use App\Models\CmsPage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCmsPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $footerOnly = filter_var($request->query('footer', false), FILTER_VALIDATE_BOOL);

        $query = CmsPage::query()->published();

        if ($footerOnly) {
            $query->where('show_in_footer', true)
                ->orderBy('footer_group')
                ->orderBy('footer_order')
                ->orderBy('title');
        } else {
            $query->orderBy('title');
        }

        $pages = $query->get([
            'id',
            'title',
            'slug',
            'page_type',
            'show_in_footer',
            'footer_group',
            'footer_order',
            'updated_at',
        ]);

        return ApiResponse::success(
            $pages->map(fn (CmsPage $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'page_type' => $page->page_type instanceof \BackedEnum
                    ? $page->page_type->value
                    : $page->page_type,
                'path' => $page->publicPath(),
                'show_in_footer' => (bool) $page->show_in_footer,
                'footer_group' => $page->footer_group,
                'footer_order' => (int) $page->footer_order,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ])->values()->all()
        );
    }

    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return ApiResponse::success(CmsPageResource::make($page)->resolve());
    }
}
