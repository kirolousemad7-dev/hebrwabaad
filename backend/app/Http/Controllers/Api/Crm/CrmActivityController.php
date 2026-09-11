<?php

namespace App\Http\Controllers\Api\Crm;

use App\Enums\CrmActivityType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmActivityRequest;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\User;
use App\Services\Crm\CrmLeadService;
use App\Services\PlatformNotifier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmActivityController extends Controller
{
    public function __construct(
        private readonly CrmLeadService $leads,
        private readonly PlatformNotifier $notifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = CrmActivity::query()
            ->with(['user:id,name,email', 'lead:id,reference,full_name,assigned_to'])
            ->latest('occurred_at');

        if ($request->filled('lead_id')) {
            $lead = CrmLead::query()->findOrFail((int) $request->query('lead_id'));
            $this->leads->assertVisible($request->user(), $lead);
            $query->where('lead_id', $lead->id);
        }

        $page = $query->paginate(max(1, min((int) $request->query('per_page', 15), 50)));

        return ApiResponse::success([
            'items' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreCrmActivityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $lead = null;

        if (! empty($data['lead_id'])) {
            $lead = CrmLead::query()->findOrFail((int) $data['lead_id']);
            $this->leads->assertVisible($request->user(), $lead);
        }

        $activity = CrmActivity::query()->create([
            'lead_id' => $data['lead_id'] ?? null,
            'opportunity_id' => $data['opportunity_id'] ?? null,
            'user_id' => $request->user()->id,
            'type' => $data['type'] ?? CrmActivityType::Note->value,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'call_result' => $data['call_result'] ?? null,
            'result' => $data['result'] ?? null,
            'notes' => $data['notes'] ?? null,
            'next_action' => $data['next_action'] ?? null,
        ]);

        $type = $activity->type instanceof CrmActivityType
            ? $activity->type
            : CrmActivityType::tryFrom((string) $activity->type);

        if ($lead !== null && in_array($type, [
            CrmActivityType::Call,
            CrmActivityType::WhatsApp,
            CrmActivityType::Email,
            CrmActivityType::Meeting,
            CrmActivityType::VideoMeeting,
        ], true)) {
            $updates = [
                'last_contacted_at' => now(),
                'needs_attention' => false,
            ];
            if ($lead->first_contacted_at === null) {
                $updates['first_contacted_at'] = now();
            }
            $lead->update($updates);
        } elseif ($lead !== null) {
            $lead->update(['last_contacted_at' => now()]);
        }

        $this->notifyMentions($request->user(), $lead, (string) ($data['notes'] ?? ''));

        return ApiResponse::success($activity->load(['user:id,name,email']), 201);
    }

    private function notifyMentions(User $actor, ?CrmLead $lead, string $notes): void
    {
        if ($lead === null || $notes === '' || ! preg_match_all('/@([\\p{L}\\p{N}_.\\- ]+)/u', $notes, $matches)) {
            return;
        }

        $names = array_unique(array_map('trim', $matches[1]));
        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }

            $users = User::query()
                ->active()
                ->whereIn('role', [
                    UserRole::Owner->value,
                    UserRole::AdminManager->value,
                    UserRole::SalesManager->value,
                    UserRole::SalesRepresentative->value,
                ])
                ->where('name', 'like', $name.'%')
                ->limit(5)
                ->get();

            foreach ($users as $user) {
                $this->notifier->crmMention($user, $actor, $lead, mb_substr($notes, 0, 160));
            }
        }
    }
}
