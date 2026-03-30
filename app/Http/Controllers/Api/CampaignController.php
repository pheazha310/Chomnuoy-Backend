<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignImage;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    private function notifyDonorsAboutCampaign(Campaign $campaign, string $action = 'published'): void
    {
        $status = strtolower((string) ($campaign->status ?? ''));
        if ($status !== 'active') {
            return;
        }

        $donorRoleId = Role::query()->where('role_name', 'Donor')->value('id');
        if (!$donorRoleId) {
            return;
        }

        $organizationName = DB::table('organizations')
            ->where('id', $campaign->organization_id)
            ->value('name') ?? 'An organization';

        $message = $action === 'updated'
            ? sprintf('%s updated the campaign "%s".', $organizationName, $campaign->title)
            : sprintf('%s posted a new campaign: "%s".', $organizationName, $campaign->title);

        $donorIds = User::query()
            ->where('role_id', $donorRoleId)
            ->pluck('id');

        foreach ($donorIds as $donorId) {
            Notification::create([
                'user_id' => (int) $donorId,
                'sender_type' => 'organization',
                'sender_name' => $organizationName,
                'recipient_type' => 'user',
                'recipient_id' => $donorId,
                'message' => $message,
                'type' => 'campaign',
                'is_read' => false,
            ]);
        }
    }

    private function notifyCampaignPublished(Campaign $campaign): void
    {
        $title = trim((string) ($campaign->title ?? 'Untitled Campaign'));
        $organizationId = (int) ($campaign->organization_id ?? 0);
        $organizationName = 'Organization';

        if ($organizationId > 0) {
            $organizationName = Organization::query()
                ->where('id', $organizationId)
                ->value('name') ?? 'Organization';
        }

        $message = sprintf('New campaign published: %s', $title);

        if ($organizationId > 0) {
            Notification::create([
                'user_id' => $organizationId,
                'sender_type' => 'organization',
                'sender_name' => $organizationName,
                'recipient_type' => 'organization',
                'recipient_id' => $organizationId,
                'message' => $message,
                'type' => 'campaign',
                'is_read' => false,
            ]);
        }

        Notification::create([
            'user_id' => $organizationId > 0 ? $organizationId : 1,
            'sender_type' => 'organization',
            'sender_name' => $organizationName,
            'recipient_type' => 'admin',
            'message' => $message,
            'type' => 'campaign',
            'is_read' => false,
        ]);

        $donorIds = User::query()
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereRaw('LOWER(roles.role_name) = ?', ['donor'])
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($donorIds as $donorId) {
            Notification::create([
                'user_id' => $donorId,
                'sender_type' => 'organization',
                'sender_name' => $organizationName,
                'recipient_type' => 'user',
                'recipient_id' => $donorId,
                'message' => $message,
                'type' => 'campaign',
                'is_read' => false,
            ]);
        }
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
        $records = Campaign::query()
            ->leftJoin('organizations', 'organizations.id', '=', 'campaigns.organization_id')
            ->select(
                'campaigns.*',
                'organizations.name as organization_name',
                'organizations.location as organization_location',
                'organizations.latitude as organization_latitude',
                'organizations.longitude as organization_longitude'
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
        $this->notifyDonorsAboutCampaign($record, 'published');

        if (strtolower((string) ($record->status ?? '')) === 'active') {
            $this->notifyCampaignPublished($record);
        }

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = Campaign::query()
            ->leftJoin('organizations', 'organizations.id', '=', 'campaigns.organization_id')
            ->select(
                'campaigns.*',
                'organizations.name as organization_name',
                'organizations.location as organization_location',
                'organizations.latitude as organization_latitude',
                'organizations.longitude as organization_longitude'
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
        $wasActive = strtolower((string) ($record->status ?? '')) === 'active';
        $payload = $this->preparePayload($request);
        $record->update($payload);
        $record->refresh();

        if (!$wasActive && strtolower((string) ($record->status ?? '')) === 'active') {
            $this->notifyDonorsAboutCampaign($record, 'published');
        }

        $isActive = strtolower((string) ($record->status ?? '')) === 'active';
        if (!$wasActive && $isActive) {
            $this->notifyCampaignPublished($record);
        }

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
