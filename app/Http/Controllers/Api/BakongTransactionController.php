<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationStatusHistory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BakongTransactionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $payload = DB::transaction(function () use ($validated) {
            $campaign = Campaign::query()->findOrFail((int) $validated['campaign_id']);
            $organizationId = (int) ($validated['organization_id'] ?? $campaign->organization_id ?? 0);
            $amount = (float) $validated['amount'];
            $tranId = sprintf('BKG-%s', strtoupper(bin2hex(random_bytes(4))));
            $environment = (string) config('services.aba_payway.environment', 'sandbox');
            $currency = (string) config('services.bakong.currency', 'USD');

            $donation = Donation::create([
                'user_id' => (int) $validated['user_id'],
                'organization_id' => $organizationId,
                'campaign_id' => (int) $campaign->id,
                'amount' => $amount,
                'donation_type' => 'money',
                'status' => 'pending',
            ]);

            DonationStatusHistory::create([
                'donation_id' => $donation->id,
                'old_status' => 'created',
                'new_status' => 'pending',
            ]);

            $paymentMethod = PaymentMethod::query()->firstOrCreate([
                'method_name' => 'Bakong KHQR',
            ]);

            $payment = Payment::create([
                'donation_id' => $donation->id,
                'payment_method_id' => $paymentMethod->id,
                'transaction_reference' => $tranId,
                'amount' => $amount,
                'payment_status' => 'pending',
            ]);

            $transaction = Transaction::create([
                'user_id' => (int) $validated['user_id'],
                'organization_id' => $organizationId > 0 ? $organizationId : null,
                'campaign_id' => (int) $campaign->id,
                'donation_id' => $donation->id,
                'payment_id' => $payment->id,
                'tran_id' => $tranId,
                'amount' => $amount,
                'currency' => $currency,
                'payment_option' => (string) config('services.aba_payway.payment_option', 'abapay_khqr'),
                'status' => 'pending',
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'checkout_url' => null,
                'qr_string' => (string) config('services.bakong.static_qr_string', ''),
                'deeplink' => (string) config('services.bakong.static_qr_deeplink', ''),
                'request_payload' => $validated,
                'response_payload' => [
                    'static_qr_image' => (string) config('services.bakong.static_qr_image', ''),
                    'static_qr_name' => (string) config('services.bakong.static_qr_name', 'Bakong KHQR'),
                ],
            ]);

            return [
                'donation' => $donation,
                'payment' => $payment,
                'transaction' => $transaction,
                'campaign' => $campaign,
                'environment' => $environment,
            ];
        });

        return response()->json([
            'donation' => $payload['donation'],
            'payment' => $payload['payment'],
            'transaction' => [
                'id' => $payload['transaction']->id,
                'tran_id' => $payload['transaction']->tran_id,
                'status' => $payload['transaction']->status,
                'amount' => (float) $payload['transaction']->amount,
                'currency' => $payload['transaction']->currency,
            ],
            'checkout' => [
                'meta' => [
                    'environment' => $payload['environment'],
                    'payment_option' => $payload['transaction']->payment_option,
                    'payment_label' => 'Bakong KHQR',
                ],
                'qr' => [
                    'image' => (string) config('services.bakong.static_qr_image', ''),
                    'string' => (string) config('services.bakong.static_qr_string', ''),
                    'deeplink' => (string) config('services.bakong.static_qr_deeplink', ''),
                    'checkout_url' => $payload['transaction']->checkout_url,
                    'app_store' => '',
                    'play_store' => '',
                    'amount' => (float) $payload['transaction']->amount,
                    'currency' => $payload['transaction']->currency,
                ],
            ],
        ], 201);
    }

    public function verify(string $tranId): JsonResponse
    {
        $transaction = Transaction::query()->where('tran_id', $tranId)->firstOrFail();
        $environment = (string) config('services.aba_payway.environment', 'sandbox');

        DB::transaction(function () use ($transaction, $environment) {
            if ($transaction->status !== 'pending') {
                return;
            }

            if ($environment !== 'sandbox') {
                return;
            }

            $transaction->update([
                'status' => 'completed',
                'paid_at' => Carbon::now(),
                'callback_payload' => ['verified_in' => 'sandbox'],
            ]);

            $payment = $transaction->payment_id ? Payment::query()->find($transaction->payment_id) : null;
            if ($payment) {
                $payment->update([
                    'payment_status' => 'completed',
                    'transaction_reference' => $transaction->tran_id,
                ]);
            }

            $donation = $transaction->donation_id ? Donation::query()->find($transaction->donation_id) : null;
            if ($donation && $donation->status !== 'completed') {
                $oldStatus = $donation->status ?: 'pending';
                $donation->update(['status' => 'completed']);
                DonationStatusHistory::create([
                    'donation_id' => $donation->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'completed',
                ]);

                if ($donation->campaign_id) {
                    $campaign = Campaign::query()->find($donation->campaign_id);
                    if ($campaign) {
                        $campaign->increment('current_amount', (float) $donation->amount);
                    }
                }
            }
        });

        $freshTransaction = Transaction::query()->where('tran_id', $tranId)->firstOrFail();

        return response()->json([
            'transaction' => [
                'id' => $freshTransaction->id,
                'tran_id' => $freshTransaction->tran_id,
                'status' => $freshTransaction->status,
                'amount' => (float) $freshTransaction->amount,
                'currency' => $freshTransaction->currency,
                'donation_id' => $freshTransaction->donation_id,
                'paid_at' => optional($freshTransaction->paid_at)->toIso8601String(),
            ],
        ]);
    }
}
