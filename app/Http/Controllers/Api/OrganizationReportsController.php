<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrganizationReportsController extends Controller
{
    public function transactions(Request $request): JsonResponse
    {
        $organizationId = $this->parseOrganizationId($request);
        $perPage = (int) $request->query('per_page', $request->query('limit', 10));
        $perPage = max(1, min($perPage, 50));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $type = strtolower((string) $request->query('type', $request->query('donation_type', '')));

        $baseQuery = DB::table('donations')
            ->leftJoin('users', 'users.id', '=', 'donations.user_id')
            ->leftJoin('organizations', 'organizations.id', '=', 'donations.organization_id')
            ->leftJoin('payments', function ($join) {
                $join->on('payments.donation_id', '=', 'donations.id')
                    ->where('payments.payment_status', 'completed');
            })
            ->leftJoin('material_items', 'material_items.donation_id', '=', 'donations.id');

        if ($organizationId) {
            $baseQuery->where('donations.organization_id', $organizationId);
        }

        if ($status) {
            $baseQuery->where('donations.status', $status);
        }

        if ($type === 'financial') {
            $baseQuery->where('donations.donation_type', 'money');
        } elseif ($type === 'material') {
            $baseQuery->where('donations.donation_type', 'material');
        } elseif ($type) {
            $baseQuery->where('donations.donation_type', $type);
        }

        if ($search !== '') {
            $normalized = preg_replace('/^#?TXN-?/i', '', $search);
            $baseQuery->where(function ($query) use ($search, $normalized) {
                if (is_numeric($normalized)) {
                    $query->orWhere('donations.id', (int) $normalized);
                }
                $like = '%' . $search . '%';
                $query->orWhere('users.name', 'like', $like);
            });
        }

        $total = (clone $baseQuery)->distinct('donations.id')->count('donations.id');
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        $rows = (clone $baseQuery)
            ->select([
                'donations.id as donation_id',
                'donations.donation_type',
                'donations.amount as donation_amount',
                'donations.created_at as donation_date',
                'users.name as donor_name',
                'organizations.location as province',
                DB::raw('SUM(material_items.quantity) as material_units'),
                DB::raw('MAX(payments.amount) as payment_amount'),
            ])
            ->groupBy(
                'donations.id',
                'donations.donation_type',
                'donations.amount',
                'donations.created_at',
                'users.name',
                'organizations.location'
            )
            ->orderByDesc('donations.created_at')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $payload = $rows->map(function ($row) {
            $type = $row->donation_type === 'material' ? 'Material' : 'Financial';
            $materialUnits = (int) ($row->material_units ?? 0);
            $amountValue = $type === 'Financial'
                ? (float) ($row->payment_amount ?? $row->donation_amount ?? 0)
                : $materialUnits;

            return [
                'code' => 'TXN-' . $row->donation_id,
                'donor' => $row->donor_name ?? 'Unknown Donor',
                'type' => $type,
                'province' => $row->province ?? 'Unknown',
                'date' => $row->donation_date,
                'amount_value' => $amountValue,
                'material_units' => $materialUnits,
            ];
        });

        return response()->json([
            'data' => $payload,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $total > 0 ? min($offset + 1, $total) : 0,
                'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
            ],
        ]);
    }

    public function financialSummary(Request $request): JsonResponse
    {
        $organizationId = $this->parseOrganizationId($request);
        $now = Carbon::now();
        $periodStart = $now->copy()->startOfMonth()->startOfDay();
        $periodEnd = $now->copy();
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $prevEnd = $now->copy()->subMonthNoOverflow();

        $current = $this->computeFinancialTotals($organizationId, $periodStart, $periodEnd);
        $previous = $this->computeFinancialTotals($organizationId, $prevStart, $prevEnd);

        return response()->json([
            'period' => [
                'start' => $periodStart->toDateTimeString(),
                'end' => $periodEnd->toDateTimeString(),
            ],
            'previous_period' => [
                'start' => $prevStart->toDateTimeString(),
                'end' => $prevEnd->toDateTimeString(),
            ],
            'metrics' => [
                'total_revenue' => $this->buildMetric($current['total_revenue'], $previous['total_revenue']),
                'active_donors' => $this->buildMetric($current['active_donors'], $previous['active_donors']),
                'avg_donation' => $this->buildMetric($current['avg_donation'], $previous['avg_donation']),
                'conversion_rate' => $this->buildMetric($current['conversion_rate'], $previous['conversion_rate']),
            ],
        ]);
    }

    public function materialSummary(Request $request): JsonResponse
    {
        $organizationId = $this->parseOrganizationId($request);
        $now = Carbon::now();
        $periodStart = $now->copy()->startOfMonth()->startOfDay();
        $periodEnd = $now->copy();
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $prevEnd = $now->copy()->subMonthNoOverflow();

        $current = $this->computeMaterialTotals($organizationId, $periodStart, $periodEnd);
        $previous = $this->computeMaterialTotals($organizationId, $prevStart, $prevEnd);

        return response()->json([
            'period' => [
                'start' => $periodStart->toDateTimeString(),
                'end' => $periodEnd->toDateTimeString(),
            ],
            'previous_period' => [
                'start' => $prevStart->toDateTimeString(),
                'end' => $prevEnd->toDateTimeString(),
            ],
            'metrics' => [
                'total_items_collected' => $this->buildMetric($current['total_items_collected'], $previous['total_items_collected']),
                'successful_deliveries' => $this->buildMetric($current['successful_deliveries'], $previous['successful_deliveries']),
                'pending_pickups' => $this->buildMetric($current['pending_pickups'], $previous['pending_pickups']),
                'delivery_success_rate' => $this->buildMetric($current['delivery_success_rate'], $previous['delivery_success_rate']),
            ],
        ]);
    }

    public function sourceBreakdown(Request $request): JsonResponse
    {
        $organizationId = $this->parseOrganizationId($request);
        $now = Carbon::now();
        $periodStart = $now->copy()->startOfMonth()->startOfDay();
        $periodEnd = $now->copy();

        $query = DB::table('payments')
            ->join('donations', 'donations.id', '=', 'payments.donation_id')
            ->join('organizations', 'organizations.id', '=', 'donations.organization_id')
            ->join('categories', 'categories.id', '=', 'organizations.category_id')
            ->where('payments.payment_status', 'completed')
            ->where('donations.donation_type', 'money')
            ->whereBetween('payments.created_at', [$periodStart, $periodEnd])
            ->select([
                'categories.id as category_id',
                'categories.category_name as category_name',
                DB::raw('SUM(payments.amount) as total_amount'),
            ])
            ->groupBy('categories.id', 'categories.category_name')
            ->orderByDesc('total_amount');

        if ($organizationId) {
            $query->where('donations.organization_id', $organizationId);
        }

        $rows = $query->get();
        $total = (float) $rows->sum('total_amount');

        $items = $rows->map(function ($row) use ($total) {
            $value = (float) ($row->total_amount ?? 0);
            $percent = $total > 0 ? ($value / $total) * 100 : 0.0;
            return [
                'category_id' => $row->category_id,
                'label' => $row->category_name,
                'value' => $value,
                'percent' => $percent,
            ];
        });

        return response()->json([
            'period' => [
                'start' => $periodStart->toDateTimeString(),
                'end' => $periodEnd->toDateTimeString(),
            ],
            'total' => $total,
            'items' => $items,
        ]);
    }

    public function financialTransactions(Request $request): JsonResponse
    {
        $organizationId = $this->parseOrganizationId($request);
        $limit = (int) $request->query('limit', 5);
        $limit = max(1, min($limit, 50));

        $query = DB::table('payments')
            ->join('donations', 'donations.id', '=', 'payments.donation_id')
            ->leftJoin('users', 'users.id', '=', 'donations.user_id')
            ->where('donations.donation_type', 'money')
            ->select([
                'payments.created_at as payment_date',
                'payments.payment_status',
                'payments.amount as payment_amount',
                'payments.transaction_reference',
                'donations.campaign_id',
                'users.name as donor_name',
            ])
            ->orderByDesc('payments.created_at')
            ->limit($limit);

        if ($organizationId) {
            $query->where('donations.organization_id', $organizationId);
        }

        $rows = $query->get();

        $payload = $rows->map(function ($row) {
            $transactionRef = (string) ($row->transaction_reference ?? '');
            $isRecurring = str_starts_with($transactionRef, 'REC');
            $type = $isRecurring ? 'Recurring' : 'One-time';

            return [
                'date' => $row->payment_date,
                'donor' => $row->donor_name ?? 'Unknown Donor',
                'type' => $type,
                'amount' => (float) ($row->payment_amount ?? 0),
                'status' => ucfirst((string) ($row->payment_status ?? 'pending')),
            ];
        });

        return response()->json($payload);
    }

    private function computeFinancialTotals(?int $organizationId, Carbon $start, Carbon $end): array
    {
        $paymentsBase = DB::table('payments')
            ->join('donations', 'donations.id', '=', 'payments.donation_id')
            ->where('payments.payment_status', 'completed')
            ->where('donations.donation_type', 'money')
            ->whereBetween('payments.created_at', [$start, $end]);

        $donationsBase = DB::table('donations')
            ->where('donations.donation_type', 'money')
            ->whereBetween('donations.created_at', [$start, $end]);

        if ($organizationId) {
            $paymentsBase->where('donations.organization_id', $organizationId);
            $donationsBase->where('donations.organization_id', $organizationId);
        }

        $paymentsForSum = clone $paymentsBase;
        $paymentsForAvg = clone $paymentsBase;
        $donationsForActive = clone $donationsBase;
        $donationsForTotal = clone $donationsBase;
        $donationsForCompleted = clone $donationsBase;

        $totalRevenue = (float) ($paymentsForSum->sum('payments.amount') ?? 0);
        $activeDonors = (float) ($donationsForActive
            ->where('donations.status', 'completed')
            ->distinct()
            ->count('donations.user_id') ?? 0);
        $avgDonation = (float) ($paymentsForAvg->avg('payments.amount') ?? 0);

        $totalMoneyDonations = (int) ($donationsForTotal->count('donations.id') ?? 0);
        $completedMoneyDonations = (int) ($donationsForCompleted
            ->where('donations.status', 'completed')
            ->count('donations.id') ?? 0);

        $conversionRate = $totalMoneyDonations > 0
            ? ($completedMoneyDonations / $totalMoneyDonations) * 100
            : 0.0;

        return [
            'total_revenue' => $totalRevenue,
            'active_donors' => $activeDonors,
            'avg_donation' => $avgDonation,
            'conversion_rate' => $conversionRate,
        ];
    }

    private function computeMaterialTotals(?int $organizationId, Carbon $start, Carbon $end): array
    {
        $itemsBase = DB::table('material_items')
            ->join('donations', 'donations.id', '=', 'material_items.donation_id')
            ->where('donations.donation_type', 'material')
            ->whereBetween('donations.created_at', [$start, $end]);

        $pickupsBase = DB::table('material_pickups')
            ->join('donations', 'donations.id', '=', 'material_pickups.donation_id')
            ->where('donations.donation_type', 'material')
            ->whereBetween('donations.created_at', [$start, $end]);

        if ($organizationId) {
            $itemsBase->where('donations.organization_id', $organizationId);
            $pickupsBase->where('donations.organization_id', $organizationId);
        }

        $totalItems = (int) ($itemsBase->sum('material_items.quantity') ?? 0);
        $pickupsTotal = (int) (clone $pickupsBase)->count('material_pickups.id');
        $successful = (int) (clone $pickupsBase)
            ->whereIn(DB::raw('LOWER(material_pickups.status)'), ['completed', 'delivered'])
            ->count('material_pickups.id');
        $pending = (int) (clone $pickupsBase)
            ->whereIn(DB::raw('LOWER(material_pickups.status)'), ['pending', 'scheduled'])
            ->count('material_pickups.id');

        $successRate = $pickupsTotal > 0 ? ($successful / $pickupsTotal) * 100 : 0.0;

        return [
            'total_items_collected' => $totalItems,
            'successful_deliveries' => $successful,
            'pending_pickups' => $pending,
            'delivery_success_rate' => $successRate,
        ];
    }

    private function buildMetric(float $current, float $previous): array
    {
        return [
            'value' => $current,
            'previous' => $previous,
            'delta_percent' => $this->deltaPercent($current, $previous),
            'positive' => $current >= $previous,
        ];
    }

    private function deltaPercent(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return $current > 0.0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100;
    }

    private function parseOrganizationId(Request $request): ?int
    {
        $raw = $request->query('organization_id');

        return is_numeric($raw) ? (int) $raw : null;
    }
}
