<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationStatusHistory;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DonationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Donation::query()->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'donation_type' => ['required', Rule::in(['money', 'material'])],
            'status' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'is_monthly' => ['nullable', 'boolean'],
        ]);

        $record = DB::transaction(function () use ($validated) {
            $campaign = null;
            if (!empty($validated['campaign_id'])) {
                $campaign = Campaign::query()->findOrFail((int) $validated['campaign_id']);
            }

            $organizationId = (int) ($validated['organization_id'] ?? ($campaign?->organization_id ?? 0));
            if ($organizationId <= 0) {
                throw ValidationException::withMessages([
                    'organization_id' => 'A valid organization is required for this donation.',
                ]);
            }

            $status = strtolower((string) ($validated['status'] ?? 'completed'));

            $donation = Donation::create([
                'user_id' => (int) $validated['user_id'],
                'organization_id' => $organizationId,
                'campaign_id' => !empty($validated['campaign_id']) ? (int) $validated['campaign_id'] : null,
                'amount' => $validated['amount'],
                'donation_type' => $validated['donation_type'],
                'status' => $status,
            ]);

            DonationStatusHistory::create([
                'donation_id' => $donation->id,
                'old_status' => 'created',
                'new_status' => $status,
            ]);

            $payment = null;
            if ($validated['donation_type'] === 'money') {
                $paymentMethodName = trim((string) ($validated['payment_method'] ?? 'Credit Card'));
                $paymentMethod = PaymentMethod::query()->firstOrCreate([
                    'method_name' => $paymentMethodName,
                ]);

                $payment = Payment::create([
                    'donation_id' => $donation->id,
                    'payment_method_id' => $paymentMethod->id,
                    'transaction_reference' => $validated['transaction_reference'] ?? sprintf('CNY-%06d', $donation->id),
                    'amount' => $validated['amount'],
                    'payment_status' => $status,
                ]);
            }

            if ($campaign && $validated['donation_type'] === 'money' && $status === 'completed') {
                $campaign->increment('current_amount', (float) $validated['amount']);
                $campaign->refresh();
            }

            Notification::create([
                'user_id' => (int) $validated['user_id'],
                'message' => sprintf(
                    'Your donation of $%s to %s was successful. Thank you for your support!',
                    number_format((float) $validated['amount'], 2),
                    $campaign?->title ?? 'this campaign'
                ),
                'type' => 'donation-confirmed',
            ]);

            return [
                'donation' => $donation->fresh(),
                'payment' => $payment,
                'campaign' => $campaign,
            ];
        });

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = Donation::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = Donation::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = Donation::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
