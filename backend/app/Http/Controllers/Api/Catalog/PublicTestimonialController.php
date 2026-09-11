<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicTestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Testimonial::query()->published()->get();

        return ApiResponse::success(TestimonialResource::collection($items)->resolve($request));
    }
}
