<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Donation;
use App\Models\Notification;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignUpdateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CampaignUpdate::query()->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'update_description' => ['required', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ]);

        $record = CampaignUpdate::create($validated);

        $campaign = Campaign::find($validated['campaign_id']);
        $organizationName = 'Organization';
        if ($campaign && $campaign->organization_id) {
            $organizationName = Organization::query()
                ->where('id', $campaign->organization_id)
                ->value('name') ?? 'Organization';
        }

        $donorIds = Donation::query()
            ->where('campaign_id', $validated['campaign_id'])
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->filter()
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
                'message' => sprintf(
                    'Campaign update for %s: %s',
                    $campaign?->title ?? 'your campaign',
                    trim($validated['update_description'])
                ),
                'type' => 'campaign',
                'is_read' => false,
            ]);
        }

        if ($campaign && $campaign->organization_id) {
            Notification::create([
                'user_id' => $campaign->organization_id,
                'sender_type' => 'organization',
                'sender_name' => $organizationName,
                'recipient_type' => 'organization',
                'recipient_id' => $campaign->organization_id,
                'message' => sprintf(
                    'Campaign update for %s: %s',
                    $campaign?->title ?? 'your campaign',
                    trim($validated['update_description'])
                ),
                'type' => 'campaign',
                'is_read' => false,
            ]);
        }

        Notification::create([
            'user_id' => $campaign?->organization_id ?? 1,
            'sender_type' => 'organization',
            'sender_name' => $organizationName,
            'recipient_type' => 'admin',
            'message' => sprintf(
                'Campaign update for %s: %s',
                $campaign?->title ?? 'your campaign',
                trim($validated['update_description'])
            ),
            'type' => 'campaign',
            'is_read' => false,
        ]);

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = CampaignUpdate::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = CampaignUpdate::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = CampaignUpdate::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
