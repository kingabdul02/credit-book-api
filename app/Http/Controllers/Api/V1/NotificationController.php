<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * List all notifications for the authenticated user along with unread count.
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->notificationService->getForUser($request->user());

        return response()->json([
            'data' => $result['data'],
            'unread_count' => $result['unread_count'],
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user(), $id);

        if (!$notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $notification,
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'message' => 'All notifications marked as read.',
            'updated_count' => $count,
        ]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $this->notificationService->delete($request->user(), $id);

        if (!$deleted) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        return response()->json([
            'message' => 'Notification deleted successfully.',
        ]);
    }

    /**
     * Clear all notifications for the authenticated user.
     */
    public function clearAll(Request $request): JsonResponse
    {
        $this->notificationService->clearAll($request->user());

        return response()->json([
            'message' => 'All notifications cleared successfully.',
        ]);
    }
}
