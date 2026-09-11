<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Operations\OperationalAttentionService;
use App\Services\Operations\OperationsInsightsService;
use App\Services\Operations\OperationsSearchService;
use App\Services\Operations\TeamDashboardService;
use App\Services\Payments\PaymentInsightsService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Printing\PrintingRevenueService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationsUtilityController extends Controller
{
    public function __construct(
        private readonly OperationsSearchService $search,
        private readonly OperationsInsightsService $insights,
        private readonly TeamDashboardService $teamDashboard,
        private readonly OperationalAttentionService $attention,
        private readonly PrintingRevenueService $printingRevenue,
        private readonly PaymentProviderManager $paymentProviders,
        private readonly PaymentReconciliationService $reconciliation,
        private readonly PaymentInsightsService $paymentInsights,
    ) {}

    public function paymentCapabilities(): JsonResponse
    {
        return ApiResponse::success($this->paymentProviders->providers());
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:200'],
        ]);

        return ApiResponse::success($this->search->search($request->user(), $data['q']));
    }

    public function insights(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;
        if (! ($role instanceof UserRole) || ! ($role === UserRole::Owner || $role === UserRole::AdminManager || $role === UserRole::AccountManager)) {
            abort(403);
        }

        if ($request->filled('period')) {
            $data = $request->validate([
                'period' => ['required', 'integer', Rule::in([7, 30, 90])],
            ]);

            return ApiResponse::success($this->insights->forPeriod((int) $data['period']));
        }

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return ApiResponse::success($this->insights->report(
            Carbon::parse($data['from']),
            Carbon::parse($data['to']),
        ));
    }

    public function printingInsights(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'integer', Rule::in([7, 30, 90])],
        ]);

        return ApiResponse::success(
            $this->printingRevenue->insights($request->user(), (int) $data['period']),
        );
    }

    public function printingFunnel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'integer', Rule::in([7, 30, 90])],
        ]);

        return ApiResponse::success(
            $this->printingRevenue->printingFunnel($request->user(), (int) $data['period']),
        );
    }

    public function paymentInsights(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'integer', Rule::in([7, 30, 90])],
        ]);

        return ApiResponse::success(
            $this->paymentInsights->payments($request->user(), (int) $data['period']),
        );
    }

    public function paymentFunnel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'integer', Rule::in([7, 30, 90])],
        ]);

        return ApiResponse::success(
            $this->paymentInsights->paymentFunnel($request->user(), (int) $data['period']),
        );
    }

    public function paymentsReconciliation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success(
            $this->reconciliation->listNeedingAttention(
                $request->user(),
                (int) ($data['limit'] ?? 50),
            ),
        );
    }

    public function customerPrintingHistory(Request $request, User $user): JsonResponse
    {
        return ApiResponse::success(
            $this->printingRevenue->customerHistory($request->user(), $user),
        );
    }

    public function teamDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->teamDashboard->canAccess($user)) {
            abort(403);
        }

        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        return ApiResponse::success($this->teamDashboard->for(
            $user,
            isset($data['department_id']) ? (int) $data['department_id'] : null,
        ));
    }

    public function snoozeAttention(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attention_key' => ['required', 'string', 'max:190'],
            'until' => ['nullable', 'date'],
            'preset' => ['nullable', 'string', Rule::in(['1h', 'today', 'tomorrow'])],
        ]);

        return ApiResponse::success($this->attention->snooze($request->user(), $data));
    }
}
