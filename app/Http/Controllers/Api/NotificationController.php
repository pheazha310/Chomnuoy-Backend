<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Notification::query()->orderByDesc('id');

        if (request()->filled('user_id')) {
            $query->where('user_id', (int) request()->query('user_id'));
        }

        if (request()->filled('recipient_type')) {
            $query->where('recipient_type', request()->query('recipient_type'));
        }

        if (request()->filled('recipient_id')) {
            $query->where('recipient_id', (int) request()->query('recipient_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $record = Notification::create($request->all());

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

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $query = Notification::query()->orderBy('id');
                if ($lastSent > 0) {
                    $query->where('id', '>', $lastSent);
                }
                if ($userId > 0) {
                    $query->where('user_id', $userId);
                }
                if ($recipientType !== '') {
                    $query->where('recipient_type', $recipientType);
                }
                if ($recipientId > 0) {
                    $query->where('recipient_id', $recipientId);
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
