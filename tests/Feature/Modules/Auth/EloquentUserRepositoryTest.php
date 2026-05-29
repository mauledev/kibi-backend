<?php

use App\Common\Tenant\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Auth\Domain\Entities\User as UserEntity;
use App\Modules\Auth\Infrastructure\Repositories\EloquentUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function repoCreateTenantUser(Tenant $tenant, array $attributes = []): User
{
    return User::factory()->create(array_merge(['tenant_id' => $tenant->id], $attributes));
}

describe('EloquentUserRepository', function () {
    beforeEach(function () {
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        app()->instance(TenantContext::class, new TenantContext(
            tenantId: $this->tenantA->id,
        ));
        $this->repo = new EloquentUserRepository(app(TenantContext::class));
    });

    describe('findByEmail', function () {
        it('returns user when email belongs to current tenant', function () {
            repoCreateTenantUser($this->tenantA, ['email' => 'mine@test.com']);

            $result = $this->repo->findByEmail('mine@test.com');

            expect($result)->not->toBeNull();
            expect($result->getEmail())->toBe('mine@test.com');
        });

        it('returns null when email belongs to a different tenant', function () {
            repoCreateTenantUser($this->tenantB, ['email' => 'theirs@test.com']);

            $result = $this->repo->findByEmail('theirs@test.com');

            expect($result)->toBeNull();
        });

        it('returns null when email does not exist', function () {
            $result = $this->repo->findByEmail('nonexistent@test.com');

            expect($result)->toBeNull();
        });

        it('returns the tenant owner by email', function () {
            $owner = User::find($this->tenantA->owner_id);

            $result = $this->repo->findByEmail($owner->email);

            expect($result)->not->toBeNull();
            expect($result->getId())->toBe($owner->id);
        });
    });

    describe('findById', function () {
        it('returns user when id belongs to current tenant', function () {
            $user = repoCreateTenantUser($this->tenantA);

            $result = $this->repo->findById($user->id);

            expect($result)->not->toBeNull();
            expect($result->getId())->toBe($user->id);
        });

        it('returns null when user belongs to a different tenant', function () {
            $user = repoCreateTenantUser($this->tenantB);

            $result = $this->repo->findById($user->id);

            expect($result)->toBeNull();
        });

        it('returns null when id does not exist', function () {
            $result = $this->repo->findById(99999);

            expect($result)->toBeNull();
        });

        it('returns the tenant owner by id', function () {
            $result = $this->repo->findById($this->tenantA->owner_id);

            expect($result)->not->toBeNull();
            expect($result->getId())->toBe($this->tenantA->owner_id);
        });
    });

    describe('findByUuid', function () {
        it('returns user when uuid belongs to current tenant', function () {
            $user = repoCreateTenantUser($this->tenantA);

            $result = $this->repo->findByUuid($user->uuid);

            expect($result)->not->toBeNull();
            expect($result->getUuid())->toBe($user->uuid);
        });

        it('returns null when uuid belongs to a different tenant', function () {
            $user = repoCreateTenantUser($this->tenantB);

            $result = $this->repo->findByUuid($user->uuid);

            expect($result)->toBeNull();
        });
    });

    describe('findByGoogleId', function () {
        it('returns user when google_id matches current tenant', function () {
            repoCreateTenantUser($this->tenantA, ['google_id' => 'g-abc', 'email' => 'google@test.com']);

            $result = $this->repo->findByGoogleId('g-abc');

            expect($result)->not->toBeNull();
            expect($result->getGoogleId())->toBe('g-abc');
        });

        it('returns null when google_id belongs to a different tenant', function () {
            repoCreateTenantUser($this->tenantB, ['google_id' => 'g-xyz', 'email' => 'other@test.com']);

            $result = $this->repo->findByGoogleId('g-xyz');

            expect($result)->toBeNull();
        });

        it('returns null when google_id does not exist', function () {
            $result = $this->repo->findByGoogleId('unknown-google-id');

            expect($result)->toBeNull();
        });
    });

    describe('findByMicrosoftId', function () {
        it('returns user when microsoft_id matches current tenant', function () {
            repoCreateTenantUser($this->tenantA, ['microsoft_id' => 'ms-123', 'email' => 'ms@test.com']);

            $result = $this->repo->findByMicrosoftId('ms-123');

            expect($result)->not->toBeNull();
            expect($result->getMicrosoftId())->toBe('ms-123');
        });

        it('returns null when microsoft_id does not exist in current tenant', function () {
            repoCreateTenantUser($this->tenantB, ['microsoft_id' => 'ms-456', 'email' => 'ms2@test.com']);

            $result = $this->repo->findByMicrosoftId('ms-456');

            expect($result)->toBeNull();
        });
    });

    describe('save', function () {
        it('creates the user record in the database', function () {
            $user = repoCreateTenantUser($this->tenantA, ['email' => 'factory-created@test.com']);

            $this->assertDatabaseHas('users', [
                'email' => 'factory-created@test.com',
                'is_staff' => false,
            ]);
        });
    });

    describe('update', function () {
        it('updates mutable fields and returns updated entity', function () {
            $user = repoCreateTenantUser($this->tenantA, ['first_name' => 'Old', 'last_name_paternal' => 'Name']);

            $entity = $this->repo->findById($user->id);
            $entity->activate(); // triggers status mutation
            $result = $this->repo->update($entity);

            expect($result)->toBeInstanceOf(UserEntity::class);
            expect($result->getStatus())->toBe('active');
        });
    });

    describe('delete', function () {
        it('soft-deletes the user and returns true', function () {
            $user = repoCreateTenantUser($this->tenantA);

            $result = $this->repo->delete($user->id);

            expect($result)->toBeTrue();
            $this->assertSoftDeleted('users', ['id' => $user->id]);
        });

        it('returns false when user does not exist in current tenant', function () {
            $user = repoCreateTenantUser($this->tenantB);

            $result = $this->repo->delete($user->id);

            expect($result)->toBeFalse();
        });
    });

    describe('tenant isolation', function () {
        it('does not expose users from a different tenant through any lookup method', function () {
            $userB = repoCreateTenantUser($this->tenantB, [
                'email' => 'secret@tenantb.com',
                'google_id' => 'g-secret',
            ]);

            expect($this->repo->findByEmail('secret@tenantb.com'))->toBeNull();
            expect($this->repo->findById($userB->id))->toBeNull();
            expect($this->repo->findByUuid($userB->uuid))->toBeNull();
            expect($this->repo->findByGoogleId('g-secret'))->toBeNull();
        });
    });
});
