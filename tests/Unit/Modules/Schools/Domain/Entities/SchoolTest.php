<?php

use App\Modules\Schools\Domain\Entities\School;

describe('School', function () {
    function makeSchool(array $overrides = []): School
    {
        return new School(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'uuid-school-1',
            tenantId: $overrides['tenantId'] ?? 10,
            name: $overrides['name'] ?? 'Colegio Kibi',
            slug: $overrides['slug'] ?? 'colegio-kibi',
            address: array_key_exists('address', $overrides) ? $overrides['address'] : null,
            phone: array_key_exists('phone', $overrides) ? $overrides['phone'] : null,
            status: $overrides['status'] ?? 'active',
            createdAt: $overrides['createdAt'] ?? new DateTimeImmutable('2024-01-01'),
            updatedAt: $overrides['updatedAt'] ?? new DateTimeImmutable('2024-01-01'),
            deletedAt: array_key_exists('deletedAt', $overrides) ? $overrides['deletedAt'] : null,
        );
    }

    // --- Status predicates ---

    it('isActive returns true when status is active', function () {
        $school = makeSchool(['status' => 'active']);

        expect($school->isActive())->toBeTrue();
        expect($school->isSuspended())->toBeFalse();
    });

    it('isSuspended returns true when status is suspended', function () {
        $school = makeSchool(['status' => 'suspended']);

        expect($school->isSuspended())->toBeTrue();
        expect($school->isActive())->toBeFalse();
    });

    it('isDeleted returns false when deletedAt is null', function () {
        $school = makeSchool(['deletedAt' => null]);

        expect($school->isDeleted())->toBeFalse();
    });

    it('isDeleted returns true when deletedAt is set', function () {
        $school = makeSchool(['deletedAt' => new DateTimeImmutable('2025-06-01')]);

        expect($school->isDeleted())->toBeTrue();
    });

    // --- rename() ---

    it('rename updates the school name', function () {
        $school = makeSchool(['name' => 'Old Name']);
        $school->rename('New Name');

        expect($school->getName())->toBe('New Name');
    });

    it('rename throws InvalidArgumentException on empty string', function () {
        $school = makeSchool();

        expect(fn () => $school->rename(''))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rename throws InvalidArgumentException on whitespace-only string', function () {
        $school = makeSchool();

        expect(fn () => $school->rename('   '))
            ->toThrow(InvalidArgumentException::class);
    });

    // --- changeSlug() ---

    it('changeSlug updates the school slug', function () {
        $school = makeSchool(['slug' => 'old-slug']);
        $school->changeSlug('new-slug');

        expect($school->getSlug())->toBe('new-slug');
    });

    it('changeSlug throws InvalidArgumentException on empty string', function () {
        $school = makeSchool();

        expect(fn () => $school->changeSlug(''))
            ->toThrow(InvalidArgumentException::class);
    });

    it('changeSlug throws InvalidArgumentException on whitespace-only string', function () {
        $school = makeSchool();

        expect(fn () => $school->changeSlug('   '))
            ->toThrow(InvalidArgumentException::class);
    });

    // --- updateAddress() ---

    it('updateAddress replaces the address with a new array', function () {
        $school = makeSchool(['address' => null]);
        $newAddress = ['street' => 'Av. Reforma', 'state' => 'CDMX'];

        $school->updateAddress($newAddress);

        expect($school->getAddress())->toBe($newAddress);
    });

    it('updateAddress accepts null to clear the address', function () {
        $school = makeSchool(['address' => ['street' => 'Av. Reforma']]);
        $school->updateAddress(null);

        expect($school->getAddress())->toBeNull();
    });

    // --- updatePhone() ---

    it('updatePhone sets the phone number', function () {
        $school = makeSchool(['phone' => null]);
        $school->updatePhone('+52 55 1234 5678');

        expect($school->getPhone())->toBe('+52 55 1234 5678');
    });

    it('updatePhone accepts null to clear the phone', function () {
        $school = makeSchool(['phone' => '+52 55 0000 0000']);
        $school->updatePhone(null);

        expect($school->getPhone())->toBeNull();
    });

    // --- suspend() ---

    it('suspend changes status from active to suspended', function () {
        $school = makeSchool(['status' => 'active']);
        $school->suspend();

        expect($school->isSuspended())->toBeTrue();
    });

    it('suspend is idempotent when already suspended', function () {
        $school = makeSchool(['status' => 'suspended']);
        $school->suspend();
        $school->suspend();

        expect($school->isSuspended())->toBeTrue();
    });

    // --- activate() ---

    it('activate changes status from suspended to active', function () {
        $school = makeSchool(['status' => 'suspended']);
        $school->activate();

        expect($school->isActive())->toBeTrue();
    });

    it('activate is idempotent when already active', function () {
        $school = makeSchool(['status' => 'active']);
        $school->activate();
        $school->activate();

        expect($school->isActive())->toBeTrue();
    });

    // --- Getters (read-only properties) ---

    it('exposes all read properties correctly', function () {
        $createdAt = new DateTimeImmutable('2024-01-01');
        $updatedAt = new DateTimeImmutable('2024-06-01');
        $address = ['street' => 'Av. Universidad'];

        $school = makeSchool([
            'id' => 42,
            'uuid' => 'uuid-test-42',
            'tenantId' => 99,
            'name' => 'Colegio Test',
            'slug' => 'colegio-test',
            'address' => $address,
            'phone' => '+52 55 9999 9999',
            'status' => 'active',
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
            'deletedAt' => null,
        ]);

        expect($school->getId())->toBe(42);
        expect($school->getUuid())->toBe('uuid-test-42');
        expect($school->getTenantId())->toBe(99);
        expect($school->getName())->toBe('Colegio Test');
        expect($school->getSlug())->toBe('colegio-test');
        expect($school->getAddress())->toBe($address);
        expect($school->getPhone())->toBe('+52 55 9999 9999');
        expect($school->getStatus())->toBe('active');
        expect($school->getCreatedAt())->toBe($createdAt);
        expect($school->getUpdatedAt())->toBe($updatedAt);
        expect($school->getDeletedAt())->toBeNull();
    });
});
