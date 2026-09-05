<?php

namespace App\Http\Controllers\Api\Printing;

use App\Enums\PrintingRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Printing\StorePrintingRequestRequest;
use App\Http\Resources\PrintingRequestResource;
use App\Models\PrintingRequest;
use App\Services\PrintingPricingService;
use App\Support\ApiResponse;
use App\Support\PrintingRequestFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintingRequestController extends Controller
{
    public function __construct(private PrintingPricingService $pricing) {}

    public function index(Request $request): JsonResponse
    {
        $printingRequests = $request->user()
            ->printingRequests()
            ->latest()
            ->get();

        return ApiResponse::success(
            PrintingRequestResource::collection($printingRequests)->resolve($request)
        );
    }

    public function show(Request $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->authorize('view', $printingRequest);

        return ApiResponse::success(
            PrintingRequestResource::make($printingRequest)->resolve($request)
        );
    }

    public function file(Request $request, PrintingRequest $printingRequest): StreamedResponse
    {
        $this->authorize('download', $printingRequest);

        return PrintingRequestFile::download($printingRequest);
    }

    public function store(StorePrintingRequestRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return ApiResponse::error('Validation failed.', 422, [
                'file' => ['A design file is required.'],
            ]);
        }

        $printingRequest = DB::transaction(function () use ($request, $user, $file) {
            $path = $file->store('printing-requests/'.$user->id, 'local');

            $printingRequest = PrintingRequest::query()->create([
                'user_id' => $user->id,
                'product_slug' => $request->validated('product_slug'),
                'product_name' => $request->validated('product_name'),
                'width' => $request->validated('width'),
                'height' => $request->validated('height'),
                'dimension_unit' => $request->validated('dimension_unit'),
                'shape' => $request->validated('shape'),
                'material' => $request->validated('material'),
                'quantity' => $request->validated('quantity'),
                'printing_method' => $request->validated('printing_method'),
                'finishing' => array_values($request->validated('finishing')),
                'file_path' => $path,
                'original_filename' => $this->safeOriginalName($file),
                'required_date' => $request->validated('required_date'),
                'notes' => $request->validated('notes'),
                'status' => PrintingRequestStatus::Pending,
            ]);

            $this->pricing->applyInitialSuggestion($printingRequest);

            return $printingRequest->refresh();
        });

        return ApiResponse::success(
            PrintingRequestResource::make($printingRequest)->resolve($request),
            201
        );
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename($file->getClientOriginalName());

        return Str::limit($name === '' ? 'design-file' : $name, 255, '');
    }
}
