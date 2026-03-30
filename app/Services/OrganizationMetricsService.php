<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrganizationMetricsService
{
    public function computeSummary(?int $organizationId, Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = $this->computeTotals($organizationId, $start, $end);
        $previous = $this->computeTotals($organizationId, $prevStart, $prevEnd);

        return [
            'period' => [
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ],
            'previous_period' => [
                'start' => $prevStart->toDateTimeString(),
                'end' => $prevEnd->toDateTimeString(),
            ],
            'metrics' => [
                'total_revenue' => $this->buildMetric($current['total_revenue'], $previous['total_revenue']),
                'active_donors' => $this->buildMetric($current['active_donors'], $previous['active_donors']),
                'material_units' => $this->buildMetric($current['material_units'], $previous['material_units']),
                'avg_donation' => $this->buildMetric($current['avg_donation'], $previous['avg_donation']),
            ],
        ];
    }

    public function computeTotals(?int $organizationId, Carbon $start, Carbon $end): array
    {
        $totalRevenue = $this->sumPayments($organizationId, $start, $end);
        $activeDonors = $this->countActiveDonors($organizationId, $start, $end);
        $materialUnits = $this->sumMaterialUnits($organizationId, $start, $end);
        $avgDonation = $this->avgDonation($organizationId, $start, $end);

        return [
            'total_revenue' => $totalRevenue,
            'active_donors' => $activeDonors,
            'material_units' => $materialUnits,
            'avg_donation' => $avgDonation,
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

    private function sumPayments(?int $organizationId, Carbon $start, Carbon $end): float
    {
        $query = DB::table('payments')
            ->join('donations', 'donations.id', '=', 'payments.donation_id')
            ->where('payments.payment_status', 'completed')
            ->whereBetween('payments.created_at', [$start, $end]);

        if ($organizationId) {
            $query->where('donations.organization_id', $organizationId);
        }

        return (float) ($query->sum('payments.amount') ?? 0);
    }

    private function countActiveDonors(?int $organizationId, Carbon $start, Carbon $end): float
    {
        $query = DB::table('donations')
            ->where('donations.status', 'completed')
            ->whereBetween('donations.created_at', [$start, $end]);

        if ($organizationId) {
            $query->where('donations.organization_id', $organizationId);
        }

        return (float) ($query->distinct()->count('donations.user_id') ?? 0);
    }

    private function sumMaterialUnits(?int $organizationId, Carbon $start, Carbon $end): float
    {
        $query = DB::table('material_items')
            ->join('donations', 'donations.id', '=', 'material_items.donation_id')
            ->where('donations.donation_type', 'material')
            ->where('donations.status', 'completed')
            ->whereBetween('donations.created_at', [$start, $end]);

        if ($organizationId) {
            $query->where('donations.organization_id', $organizationId);
        }

        return (float) ($query->sum('material_items.quantity') ?? 0);
    }

    private function avgDonation(?int $organizationId, Carbon $start, Carbon $end): float
    {
        $query = DB::table('payments')
            ->join('donations', 'donations.id', '=', 'payments.donation_id')
            ->where('payments.payment_status', 'completed')
            ->where('donations.donation_type', 'money')
            ->whereBetween('payments.created_at', [$start, $end]);

        if ($organizationId) {
            $query->where('donations.organization_id', $organizationId);
        }

        return (float) ($query->avg('payments.amount') ?? 0);
    }
}
