<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsSettingsController extends Controller
{
    public function __construct(
        private readonly OperationsSettingsService $settings,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        return ApiResponse::success($this->settings->show($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'printing_approaching_days' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'default_business_calendar_id' => ['sometimes', 'nullable', 'integer', 'exists:business_calendars,id'],
            'quiet_hours_defaults' => ['sometimes', 'array'],
            'quiet_hours_defaults.enabled' => ['sometimes', 'boolean'],
            'quiet_hours_defaults.start' => ['sometimes', 'date_format:H:i'],
            'quiet_hours_defaults.end' => ['sometimes', 'date_format:H:i'],
            'quiet_hours_defaults.timezone' => ['sometimes', 'timezone'],
            'unified_work_driver' => ['sometimes', 'string'],
        ]);

        return ApiResponse::success($this->settings->update($request->user(), $data));
    }

    private function assertCanManage(Request $request): void
    {
        $user = $request->user();
        if (! ($user->role instanceof UserRole)
            || ! in_array($user->role, [UserRole::Owner, UserRole::AdminManager], true)
        ) {
            abort(403);
        }
    }
}
