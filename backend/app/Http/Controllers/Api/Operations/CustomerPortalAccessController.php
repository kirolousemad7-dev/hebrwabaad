<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Customer\CustomerCommunicationService;
use App\Services\Customer\CustomerPortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPortalAccessController extends Controller
{
    public function __construct(
        private readonly CustomerPortalService $portal,
        private readonly CustomerCommunicationService $communications,
    ) {}

    public function store(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $expires = isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null;
        $result = $this->portal->createAccess($request->user(), $user, $expires);

        return ApiResponse::success([
            'id' => $result['access']->id,
            'token' => $result['raw_token'],
            'token_hint' => $result['access']->token_hint,
            'expires_at' => $result['access']->expires_at?->toIso8601String(),
            'portal_url' => $this->communications->frontendUrl('/portal/'.$result['raw_token']),
            'portal_path' => '/portal/'.$result['raw_token'],
        ], 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $revoked = $this->portal->revoke($request->user(), $user);

        return ApiResponse::success([
            'revoked' => $revoked,
        ]);
    }

    public function resendEmail(Request $request, User $user): JsonResponse
    {
        $result = $this->communications->resendPortalAccessEmail(
            $request->user(),
            $user,
            fn (User $customer) => $this->portal->createAccess($request->user(), $customer),
        );

        return ApiResponse::success([
            'status' => $result['status'],
            'portal_url' => $result['portal_url'],
            'mail_enabled' => $this->communications->mailEnabled(),
        ]);
    }
}
