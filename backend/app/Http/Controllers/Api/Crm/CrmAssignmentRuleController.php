<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmAssignmentRule;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmAssignmentRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageCrmSettings() === true, 403);

        return ApiResponse::success([
            'items' => CrmAssignmentRule::query()
                ->with('assignee:id,name,email')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageCrmSettings() === true, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'match_type' => ['required', Rule::in(['source', 'service'])],
            'match_value' => ['required', 'string', 'max:120'],
            'assign_to_user_id' => ['required', 'integer', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $rule = CrmAssignmentRule::query()->create([
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
            'match_type' => $data['match_type'],
            'match_value' => $data['match_value'],
            'assign_to_user_id' => $data['assign_to_user_id'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return ApiResponse::success($rule->load('assignee:id,name,email'), 201);
    }

    public function update(Request $request, CrmAssignmentRule $assignmentRule): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageCrmSettings() === true, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'match_type' => ['sometimes', Rule::in(['source', 'service'])],
            'match_value' => ['sometimes', 'string', 'max:120'],
            'assign_to_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $assignmentRule->update($data);

        return ApiResponse::success($assignmentRule->fresh()->load('assignee:id,name,email'));
    }

    public function destroy(Request $request, CrmAssignmentRule $assignmentRule): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageCrmSettings() === true, 403);
        $assignmentRule->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
