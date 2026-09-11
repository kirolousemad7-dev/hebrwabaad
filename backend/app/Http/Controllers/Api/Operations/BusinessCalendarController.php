<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use App\Services\Operations\BusinessCalendarService;
use App\Services\Operations\BusinessTimeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessCalendarController extends Controller
{
    public function __construct(
        private readonly BusinessCalendarService $calendars,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        return ApiResponse::success([
            'items' => $this->calendars->list(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'week_start' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'working_days' => ['sometimes', 'array', 'min:1'],
            'working_days.*' => ['integer', 'min:0', 'max:6'],
            'work_start' => ['sometimes', 'date_format:H:i:s'],
            'work_end' => ['sometimes', 'date_format:H:i:s', 'after:work_start'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
        ]);

        $calendar = $this->calendars->create($request->user(), $data);

        return ApiResponse::success($this->calendars->serialize($calendar), 201);
    }

    public function show(Request $request, BusinessCalendar $businessCalendar): JsonResponse
    {
        $this->assertCanManage($request);

        return ApiResponse::success($this->calendars->serialize($businessCalendar, true));
    }

    public function update(Request $request, BusinessCalendar $businessCalendar): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'week_start' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'working_days' => ['sometimes', 'array', 'min:1'],
            'working_days.*' => ['integer', 'min:0', 'max:6'],
            'work_start' => ['sometimes', 'date_format:H:i:s'],
            'work_end' => ['sometimes', 'date_format:H:i:s'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
        ]);

        $calendar = $this->calendars->update($request->user(), $businessCalendar, $data);

        return ApiResponse::success($this->calendars->serialize($calendar, true));
    }

    public function destroy(Request $request, BusinessCalendar $businessCalendar): JsonResponse
    {
        $this->assertCanManage($request);
        $this->calendars->delete($request->user(), $businessCalendar);

        return ApiResponse::success(['deleted' => true]);
    }

    public function storeHoliday(Request $request, BusinessCalendar $businessCalendar): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'date' => [
                'required',
                'date',
                Rule::unique('business_calendar_holidays', 'date')
                    ->where(fn ($q) => $q->where('business_calendar_id', $businessCalendar->id)),
            ],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $holiday = $this->calendars->addHoliday($request->user(), $businessCalendar, $data);

        return ApiResponse::success([
            'id' => $holiday->id,
            'date' => $holiday->date?->toDateString(),
            'name' => $holiday->name,
        ], 201);
    }

    public function destroyHoliday(
        Request $request,
        BusinessCalendar $businessCalendar,
        BusinessCalendarHoliday $holiday,
    ): JsonResponse {
        $this->assertCanManage($request);
        $this->calendars->removeHoliday($request->user(), $businessCalendar, $holiday);

        return ApiResponse::success(['deleted' => true]);
    }

    public function ensureDefault(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        $calendar = app(BusinessTimeService::class)->defaultCalendar();

        return ApiResponse::success($this->calendars->serialize($calendar->loadCount('holidays'), true));
    }

    public function importHolidays(Request $request, BusinessCalendar $businessCalendar): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'preview' => ['sometimes', 'boolean'],
            'csv' => ['required_without:file', 'nullable', 'string', 'max:500000'],
            'file' => ['required_without:csv', 'nullable', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);

        $csv = (string) ($data['csv'] ?? '');
        if ($request->hasFile('file')) {
            $csv = (string) file_get_contents($request->file('file')->getRealPath());
        }

        if (trim($csv) === '') {
            return ApiResponse::error('CSV content is required.', 422);
        }

        $preview = (bool) ($data['preview'] ?? false);

        return ApiResponse::success(
            $this->calendars->importHolidays($request->user(), $businessCalendar, $csv, $preview),
            $preview ? 200 : 201,
        );
    }

    public function copyYear(Request $request, BusinessCalendar $businessCalendar): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'from_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'to_year' => ['required', 'integer', 'min:2000', 'max:2100', 'different:from_year'],
        ]);

        return ApiResponse::success(
            $this->calendars->copyYear(
                $request->user(),
                $businessCalendar,
                (int) $data['from_year'],
                (int) $data['to_year'],
            ),
        );
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
