<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreCmsPageRequest;
use App\Http\Requests\Cms\UpdateCmsPageFooterRequest;
use App\Http\Requests\Cms\UpdateCmsPageRequest;
use App\Http\Resources\CmsPageResource;
use App\Models\CmsPage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CmsPage::class);

        $pages = CmsPage::query()
            ->orderBy('footer_group')
            ->orderBy('footer_order')
            ->orderBy('title')
            ->get();

        return ApiResponse::success(CmsPageResource::collection($pages)->resolve($request));
    }

    public function store(StoreCmsPageRequest $request): JsonResponse
    {
        $this->authorize('create', CmsPage::class);

        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = CmsPage::uniqueSlug((string) $data['title']);
        }

        $page = CmsPage::query()->create($data);

        return ApiResponse::success(CmsPageResource::make($page)->resolve($request), 201);
    }

    public function show(Request $request, CmsPage $page): JsonResponse
    {
        $this->authorize('view', $page);

        return ApiResponse::success(CmsPageResource::make($page)->resolve($request));
    }

    public function update(UpdateCmsPageRequest $request, CmsPage $page): JsonResponse
    {
        $this->authorize('update', $page);

        $data = $request->validated();
        if (array_key_exists('slug', $data) && ($data['slug'] === null || $data['slug'] === '')) {
            $data['slug'] = CmsPage::uniqueSlug((string) ($data['title'] ?? $page->title), $page->id);
        }

        $page->fill($data);
        $page->save();

        return ApiResponse::success(CmsPageResource::make($page->fresh())->resolve($request));
    }

    public function destroy(CmsPage $page): JsonResponse
    {
        $this->authorize('delete', $page);

        if ($page->isSystem()) {
            return ApiResponse::error(
                'لا يمكن حذف الصفحات الأساسية. ألغِ نشرها أو أخفِها من الفوتر بدلًا من الحذف.',
                422,
            );
        }

        $page->delete();

        return ApiResponse::success(null);
    }

    public function publish(Request $request, CmsPage $page): JsonResponse
    {
        $this->authorize('publish', $page);

        $request->validate([
            'is_published' => ['required', 'boolean'],
        ]);

        $page->is_published = (bool) $request->boolean('is_published');
        $page->save();

        return ApiResponse::success(CmsPageResource::make($page->fresh())->resolve($request));
    }

    public function footer(UpdateCmsPageFooterRequest $request, CmsPage $page): JsonResponse
    {
        $this->authorize('manageFooter', $page);

        $data = $request->validated();
        $page->show_in_footer = (bool) $data['show_in_footer'];
        if (array_key_exists('footer_group', $data)) {
            $page->footer_group = $data['footer_group'];
        }
        if (array_key_exists('footer_order', $data)) {
            $page->footer_order = (int) ($data['footer_order'] ?? 0);
        }
        $page->save();

        return ApiResponse::success(CmsPageResource::make($page->fresh())->resolve($request));
    }
}
