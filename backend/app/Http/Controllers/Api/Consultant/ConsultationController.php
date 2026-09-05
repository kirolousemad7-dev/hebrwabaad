<?php

namespace App\Http\Controllers\Api\Consultant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\AnswerConsultationRequest;
use App\Http\Requests\Consultant\MessageConsultationRequest;
use App\Http\Requests\Consultant\StoreConsultationEventRequest;
use App\Http\Requests\Consultant\StoreConsultationLeadRequest;
use App\Models\Consultation;
use App\Models\User;
use App\Services\ConsultationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function __construct(
        private readonly ConsultationService $consultations,
    ) {}

    public function config(): JsonResponse
    {
        return ApiResponse::success($this->consultations->publicConfig());
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->consultations->enabled()) {
            return ApiResponse::error('مستشار حبر غير متاح حالياً.', 503);
        }

        $consultation = $this->consultations->start($this->optionalUser($request));

        return ApiResponse::success($this->consultations->present($consultation), 201);
    }

    public function show(string $consultation): JsonResponse
    {
        $model = $this->consultations->findByToken($consultation);

        return ApiResponse::success($this->consultations->present($model));
    }

    public function answer(AnswerConsultationRequest $request, string $consultation): JsonResponse
    {
        if (! $this->consultations->enabled()) {
            return ApiResponse::error('مستشار حبر غير متاح حالياً.', 503);
        }

        $model = $this->attachUser($this->consultations->findByToken($consultation), $request);
        $updated = $this->consultations->answer(
            $model,
            (string) $request->validated('question_id'),
            $request->validated('value'),
        );

        return ApiResponse::success($this->consultations->present($updated));
    }

    public function message(MessageConsultationRequest $request, string $consultation): JsonResponse
    {
        if (! $this->consultations->enabled()) {
            return ApiResponse::error('مستشار حبر غير متاح حالياً.', 503);
        }

        $model = $this->attachUser($this->consultations->findByToken($consultation), $request);
        $updated = $this->consultations->message($model, (string) $request->validated('message'));

        return ApiResponse::success($this->consultations->present($updated));
    }

    public function reset(Request $request, string $consultation): JsonResponse
    {
        if (! $this->consultations->enabled()) {
            return ApiResponse::error('مستشار حبر غير متاح حالياً.', 503);
        }

        $model = $this->consultations->findByToken($consultation);
        $fresh = $this->consultations->reset($model);

        return ApiResponse::success($this->consultations->present($fresh));
    }

    public function lead(StoreConsultationLeadRequest $request, string $consultation): JsonResponse
    {
        $model = $this->attachUser($this->consultations->findByToken($consultation), $request);
        $lead = $this->consultations->captureLead($model, $request->validated());

        return ApiResponse::success([
            'lead_captured' => true,
            'id' => $lead->id,
        ], 201);
    }

    public function event(StoreConsultationEventRequest $request, string $consultation): JsonResponse
    {
        $model = $this->consultations->findByToken($consultation);
        $this->consultations->recordEvent(
            $model,
            (string) $request->validated('name'),
            $request->validated('payload') ?? null,
        );

        return ApiResponse::success(['recorded' => true]);
    }

    private function optionalUser(Request $request): ?User
    {
        $user = auth('sanctum')->user();

        return $user instanceof User ? $user : null;
    }

    private function attachUser(Consultation $consultation, Request $request): Consultation
    {
        $user = $this->optionalUser($request);

        if ($user && $consultation->user_id === null) {
            $consultation->user_id = $user->id;
            $consultation->save();
        }

        return $consultation;
    }
}
