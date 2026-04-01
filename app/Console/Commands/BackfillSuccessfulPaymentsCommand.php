<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationStatusHistory;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillSuccessfulPaymentsCommand extends Command
{
    protected $signature = 'payments:backfill-donations {--dry-run : Show what would be created without writing changes}';

    protected $description = 'Backfill donation records from already successful QR payments.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $payments = Payment::query()
            ->where('status', 'SUCCESS')
            ->where(function ($query) {
                if (Schema::hasColumn('payments', 'donation_id')) {
                    $query->whereNull('donation_id');
                }
            })
            ->orderBy('id')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($payments as $payment) {
            $context = $this->resolveDonationContext($payment);
            $userId = $payment->user_id ?: ($context['user_id'] ?? null);
            $organizationId = $context['organization_id'] ?? null;
            $campaignId = $context['campaign_id'] ?? null;

            if (!$userId || !$organizationId) {
                $skipped++;
                $this->warn("Skipped payment {$payment->id}: missing user_id or organization_id");
                continue;
            }

            $existingDonation = Donation::query()
                ->where('user_id', $userId)
                ->where('organization_id', $organizationId)
                ->where('campaign_id', $campaignId)
                ->where('amount', $payment->amount)
                ->where(function ($query) use ($payment) {
                    $query->where('created_at', '>=', $payment->created_at?->copy()->subMinutes(10));

                    if (Schema::hasColumn('payments', 'donation_id') && $payment->donation_id) {
                        $query->orWhere('id', $payment->donation_id);
                    }
                })
                ->latest('id')
                ->first();

            if ($existingDonation) {
                if (!$dryRun && Schema::hasColumn('payments', 'donation_id') && !$payment->donation_id) {
                    $updates = ['donation_id' => $existingDonation->id];

                    if (Schema::hasColumn('payments', 'payment_status')) {
                        $updates['payment_status'] = 'completed';
                    }

                    $payment->update($updates);
                }

                $skipped++;
                $this->line("Linked existing donation {$existingDonation->id} to payment {$payment->id}");
                continue;
            }

            if ($dryRun) {
                $created++;
                $this->info("Would create donation for payment {$payment->id}");
                continue;
            }

            DB::transaction(function () use ($payment, $userId, $organizationId, $campaignId, &$created) {
                $donation = Donation::create([
                    'user_id' => $userId,
                    'organization_id' => $organizationId,
                    'campaign_id' => $campaignId,
                    'amount' => $payment->amount,
                    'donation_type' => 'money',
                    'status' => 'completed',
                ]);

                DonationStatusHistory::create([
                    'donation_id' => $donation->id,
                    'old_status' => 'created',
                    'new_status' => 'completed',
                ]);

                $updates = [];

                if (Schema::hasColumn('payments', 'donation_id')) {
                    $updates['donation_id'] = $donation->id;
                }

                if (Schema::hasColumn('payments', 'payment_status')) {
                    $updates['payment_status'] = 'completed';
                }

                if ($updates !== []) {
                    $payment->update($updates);
                }

                $created++;
                $this->info("Created donation {$donation->id} from payment {$payment->id}");
            });
        }

        $this->newLine();
        $this->info("Backfill complete. Created: {$created}, Skipped/linked: {$skipped}");

        return self::SUCCESS;
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
