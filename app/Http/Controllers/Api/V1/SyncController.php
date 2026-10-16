<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\PushSyncRequest;
use App\Services\NotificationService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        private readonly SyncService $syncService,
    ) {}

    /**
     * Push local changes to the server.
     */
    public function push(PushSyncRequest $request): JsonResponse
    {
        $changes = $request->validated('changes');
        $results = $this->syncService->push(
            $request->user()->id,
            $changes,
        );

        $changesCount = count($changes);
        if ($changesCount > 0) {
            NotificationService::log(
                user: $request->user(),
                title: 'Data Synced 🔄',
                message: "Successfully synchronized {$changesCount} offline change" . ($changesCount > 1 ? 's' : '') . " with the server.",
                category: 'system',
                actionRoute: '/home',
            );
        }

        return response()->json([
            'message' => 'Sync push completed.',
            'results' => $results,
        ]);
    }

    /**
     * Pull changes since last sync.
     */
    public function pull(Request $request): JsonResponse
    {
        $data = $this->syncService->pull(
            $request->user()->id,
            $request->query('since'),
        );

        return response()->json($data);
    }

    /**
     * Get sync status and pending conflicts.
     */
    public function status(Request $request): JsonResponse
    {
        $status = $this->syncService->getStatus($request->user()->id);

        return response()->json([
            'sync' => $status,
        ]);
    }

    /**
     * Resolve a sync conflict.
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'sync_queue_id' => ['required', 'uuid'],
            'resolution' => ['required', 'string', 'in:client,server'],
        ]);

        $result = $this->syncService->resolveConflict(
            $request->input('sync_queue_id'),
            $request->user()->id,
            $request->input('resolution'),
        );

        return response()->json($result);
    }
}
