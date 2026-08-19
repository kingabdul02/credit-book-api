<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Http\Requests\Contact\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    /**
     * List all contacts for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Contact::forUser($request->user()->id)
            ->withCount('debts');

        if ($search = $request->query('search')) {
            $query->search($search);
        }

        $sortBy = $request->query('sort_by', 'full_name');
        $sortDir = $request->query('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $contacts = $query->paginate((int) $request->query('per_page', 15));

        $contacts->each(function ($contact) {
            $contact->append(['total_owed_to_user', 'total_user_owes']);
        });

        return ContactResource::collection($contacts);
    }

    /**
     * Create a new contact.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = Contact::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'message' => 'Contact created successfully.',
            'contact' => new ContactResource($contact),
        ], 201);
    }

    /**
     * Get a single contact with debt summary.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $contact = Contact::forUser($request->user()->id)
            ->with('debts.transactions')
            ->withCount('debts')
            ->findOrFail($id);

        $contact->append(['total_owed_to_user', 'total_user_owes']);

        return response()->json([
            'contact' => new ContactResource($contact),
        ]);
    }

    /**
     * Update a contact.
     */
    public function update(UpdateContactRequest $request, string $id): JsonResponse
    {
        $contact = Contact::forUser($request->user()->id)->findOrFail($id);
        $contact->update($request->validated());

        return response()->json([
            'message' => 'Contact updated successfully.',
            'contact' => new ContactResource($contact->fresh()),
        ]);
    }

    /**
     * Soft-delete a contact.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $contact = Contact::forUser($request->user()->id)->findOrFail($id);
        $contact->delete();

        return response()->json([
            'message' => 'Contact deleted successfully.',
        ]);
    }

    /**
     * List favorite contacts.
     */
    public function favorites(Request $request): AnonymousResourceCollection
    {
        $contacts = Contact::forUser($request->user()->id)
            ->favorites()
            ->withCount('debts')
            ->orderBy('full_name')
            ->get();

        $contacts->each(function ($contact) {
            $contact->append(['total_owed_to_user', 'total_user_owes']);
        });

        return ContactResource::collection($contacts);
    }

    /**
     * List frequently used contacts.
     */
    public function frequent(Request $request): AnonymousResourceCollection
    {
        $contacts = Contact::forUser($request->user()->id)
            ->frequentlyUsed((int) $request->query('limit', 10))
            ->withCount('debts')
            ->get();

        $contacts->each(function ($contact) {
            $contact->append(['total_owed_to_user', 'total_user_owes']);
        });

        return ContactResource::collection($contacts);
    }
}
