<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PayTabsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayTabsReturnController extends Controller
{
    public function __construct(private readonly PayTabsClient $paytabs) {}

    public function handle(Request $request): RedirectResponse
    {
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $payment = $this->findPayment($request);

        if ($payment === null) {
            return redirect()->away($frontend.'/dashboard/orders');
        }

        return redirect()->away(
            $frontend.'/dashboard/orders/'.$payment->order_id.'/pay?payment='.$payment->id.'&checkout=return'
        );
    }

    private function findPayment(Request $request): ?Payment
    {
        $tranRef = trim((string) $request->input('tran_ref', ''));
        if ($tranRef !== '') {
            $byRef = Payment::query()->where('provider_transaction_id', $tranRef)->first();
            if ($byRef !== null) {
                return $byRef;
            }
        }

        $cartId = trim((string) $request->input('cart_id', ''));
        if ($cartId !== '') {
            $byCart = Payment::query()->where('checkout_session_id', $cartId)->first();
            if ($byCart !== null) {
                return $byCart;
            }

            $paymentId = $this->paytabs->paymentIdFromCartId($cartId);
            if ($paymentId !== null) {
                return Payment::query()->find($paymentId);
            }
        }

        $paymentId = (int) $request->query('payment', 0);

        return $paymentId > 0 ? Payment::query()->find($paymentId) : null;
    }
}
