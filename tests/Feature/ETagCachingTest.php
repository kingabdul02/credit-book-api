<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ETagCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_request_includes_etag_and_returns_304_when_unmodified(): void
    {
        $user = User::factory()->create();

        // 1. First GET request
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/analytics/dashboard');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('ETag'));
        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);

        // 2. Second GET request with If-None-Match matching the ETag
        $conditionalResponse = $this->actingAs($user, 'sanctum')
            ->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/analytics/dashboard');

        $conditionalResponse->assertStatus(304);
        $this->assertEmpty($conditionalResponse->getContent());
    }
}
