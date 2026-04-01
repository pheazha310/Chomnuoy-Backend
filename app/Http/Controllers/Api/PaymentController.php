<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationStatusHistory;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\KHQRService;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;

class PaymentController extends Controller
{
    private const PAYMENT_EXPIRY_MINUTES = 5;
    private const MIN_USD_AMOUNT = 0.001;
    
    protected KHQRService $khqrService;

    public function __construct(KHQRService $khqrService)
    {
        $this->khqrService = $khqrService;
    }

    private function syncPaymentStatus(Payment $payment): Payment
    {
        if ($payment->status === 'SUCCESS') {
            return $this->ensureDonationRecorded($payment);
        }

        if ($payment->isExpired()) {
            $payment->markAsExpired();
            return $payment->fresh();
        }

        $result = $this->khqrService->checkPayment($payment->md5);
        $payment->incrementCheckAttempts();

        Log::info('Payment sync check', [
            'payment_id' => $payment->id,
            'md5' => $payment->md5,
            'bakong_response' => $result,
        ]);

        $responseCode = $result['responseCode'] ?? -1;
        $isSuccess = $responseCode === 0;

        if ($isSuccess) {
            $txInfo = $this->khqrService->getPayment($payment->md5);
            $transactionId = $txInfo['data']['hash'] ??
                $result['data']['hash'] ??
                null;

            $payment->markAsSuccess($result, $transactionId);
            $payment = $this->ensureDonationRecorded($payment->fresh());

            // Send notifications to organization and admin roles (not donor)
            $updatedPayment = $payment->fresh();
            if ($updatedPayment->status === 'SUCCESS') {
                $recipientRoles = ['Organization', 'Admin'];
                $usersToNotify = User::whereIn('role_id', Role::whereIn('role_name', $recipientRoles)->pluck('id'))->get();

                foreach ($usersToNotify as $recipient) {
                    Notification::create([
                        'user_id' => $recipient->id,
                        'message' => sprintf('A payment of $%s has been completed and needs your review.', number_format($updatedPayment->amount, 2)),
                        'type' => 'payment-received',
                    ]);
                }
            }
        }

        return $payment->fresh();
    }

