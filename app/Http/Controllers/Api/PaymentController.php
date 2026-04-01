<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\KHQRService;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class PaymentController extends Controller
{

    protected KHQRService $khqrService;

    public function __construct(KHQRService $khqrService)
    {
        $this->khqrService = $khqrService;
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
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'in:USD,KHR',
            'bill_number' => 'nullable|string',
            'mobile_number' => 'nullable|string',
            'store_label' => 'nullable|string',
            'terminal_label' => 'nullable|string',
            'type' => 'in:individual,merchant',
        ]);

        $type = $validated['type'] ?? 'individual';

        try {
            if (blank(config('services.bakong.merchant.bakong_id'))) {
                Log::error('Bakong merchant configuration is missing', [
                    'config_key' => 'services.bakong.merchant.bakong_id',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Bakong merchant configuration is missing.',
                ], 500);
            }

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

            $md5 = (string) ($result['data']['md5'] ?? md5((string) $result['data']['qr']));
            $payment = null;
            $paymentPersistenceError = null;
            $expiresAt = now()->addMinutes(1);

            try {
                $payment = Payment::create([
                    'md5' => $md5,
                    'qr_code' => $result['data']['qr'],
                    'amount' => $validated['amount'],
                    'currency' => $validated['currency'] ?? 'USD',
                    'bill_number' => $validated['bill_number'] ?? null,
                    'mobile_number' => $validated['mobile_number'] ?? null,
                    'store_label' => $validated['store_label'] ?? null,
                    'terminal_label' => $validated['terminal_label'] ?? null,
                    'merchant_name' => config('services.bakong.merchant.name'),
                    'expires_at' => $expiresAt,
                ]);

                Log::info('Payment created', [
                    'payment_id' => $payment->id,
                    'md5' => $md5,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ]);
            } catch (QueryException $e) {
                $paymentPersistenceError = $e->getMessage();

                Log::error('Payment persistence failed after QR generation', [
                    'message' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'qr_code' => $result['data']['qr'],
                'md5' => $md5,
                'payment_id' => $payment?->id,
                'expires_at' => ($payment?->expires_at ?? $expiresAt)->toISOString(),
                'payment_persisted' => $payment !== null,
                'message' => $payment === null
                    ? 'QR generated successfully, but payment persistence failed.'
                    : 'QR generated successfully',
                'warning' => $paymentPersistenceError ? 'Payment persistence failed on the server.' : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Exception in generateQR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the QR code.',
            ], 500);
        }
    }
}
