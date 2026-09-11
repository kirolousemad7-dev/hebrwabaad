<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\UpsertTestimonialRequest;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Testimonial::query()->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success(TestimonialResource::collection($items)->resolve($request));
    }

    public function store(UpsertTestimonialRequest $request): JsonResponse
    {
        $item = Testimonial::query()->create($request->validated());

        return ApiResponse::success(TestimonialResource::make($item)->resolve($request), 201);
    }

    public function update(UpsertTestimonialRequest $request, Testimonial $testimonial): JsonResponse
    {
        $testimonial->update($request->validated());

        return ApiResponse::success(TestimonialResource::make($testimonial)->resolve($request));
    }

    public function destroy(Testimonial $testimonial): JsonResponse
    {
        $testimonial->delete();

        return ApiResponse::success(null);
    }
}
