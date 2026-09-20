<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleAuthService $google) {}

    public function status(): JsonResponse
    {
        return ApiResponse::success([
            'configured' => $this->google->isConfigured(),
            'redirect_uri' => $this->google->isConfigured() ? $this->google->redirectUri() : null,
        ]);
    }

    public function redirect(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $started = $this->google->begin(
                (string) $request->query('intent', GoogleAuthService::INTENT_LOGIN),
                $request->query('next'),
            );
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return redirect()->away($this->frontendErrorUrl('not_configured'));
        }

        return redirect()->away($started['authorize_url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $error = $request->query('error');
        if (is_string($error) && $error !== '') {
            return redirect()->away($this->frontendErrorUrl($error === 'access_denied' ? 'cancelled' : 'oauth_failed'));
        }

        $code = $request->query('code');
        $state = $request->query('state');

        if (! is_string($code) || $code === '' || ! is_string($state) || $state === '') {
            return redirect()->away($this->frontendErrorUrl('oauth_failed'));
        }

        try {
            $result = $this->google->handleCallback($code, $state);
        } catch (ValidationException $exception) {
            $messages = $exception->errors();
            $key = array_key_first($messages) ?: 'oauth_failed';
            $codeKey = match ($key) {
                'account' => 'account_deactivated',
                'privilege' => 'account_blocked',
                'state' => 'invalid_state',
                'google' => 'oauth_failed',
                default => 'oauth_failed',
            };

            return redirect()->away($this->frontendErrorUrl($codeKey));
        }

        $query = http_build_query(array_filter([
            'code' => $result['exchange_code'],
            'next' => $result['next'],
        ], fn ($value) => $value !== null && $value !== ''));

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/auth/google/callback?'.$query);
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:20', 'max:128'],
        ]);

        $payload = $this->google->exchange($validated['code']);

        return ApiResponse::success($payload);
    }

    private function frontendErrorUrl(string $error): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/login?google_error='.urlencode($error);
    }
}
