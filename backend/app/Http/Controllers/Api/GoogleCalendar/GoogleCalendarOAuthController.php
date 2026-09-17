<?php

namespace App\Http\Controllers\Api\GoogleCalendar;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendar\GoogleCalendarOAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleCalendarOAuthController extends Controller
{
    public function __construct(private readonly GoogleCalendarOAuthService $oauth) {}

    public function status(Request $request): JsonResponse
    {
        return ApiResponse::success($this->oauth->statusFor($request->user()));
    }

    public function connect(Request $request): JsonResponse
    {
        $result = $this->oauth->beginConnect($request->user());

        return ApiResponse::success($result);
    }

    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $this->oauth->handleCallback($data['code'], $data['state']);

        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
        $redirect = $frontend.'/workspace/settings/google-calendar?connected=1';

        if ($request->expectsJson()) {
            return ApiResponse::success(['connected' => true, 'redirect' => $redirect]);
        }

        return redirect()->away($redirect);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $this->oauth->disconnect($request->user());

        return ApiResponse::success(['connected' => false]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'meet_enabled' => ['sometimes', 'boolean'],
            'sync_enabled' => ['sometimes', 'boolean'],
            'calendar_id' => ['sometimes', 'string', 'max:128'],
        ]);

        $connection = $this->oauth->updateSettings($request->user(), $data);

        return ApiResponse::success($connection->publicStatus());
    }
}
