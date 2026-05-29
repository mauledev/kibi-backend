<?php

use App\Models\User;
use App\Modules\Auth\Infrastructure\Repositories\EloquentStaffUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EloquentStaffUserRepository', function () {
    beforeEach(function () {
        $this->repo = new EloquentStaffUserRepository;
    });

    describe('findByEmail', function () {
        it('returns staff user when email matches a user with is_staff true', function () {
            User::factory()->staff()->create(['email' => 'staff@kibi.com']);

            $result = $this->repo->findByEmail('staff@kibi.com');

            expect($result)->not->toBeNull();
            expect($result->getEmail())->toBe('staff@kibi.com');
            expect($result->isStaff())->toBeTrue();
        });

        it('returns null when email belongs to a non-staff user', function () {
            User::factory()->create(['email' => 'tenant@test.com']);

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

        it('returns null when id belongs to a non-staff user', function () {
            $nonStaff = User::factory()->create();

            $result = $this->repo->findById($nonStaff->id);

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

        it('returns null when uuid belongs to a non-staff user', function () {
            $nonStaff = User::factory()->create();

            $result = $this->repo->findByUuid($nonStaff->uuid);

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

        it('returns null when google_id belongs to a non-staff user', function () {
            User::factory()->create(['google_id' => 'nonstaf-google', 'email' => 'tg@test.com']);

            $result = $this->repo->findByGoogleId('nonstaf-google');

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

        it('returns null when microsoft_id belongs to a non-staff user', function () {
            User::factory()->create(['microsoft_id' => 'nonstaff-ms', 'email' => 'tm@test.com']);

            $result = $this->repo->findByMicrosoftId('nonstaff-ms');

            expect($result)->toBeNull();
        });
    });

    describe('save', function () {
        it('creates a staff user record with is_staff true using factory', function () {
            $staff = User::factory()->staff()->create(['email' => 'new-staff@kibi.com']);

            expect($staff->is_staff)->toBeTrue();
            $this->assertDatabaseHas('users', ['email' => 'new-staff@kibi.com', 'is_staff' => true]);
        });
    });

    describe('delete', function () {
        it('soft-deletes a staff user by id', function () {
            $staff = User::factory()->staff()->create();

            $result = $this->repo->delete($staff->id);

            expect($result)->toBeTrue();
            $this->assertSoftDeleted('users', ['id' => $staff->id]);
        });

        it('does not delete a non-staff user', function () {
            $nonStaff = User::factory()->create();

            $result = $this->repo->delete($nonStaff->id);

            expect($result)->toBeFalse();
        });
    });

    describe('isolation', function () {
        it('never exposes non-staff users in any lookup', function () {
            $nonStaff = User::factory()->create([
                'email' => 'hidden@test.com',
                'google_id' => 'g-hidden',
                'microsoft_id' => 'ms-hidden',
            ]);

            expect($this->repo->findByEmail('hidden@test.com'))->toBeNull();
            expect($this->repo->findById($nonStaff->id))->toBeNull();
            expect($this->repo->findByUuid($nonStaff->uuid))->toBeNull();
            expect($this->repo->findByGoogleId('g-hidden'))->toBeNull();
            expect($this->repo->findByMicrosoftId('ms-hidden'))->toBeNull();
        });
    });
});
