<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReminderController extends Controller
{
    /**
     * List all active reminders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $reminders = Reminder::forUser($request->user()->id)
            ->with('debt.contact:id,full_name,nickname')
            ->where('is_sent', false)
            ->orderBy('remind_at')
            ->paginate($request->query('per_page', 15));

        return ReminderResource::collection($reminders);
    }

    /**
     * Create a new reminder.
     */
    public function store(StoreReminderRequest $request): JsonResponse
    {
        $reminder = Reminder::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'message' => 'Reminder created successfully.',
            'reminder' => new ReminderResource($reminder->load('debt.contact')),
        ], 201);
    }

    /**
     * Update a reminder.
     */
    public function update(UpdateReminderRequest $request, string $id): JsonResponse
    {
        $reminder = Reminder::forUser($request->user()->id)->findOrFail($id);
        $reminder->update($request->validated());

        return response()->json([
            'message' => 'Reminder updated successfully.',
            'reminder' => new ReminderResource($reminder->fresh('debt.contact')),
        ]);
    }

    /**
     * Delete a reminder.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $reminder = Reminder::forUser($request->user()->id)->findOrFail($id);
        $reminder->delete();

        return response()->json([
            'message' => 'Reminder deleted successfully.',
        ]);
    }

    /**
     * Get upcoming reminders (next 7 days).
     */
    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $days = (int) $request->query('days', 7);
        $reminders = Reminder::forUser($request->user()->id)
            ->upcoming($days)
            ->with('debt.contact:id,full_name,nickname')
            ->get();

        return ReminderResource::collection($reminders);
    }
}
