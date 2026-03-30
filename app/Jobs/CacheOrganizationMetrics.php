<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\OrganizationMetricsCache;
use App\Services\OrganizationMetricsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CacheOrganizationMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OrganizationMetricsService $service): void
    {
        $now = Carbon::now();
        $periodStart = $now->copy()->startOfMonth()->startOfDay();
        $periodEnd = $now->copy();
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $prevEnd = $now->copy()->subMonthNoOverflow();

        $this->storeCache($service, null, $periodStart, $periodEnd, $prevStart, $prevEnd);

        Organization::query()
            ->select('id')
            ->orderBy('id')
            ->chunk(100, function ($organizations) use ($service, $periodStart, $periodEnd, $prevStart, $prevEnd) {
                foreach ($organizations as $organization) {
                    $this->storeCache($service, (int) $organization->id, $periodStart, $periodEnd, $prevStart, $prevEnd);
                }
            });
    }

    private function storeCache(
        OrganizationMetricsService $service,
        ?int $organizationId,
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $prevStart,
        Carbon $prevEnd
    ): void {
        $summary = $service->computeSummary($organizationId, $periodStart, $periodEnd, $prevStart, $prevEnd);

        OrganizationMetricsCache::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            [
                'total_revenue' => $summary['metrics']['total_revenue']['value'],
                'active_donors' => (int) $summary['metrics']['active_donors']['value'],
                'material_units' => (int) $summary['metrics']['material_units']['value'],
                'avg_donation' => $summary['metrics']['avg_donation']['value'],
                'previous_total_revenue' => $summary['metrics']['total_revenue']['previous'],
                'previous_active_donors' => (int) $summary['metrics']['active_donors']['previous'],
                'previous_material_units' => (int) $summary['metrics']['material_units']['previous'],
                'previous_avg_donation' => $summary['metrics']['avg_donation']['previous'],
            ]
        );
    }
}
