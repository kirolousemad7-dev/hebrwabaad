<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactInquiryResource;
use App\Models\ContactInquiry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ContactInquiryAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $items = ContactInquiry::query()->latest()->limit(100)->get();

        return ApiResponse::success(ContactInquiryResource::collection($items)->resolve());
    }
}
