<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\OperationalSlaRule;
use App\Services\Operations\SlaEvaluationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationalSlaRuleController extends Controller
{
    public function __construct(
        private readonly SlaEvaluationService $sla,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        return ApiResponse::success([
            'items' => $this->sla->listRules(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'module' => ['required', 'string', Rule::in(SlaEvaluationService::modules())],
            'event_type' => ['required', 'string', Rule::in(SlaEvaluationService::eventTypes())],
            'target_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'business_calendar_id' => ['nullable', 'integer', 'exists:business_calendars,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = $this->sla->createRule($request->user(), $data);

        return ApiResponse::success($this->sla->serializeRule($rule), 201);
    }

    public function update(Request $request, OperationalSlaRule $slaRule): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'module' => ['sometimes', 'string', Rule::in(SlaEvaluationService::modules())],
            'event_type' => ['sometimes', 'string', Rule::in(SlaEvaluationService::eventTypes())],
            'target_minutes' => ['sometimes', 'integer', 'min:1', 'max:525600'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'business_calendar_id' => ['nullable', 'integer', 'exists:business_calendars,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = $this->sla->updateRule($request->user(), $slaRule, $data);

        return ApiResponse::success($this->sla->serializeRule($rule));
    }

    public function destroy(Request $request, OperationalSlaRule $slaRule): JsonResponse
    {
        $this->assertCanManage($request);

        $this->sla->deleteRule($request->user(), $slaRule);

        return ApiResponse::success(['deleted' => true]);
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
