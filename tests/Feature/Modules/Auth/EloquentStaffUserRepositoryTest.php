<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Auth\Domain\Entities\User as UserEntity;
use App\Modules\Auth\Infrastructure\Repositories\EloquentStaffUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EloquentStaffUserRepository', function () {
    beforeEach(function () {
        $this->repo = new EloquentStaffUserRepository;
        $this->tenant = Tenant::factory()->create();
    });

    describe('findByEmail', function () {
        it('returns staff user when email matches a user with null tenant_id', function () {
            User::factory()->staff()->create(['email' => 'staff@kibi.com']);

            $result = $this->repo->findByEmail('staff@kibi.com');

            expect($result)->not->toBeNull();
            expect($result->getEmail())->toBe('staff@kibi.com');
            expect($result->isStaff())->toBeTrue();
        });

        it('returns null when email belongs to a tenant user', function () {
            User::factory()->for($this->tenant)->create(['email' => 'tenant@test.com']);

            $result = $this->repo->findByEmail('tenant@test.com');

            expect($result)->toBeNull();
        });

        it('returns null when email does not exist at all', function () {
            $result = $this->repo->findByEmail('nobody@kibi.com');

            expect($result)->toBeNull();
        });
    });

    describe('findById', function () {
        it('returns staff user when id belongs to a staff user', function () {
            $staff = User::factory()->staff()->create();

            $result = $this->repo->findById($staff->id);

            expect($result)->not->toBeNull();
            expect($result->getId())->toBe($staff->id);
        });

        it('returns null when id belongs to a tenant user', function () {
            $tenantUser = User::factory()->for($this->tenant)->create();

            $result = $this->repo->findById($tenantUser->id);

            expect($result)->toBeNull();
        });
    });

    describe('findByUuid', function () {
        it('returns staff user by uuid', function () {
            $staff = User::factory()->staff()->create();

            $result = $this->repo->findByUuid($staff->uuid);

            expect($result)->not->toBeNull();
            expect($result->getUuid())->toBe($staff->uuid);
        });

        it('returns null when uuid belongs to a tenant user', function () {
            $tenantUser = User::factory()->for($this->tenant)->create();

            $result = $this->repo->findByUuid($tenantUser->uuid);

            expect($result)->toBeNull();
        });
    });

    describe('findByGoogleId', function () {
        it('returns staff user by google_id', function () {
            User::factory()->staff()->create(['google_id' => 'staff-google-123', 'email' => 'sg@kibi.com']);

            $result = $this->repo->findByGoogleId('staff-google-123');

            expect($result)->not->toBeNull();
            expect($result->getGoogleId())->toBe('staff-google-123');
        });

        it('returns null when google_id belongs to a tenant user', function () {
            User::factory()->for($this->tenant)->create(['google_id' => 'tenant-google', 'email' => 'tg@test.com']);

            $result = $this->repo->findByGoogleId('tenant-google');

            expect($result)->toBeNull();
        });
    });

    describe('findByMicrosoftId', function () {
        it('returns staff user by microsoft_id', function () {
            User::factory()->staff()->create(['microsoft_id' => 'staff-ms-abc', 'email' => 'sm@kibi.com']);

            $result = $this->repo->findByMicrosoftId('staff-ms-abc');

            expect($result)->not->toBeNull();
            expect($result->getMicrosoftId())->toBe('staff-ms-abc');
        });

        it('returns null when microsoft_id belongs to a tenant user', function () {
            User::factory()->for($this->tenant)->create(['microsoft_id' => 'tenant-ms', 'email' => 'tm@test.com']);

            $result = $this->repo->findByMicrosoftId('tenant-ms');

            expect($result)->toBeNull();
        });
    });

    describe('save', function () {
        it('creates a staff user record with null tenant_id using factory', function () {
            // The factory sets a UUID explicitly, which the repository toDomain() requires.
            $staff = User::factory()->staff()->create(['email' => 'new-staff@kibi.com']);

            expect($staff->tenant_id)->toBeNull();
            $this->assertDatabaseHas('users', ['email' => 'new-staff@kibi.com', 'tenant_id' => null]);
        });
    });

    describe('delete', function () {
        it('soft-deletes a staff user by id', function () {
            $staff = User::factory()->staff()->create();

            $result = $this->repo->delete($staff->id);

            expect($result)->toBeTrue();
            $this->assertSoftDeleted('users', ['id' => $staff->id]);
        });

        it('does not delete a tenant user', function () {
            $tenantUser = User::factory()->for($this->tenant)->create();

            $result = $this->repo->delete($tenantUser->id);

            expect($result)->toBeFalse();
        });
    });

    describe('tenant isolation', function () {
        it('never exposes tenant-scoped users in any lookup', function () {
            $tenantUser = User::factory()->for($this->tenant)->create([
                'email' => 'hidden@test.com',
                'google_id' => 'g-hidden',
                'microsoft_id' => 'ms-hidden',
            ]);

            expect($this->repo->findByEmail('hidden@test.com'))->toBeNull();
            expect($this->repo->findById($tenantUser->id))->toBeNull();
            expect($this->repo->findByUuid($tenantUser->uuid))->toBeNull();
            expect($this->repo->findByGoogleId('g-hidden'))->toBeNull();
            expect($this->repo->findByMicrosoftId('ms-hidden'))->toBeNull();
        });
    });
});