    public function index(): JsonResponse
    {
        return response()->json(Payment::query()->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $record = Payment::create($request->all());

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = Payment::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = Payment::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = Payment::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }


    /**
     * Generate KHQR for payment
     */
    public function generateQR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:' . self::MIN_USD_AMOUNT,
            'currency' => 'in:USD,KHR',
            'user_id' => 'nullable|integer|exists:users,id',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'bill_number' => 'nullable|string',
            'mobile_number' => 'nullable|string',
            'store_label' => 'nullable|string',
            'terminal_label' => 'nullable|string',
            'type' => 'in:individual,merchant',
        ]);

        $type = $validated['type'] ?? 'individual';

        try {
            $result = $type === 'merchant'
                ? $this->khqrService->generateMerchantQR($validated)
                : $this->khqrService->generateIndividualQR($validated);

            if (isset($result['error'])) {
                Log::error('QR generation error', ['error' => $result['error']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate QR: ' . $result['error'],
                ], 400);
            }

            if (!isset($result['data'])) {
                Log::error('Invalid QR service response', ['result' => $result]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid response from QR service',
                ], 500);
            }

            $paymentMethod = PaymentMethod::query()->firstOrCreate([
                'method_name' => 'Bakong KHQR',
            ]);

            $campaign = !empty($validated['campaign_id'])
                ? Campaign::query()->find($validated['campaign_id'])
                : null;
            $organizationId = $validated['organization_id'] ?? $campaign?->organization_id;
            $transactionReference = json_encode(array_filter([
                'source' => 'qr_checkout',
                'user_id' => $validated['user_id'] ?? null,
                'organization_id' => $organizationId,
                'campaign_id' => $validated['campaign_id'] ?? null,
                'donation_type' => 'money',
            ], fn ($value) => $value !== null));

            // Older databases may not yet have all payment linkage columns.
            $paymentData = [
                'user_id' => $validated['user_id'] ?? null,
                'md5' => $result['data']['md5'],
                'qr_code' => $result['data']['qr'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? 'USD',
                'bill_number' => $validated['bill_number'] ?? null,
                'mobile_number' => $validated['mobile_number'] ?? null,
                'store_label' => $validated['store_label'] ?? null,
                'terminal_label' => $validated['terminal_label'] ?? null,
                'merchant_name' => config('services.bakong.merchant.name'),
                'expires_at' => now()->addMinutes(self::PAYMENT_EXPIRY_MINUTES),
            ];

            if (Schema::hasColumn('payments', 'donation_id')) {
                $paymentData['donation_id'] = null;
            }

            if (Schema::hasColumn('payments', 'payment_method_id')) {
                $paymentData['payment_method_id'] = $paymentMethod->id;
            }

            if (Schema::hasColumn('payments', 'transaction_reference')) {
                $paymentData['transaction_reference'] = $transactionReference;
            }

            if (Schema::hasColumn('payments', 'payment_status')) {
                $paymentData['payment_status'] = 'pending';
            }

            $payment = Payment::create($paymentData);

            Log::info('Payment created', [
                'payment_id' => $payment->id,
                'md5' => $payment->md5,
                'amount' => $payment->amount,
                'currency' => $payment->currency
            ]);

            return response()->json([
                'success' => true,
                'qr_code' => $result['data']['qr'],
                'md5' => $result['data']['md5'],
                'payment_id' => $payment->id,
                'expires_at' => $payment->expires_at->toISOString(),
                'message' => 'QR generated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Exception in generateQR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check payment status
     */
    public function checkPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'md5' => 'required|string',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $payment = Payment::where('md5', $validated['md5'])->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        $payment = $this->syncPaymentStatus($payment);
        $payment = $this->attachUserIfMissing($payment, $validated['user_id'] ?? null);

        if ($payment->status === 'SUCCESS') {
            return response()->json([
                'success' => true,
                'status' => 'SUCCESS',
                'message' => 'Payment already completed!',
                'data' => [
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at,
                    'transaction_id' => $payment->transaction_id,
                ],
            ]);
        }

        if ($payment->status === 'EXPIRED') {
            return response()->json([
                'success' => false,
                'status' => 'EXPIRED',
                'message' => 'Payment has expired',
            ]);
        }

        return response()->json([
            'success' => false,
            'status'  => 'PENDING',
            'message' => 'Payment not yet completed',
            'data'    => [
                'check_attempts'  => $payment->check_attempts,
                'last_checked_at' => $payment->last_checked_at,
                'expires_at'      => $payment->expires_at,
            ],
        ]);
    }

    /**
     * Get payment status by ID
     */
    public function getPaymentStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => 'nullable|integer|exists:payments,id',
            'md5' => 'nullable|string|exists:payments,md5',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if (!isset($validated['payment_id']) && !isset($validated['md5'])) {
            return response()->json([
                'success' => false,
                'message' => 'Either payment_id or md5 is required.',
                'errors' => [
                    'payment_id' => ['Either payment_id or md5 is required.'],
                    'md5' => ['Either payment_id or md5 is required.'],
                ],
            ], 422);
        }

        $payment = isset($validated['payment_id'])
            ? Payment::findOrFail($validated['payment_id'])
            : Payment::where('md5', $validated['md5'])->firstOrFail();

        $payment = $this->syncPaymentStatus($payment);
        $payment = $this->attachUserIfMissing($payment, $validated['user_id'] ?? null);

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'user_id' => $payment->user_id,
                'md5' => $payment->md5,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'created_at' => $payment->created_at,
                'expires_at' => $payment->expires_at,
                'paid_at' => $payment->paid_at,
                'check_attempts' => $payment->check_attempts,
                'last_checked_at' => $payment->last_checked_at,
                'telegram_sent' => $payment->telegram_sent,
            ],
        ]);
    }

    /**
     * Verify QR code
     */
    public function verifyQR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $result = $this->khqrService->verifyQR($validated['qr_code']);

        return response()->json($result);
    }

    /**
     * Decode QR code
     */
    public function decodeQR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $result = $this->khqrService->decodeQR($validated['qr_code']);

        return response()->json($result);
    }

    /**
     * Generate deep link
     */
    public function generateDeepLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $result = $this->khqrService->generateDeepLink($validated['qr_code']);

        return response()->json($result);
    }

    private function attachUserIfMissing(Payment $payment, ?int $userId = null): Payment
    {
        if (!$userId || $payment->user_id) {
            return $payment;
        }

        $payment->update(['user_id' => $userId]);

        return $payment->fresh();
    }

    private function ensureDonationRecorded(Payment $payment): Payment
    {
        if ($payment->status !== 'SUCCESS') {
            return $payment;
        }

        if ($payment->donation_id) {
            return $payment;
        }

        $context = $this->resolveDonationContext($payment);
        $userId = $payment->user_id ?: ($context['user_id'] ?? null);
        $organizationId = $context['organization_id'] ?? null;
        $campaignId = $context['campaign_id'] ?? null;

        if (!$userId || !$organizationId) {
            return $payment;
        }

        $existingDonation = Donation::query()
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->where('campaign_id', $campaignId)
            ->where('amount', $payment->amount)
            ->where(function ($query) use ($payment) {
                $query->where('created_at', '>=', $payment->created_at?->copy()->subMinutes(10))
                    ->orWhere('id', $payment->donation_id);
            })
            ->latest('id')
            ->first();

        $donation = $existingDonation ?: Donation::create([
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'campaign_id' => $campaignId,
            'amount' => $payment->amount,
            'donation_type' => 'money',
            'status' => 'completed',
        ]);

        if (!$existingDonation) {
            DonationStatusHistory::create([
                'donation_id' => $donation->id,
                'old_status' => 'created',
                'new_status' => 'completed',
            ]);
        }

        $updates = ['donation_id' => $donation->id];

        if (Schema::hasColumn('payments', 'payment_status')) {
            $updates['payment_status'] = 'completed';
        }

        $payment->update($updates);

        return $payment->fresh();
    }

    private function resolveDonationContext(Payment $payment): array
    {
        $context = [];

        if (is_string($payment->transaction_reference) && $payment->transaction_reference !== '') {
            $decoded = json_decode($payment->transaction_reference, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $context = $decoded;
            }
        }

        if (!isset($context['campaign_id']) && preg_match('/^DON-(\d+)-/i', (string) $payment->bill_number, $matches) === 1) {
            $context['campaign_id'] = (int) $matches[1];
        }

        if (!isset($context['organization_id']) && !empty($context['campaign_id'])) {
            $campaign = Campaign::query()->find((int) $context['campaign_id']);
            if ($campaign) {
                $context['organization_id'] = (int) $campaign->organization_id;
            }
        }

        return $context;
    }


}
