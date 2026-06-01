<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /api/auth/tenant-info', function () {
    it('returns 200 with slug and name for an active tenant', function () {
        $tenant = Tenant::factory()->create([
            'slug' => 'acme',
            'name' => 'Acme School',
            'status' => 'active',
        ]);

        $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/auth/tenant-info');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'acme')
            ->assertJsonPath('data.name', 'Acme School');
    });

    it('returns 200 with slug and name for a pending tenant', function () {
        $tenant = Tenant::factory()->create([
            'slug' => 'pending-school',
            'name' => 'Pending School',
            'status' => 'pending',
        ]);

        $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/auth/tenant-info');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'pending-school')
            ->assertJsonPath('data.name', 'Pending School');
    });

    it('returns 404 when tenant does not exist', function () {
        $response = $this->withHeader('X-Tenant-Slug', 'nonexistent-slug')
            ->getJson('/api/auth/tenant-info');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Tenant not found');
    });

    it('returns 400 when X-Tenant-Slug header is missing', function () {
        $this->getJson('/api/auth/tenant-info')
            ->assertStatus(400);
    });

    it('response never exposes id — only slug and name in data', function () {
        $tenant = Tenant::factory()->create([
            'slug' => 'no-id-exposed',
            'name' => 'No ID School',
            'status' => 'active',
        ]);

        $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/auth/tenant-info');

        $response->assertStatus(200);

        expect($response->json('data.id'))->toBeNull();
        expect($response->json('data.slug'))->toBe('no-id-exposed');
        expect($response->json('data.name'))->toBe('No ID School');
    });
});
