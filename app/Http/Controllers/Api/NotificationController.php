<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationController extends Controller
{
    private function hasRecipientColumns(): bool
    {
        return Schema::hasColumn('notifications', 'recipient_type')
            && Schema::hasColumn('notifications', 'recipient_id');
    }

    private function filterPayloadForExistingColumns(array $payload): array
    {
        $allowed = ['user_id', 'message', 'type', 'is_read'];

        foreach (['sender_type', 'sender_name', 'sender_email', 'recipient_type', 'recipient_id'] as $column) {
            if (Schema::hasColumn('notifications', $column)) {
                $allowed[] = $column;
            }
        }

        return array_intersect_key($payload, array_flip($allowed));
    }

    public function index(): JsonResponse
    {
        $query = Notification::query()->orderByDesc('id');
        $recipientType = trim((string) request()->query('recipient_type', ''));
        $recipientId = (int) request()->query('recipient_id', 0);

        if ($recipientType !== '' && $this->hasRecipientColumns()) {
            $query->where('recipient_type', $recipientType);
            if ($recipientId > 0) {
                $query->where('recipient_id', $recipientId);
            }
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->filterPayloadForExistingColumns($request->all());

        if (!$this->hasRecipientColumns()) {
            $fallbackRecipientId = (int) ($request->input('recipient_id') ?? 0);
            if ($fallbackRecipientId > 0) {
                $payload['user_id'] = $fallbackRecipientId;
            }
        }

        $record = Notification::create($payload);

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = Notification::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = Notification::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = Notification::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }

    public function stream(Request $request): StreamedResponse
    {
        $userId = (int) $request->query('user_id', 0);
        $recipientType = trim((string) $request->query('recipient_type', ''));
        $recipientId = (int) $request->query('recipient_id', 0);
        $lastId = (int) $request->query('last_id', 0);
        $sleepSeconds = max(1, min(10, (int) $request->query('sleep', 2)));
        $maxSeconds = max(10, min(120, (int) $request->query('max_seconds', 55)));

        return response()->stream(function () use ($userId, $recipientType, $recipientId, $lastId, $sleepSeconds, $maxSeconds) {
            @set_time_limit(0);
            $lastSent = $lastId;
            $startedAt = time();
            $hasRecipientColumns = $this->hasRecipientColumns();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $query = Notification::query()->orderBy('id');
                if ($lastSent > 0) {
                    $query->where('id', '>', $lastSent);
                }
                if ($recipientType !== '' && $hasRecipientColumns) {
                    $query->where('recipient_type', $recipientType);
                    if ($recipientId > 0) {
                        $query->where('recipient_id', $recipientId);
                    }
                } elseif ($userId > 0) {
                    $query->where('user_id', $userId);
                }

                $records = $query->limit(50)->get();
                foreach ($records as $record) {
                    echo 'id: ' . $record->id . "\n";
                    echo "event: notification\n";
                    echo 'data: ' . json_encode($record) . "\n\n";
                    $lastSent = $record->id;
                }

                echo ": heartbeat\n\n";
                @ob_flush();
                @flush();

                if ((time() - $startedAt) >= $maxSeconds) {
                    break;
                }
                sleep($sleepSeconds);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
