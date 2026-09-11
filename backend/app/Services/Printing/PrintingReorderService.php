<?php

namespace App\Services\Printing;

use App\Enums\PrintingRequestStatus;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\PrintingPricingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrintingReorderService
{
    public function __construct(private PrintingPricingService $pricing) {}

    /**
     * Copy product specs into a fresh request. Never copies price, payment,
     * approval, or delivery state — pricing is recalculated.
     */
    public function reorder(User $user, PrintingRequest $source, ?int $quantity = null): PrintingRequest
    {
        if ((int) $source->user_id !== (int) $user->id) {
            throw new AuthorizationException('You may only reorder your own printing requests.');
        }

        if (! in_array($source->status, [PrintingRequestStatus::Completed, PrintingRequestStatus::ReadyForDelivery], true)) {
            throw ValidationException::withMessages([
                'printing_request' => ['Only completed or ready-for-delivery requests can be reordered.'],
            ]);
        }

        return DB::transaction(function () use ($user, $source, $quantity) {
            $request = PrintingRequest::query()->create([
                'user_id' => $user->id,
                'reordered_from_id' => $source->id,
                'product_slug' => $source->product_slug,
                'product_name' => $source->product_name,
                'width' => $source->width,
                'height' => $source->height,
                'dimension_unit' => $source->dimension_unit,
                'shape' => $source->shape,
                'material' => $source->material,
                'quantity' => $quantity ?? $source->quantity,
                'printing_method' => $source->printing_method,
                'finishing' => $source->finishing,
                'file_path' => $source->file_path,
                'original_filename' => $source->original_filename,
                'required_date' => $source->required_date,
                'notes' => $source->notes,
                'status' => PrintingRequestStatus::Pending,
            ]);

            $this->pricing->applyInitialSuggestion($request);

            return $request->refresh();
        });
    }
}
