<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_list_contacts(): void
    {
        Contact::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contacts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_contact(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contacts', [
                'full_name' => 'John Doe',
                'nickname' => 'JD',
                'phone_number' => '+2348012345678',
                'client_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('contact.full_name', 'John Doe');

        $this->assertDatabaseHas('contacts', [
            'full_name' => 'John Doe',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_view_single_contact(): void
    {
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk()
            ->assertJsonPath('contact.id', $contact->id);
    }

    public function test_user_can_update_contact(): void
    {
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/contacts/{$contact->id}", [
                'full_name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('contact.full_name', 'Updated Name');
    }

    public function test_user_can_delete_contact(): void
    {
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk();
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_user_cannot_see_other_users_contacts(): void
    {
        $otherUser = User::factory()->create();
        $contact = Contact::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_search_contacts(): void
    {
        Contact::factory()->create([
            'user_id' => $this->user->id,
            'full_name' => 'Alice Johnson',
        ]);
        Contact::factory()->create([
            'user_id' => $this->user->id,
            'full_name' => 'Bob Smith',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contacts?search=Alice');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Alice Johnson');
    }

    public function test_user_can_list_favorite_contacts(): void
    {
        Contact::factory()->create([
            'user_id' => $this->user->id,
            'is_favorite' => true,
        ]);
        Contact::factory()->create([
            'user_id' => $this->user->id,
            'is_favorite' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contacts/favorites');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
