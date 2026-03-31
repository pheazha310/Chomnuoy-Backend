<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignImage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    private const SUCCESSFUL_PAYMENT_STATUSES = ['completed', 'success', 'confirmed', 'paid'];
    private const USD_TO_KHR_RATE = 4100;

    private function successfulDonationAmountSubquery()
    {
        $statusList = "'" . implode("','", self::SUCCESSFUL_PAYMENT_STATUSES) . "'";

        return DB::table('donations')
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN donation_type = 'money' AND LOWER(status) IN ({$statusList}) THEN amount
                        ELSE 0
                    END
                ), 0)
            ")
            ->whereColumn('campaign_id', 'campaigns.id');
    }

    private function successfulTransactionAmountSubquery()
    {
        $statusList = "'" . implode("','", self::SUCCESSFUL_PAYMENT_STATUSES) . "'";
        $rate = self::USD_TO_KHR_RATE;

        return DB::table('transactions')
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(status) IN ({$statusList}) THEN
                            CASE
                                WHEN UPPER(currency) = 'KHR' THEN amount / {$rate}
                                ELSE amount
                            END
                        ELSE 0
                    END
                ), 0)
            ")
            ->whereNull('donation_id')
            ->whereColumn('campaign_id', 'campaigns.id');
    }

    private function successfulDirectPaymentAmountSubquery()
    {
        $rate = self::USD_TO_KHR_RATE;

        return DB::table('payments')
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN status = 'SUCCESS' THEN
                            CASE
                                WHEN UPPER(currency) = 'KHR' THEN amount / {$rate}
                                ELSE amount
                            END
                        ELSE 0
                    END
                ), 0)
            ")
            ->whereRaw("bill_number LIKE CONCAT('DON-', campaigns.id, '-%')");
    }

    private function successfulSupporterCountExpression(): string
    {
        $statusList = "'" . implode("','", self::SUCCESSFUL_PAYMENT_STATUSES) . "'";

        return "
            (
                SELECT COUNT(DISTINCT supporter_rows.user_id)
                FROM (
                    SELECT donations.user_id
                    FROM donations
                    WHERE donations.campaign_id = campaigns.id
                      AND donations.donation_type = 'money'
                      AND LOWER(donations.status) IN ({$statusList})

                    UNION

                    SELECT transactions.user_id
                    FROM transactions
                    WHERE transactions.campaign_id = campaigns.id
                      AND transactions.donation_id IS NULL
                      AND LOWER(transactions.status) IN ({$statusList})

                    UNION

                    SELECT payments.id as user_id
                    FROM payments
                    WHERE payments.status = 'SUCCESS'
                      AND payments.bill_number LIKE CONCAT('DON-', campaigns.id, '-%')
                ) AS supporter_rows
            )
        ";
    }

    private function withCampaignLiveTotals($query)
    {
        return $query
            ->addSelect([
                'successful_donation_amount' => $this->successfulDonationAmountSubquery(),
                'successful_transaction_amount' => $this->successfulTransactionAmountSubquery(),
                'successful_direct_payment_amount' => $this->successfulDirectPaymentAmountSubquery(),
            ])
            ->selectRaw(
                '('
                . $this->successfulDonationAmountSubquery()->toSql()
                . ') + ('
                . $this->successfulTransactionAmountSubquery()->toSql()
                . ') + ('
                . $this->successfulDirectPaymentAmountSubquery()->toSql()
                . ') as live_current_amount',
                array_merge(
                    $this->successfulDonationAmountSubquery()->getBindings(),
                    $this->successfulTransactionAmountSubquery()->getBindings(),
                    $this->successfulDirectPaymentAmountSubquery()->getBindings(),
                )
            )
            ->selectRaw($this->successfulSupporterCountExpression() . ' as live_supporter_count');
    }

    private function preparePayload(Request $request): array
    {
        $columns = Schema::getColumnListing('campaigns');
        $payload = array_intersect_key($request->all(), array_flip($columns));
        $jsonFields = [
            'donation_tiers',
            'material_priority',
            'material_item',
            'hybrid_items',
        ];

        foreach ($jsonFields as $field) {
            if (array_key_exists($field, $payload) && is_array($payload[$field])) {
                $payload[$field] = json_encode($payload[$field]);
            }
        }

        if (array_key_exists('enable_recurring', $payload)) {
            $payload['enable_recurring'] = filter_var($payload['enable_recurring'], FILTER_VALIDATE_BOOLEAN);
        }

        return $payload;
    }

    public function index(): JsonResponse
    {
        $records = $this->withCampaignLiveTotals(
            Campaign::query()->select('campaigns.*')
        )
            ->addSelect([
                'image_path' => CampaignImage::select('image_path')
                    ->whereColumn('campaign_id', 'campaigns.id')
                    ->limit(1),
            ])
            ->orderByDesc('campaigns.id')
            ->get();
        // $records = Campaign::all();

        return response()->json($records);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->preparePayload($request);
        $record = Campaign::create($payload);

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->withCampaignLiveTotals(
            Campaign::query()->select('campaigns.*')
        )
            ->addSelect([
                'image_path' => CampaignImage::select('image_path')
                    ->whereColumn('campaign_id', 'campaigns.id')
                    ->limit(1),
            ])
            ->findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = Campaign::findOrFail($id);
        $payload = $this->preparePayload($request);
        $record->update($payload);

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = Campaign::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }

    public function donations(int $id): JsonResponse
    {
        Campaign::findOrFail($id);

        $records = DB::table('donations')
            ->join('users', 'users.id', '=', 'donations.user_id')
            ->select(
                'donations.id',
                'users.name as donor_name',
                'donations.amount',
                'donations.status',
                'donations.created_at'
            )
            ->where('donations.campaign_id', $id)
            ->orderByDesc('donations.created_at')
            ->limit(10)
            ->get();

        return response()->json($records);
    }

    public function velocity(Request $request, int $id): JsonResponse
    {
        Campaign::findOrFail($id);

        $days = (int) $request->query('days', 30);
        if ($days < 7) {
            $days = 7;
        }
        if ($days > 365) {
            $days = 365;
        }

        $end = Carbon::today();
        $start = $end->copy()->subDays($days - 1);

        $rows = DB::table('donations')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as total'))
            ->where('campaign_id', $id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start->toDateString(), $end->toDateString()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('date');

        $series = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $total = $rows[$key]->total ?? 0;
            $series[] = [
                'date' => $key,
                'total' => (float) $total,
            ];
        }

        return response()->json([
            'days' => $days,
            'series' => $series,
        ]);
    }
}
