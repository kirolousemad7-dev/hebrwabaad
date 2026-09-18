<?php

namespace App\Http\Controllers\Api\Meetings;

use App\Enums\MeetingProvider;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Services\Meetings\MeetingService;
use App\Services\Meetings\VideoMeetingProviderManager;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingController extends Controller
{
    public function __construct(
        private readonly MeetingService $meetings,
        private readonly VideoMeetingProviderManager $providers,
    ) {}

    public function providers(): JsonResponse
    {
        return ApiResponse::success([
            'providers' => array_values($this->providers->availability()),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Meeting::class);
        $items = $this->meetings->listForActor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => $items->map(
                fn (Meeting $meeting): array => $this->meetings->serialize($meeting, $request->user())
            )->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Meeting::class);

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(MeetingProvider::values())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'timezone' => ['nullable', 'timezone'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'commercial_quotation_id' => ['nullable', 'integer', 'exists:commercial_quotations,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'include_customer' => ['sometimes', 'boolean'],
            'participant_ids' => ['nullable', 'array', 'max:50'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $meeting = $this->meetings->create($request->user(), $data);

        return ApiResponse::success($this->meetings->serialize($meeting, $request->user()), 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->meetings->assertCanViewMeeting($request->user(), $meeting);
        $meeting->load(['participants:id,name,email,role', 'creator:id,name,email', 'task:id,title', 'project:id,title', 'supplier:id,name,display_name']);

        return ApiResponse::success($this->meetings->serialize($meeting, $request->user()));
    }

    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'timezone' => ['nullable', 'timezone'],
            'include_customer' => ['sometimes', 'boolean'],
            'participant_ids' => ['nullable', 'array', 'max:50'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $updated = $this->meetings->update($request->user(), $meeting, $data);

        return ApiResponse::success($this->meetings->serialize($updated, $request->user()));
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('delete', $meeting);
        $cancelled = $this->meetings->cancel($request->user(), $meeting);

        return ApiResponse::success($this->meetings->serialize($cancelled, $request->user()));
    }
}
