<?php

use App\Modules\Tutor\Domain\Entities\Tutor;

describe('Tutor entity', function () {

    /**
     * Instantiate a Tutor entity with sensible defaults.
     *
     * @param  array<string, mixed>  $overrides
     */
    function makeTutor(array $overrides = []): Tutor
    {
        return new Tutor(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'tutor-profile-uuid',
            userId: $overrides['userId'] ?? 1,
            userUuid: $overrides['userUuid'] ?? 'user-uuid-1',
            email: $overrides['email'] ?? 'tutor@example.com',
            firstName: $overrides['firstName'] ?? 'María',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'Rodríguez',
            lastNameMaternal: array_key_exists('lastNameMaternal', $overrides) ? $overrides['lastNameMaternal'] : 'López',
            phone: array_key_exists('phone', $overrides) ? $overrides['phone'] : null,
            status: $overrides['status'] ?? 'pending',
            occupation: array_key_exists('occupation', $overrides) ? $overrides['occupation'] : null,
            createdAt: new DateTime,
        );
    }

    // -------------------------------------------------------------------------
    // getFullName
    // -------------------------------------------------------------------------

    it('getFullName includes maternal last name when present', function () {
        $tutor = makeTutor([
            'firstName' => 'Juan',
            'lastNamePaternal' => 'García',
            'lastNameMaternal' => 'López',
        ]);

        expect($tutor->getFullName())->toBe('Juan García López');
    });

    it('getFullName omits maternal last name when null', function () {
        $tutor = makeTutor([
            'firstName' => 'Carlos',
            'lastNamePaternal' => 'Martínez',
            'lastNameMaternal' => null,
        ]);

        expect($tutor->getFullName())->toBe('Carlos Martínez');
    });

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    it('returns uuid via getUuid', function () {
        $tutor = makeTutor(['uuid' => 'abc-123']);

        expect($tutor->getUuid())->toBe('abc-123');
    });

    it('returns id via getId', function () {
        $tutor = makeTutor(['id' => 42]);

        expect($tutor->getId())->toBe(42);
    });

    it('returns userId via getUserId', function () {
        $tutor = makeTutor(['userId' => 10]);

        expect($tutor->getUserId())->toBe(10);
    });

    it('returns userUuid via getUserUuid', function () {
        $tutor = makeTutor(['userUuid' => 'user-abc']);

        expect($tutor->getUserUuid())->toBe('user-abc');
    });

    it('returns firstName via getFirstName', function () {
        $tutor = makeTutor(['firstName' => 'Sofía']);

        expect($tutor->getFirstName())->toBe('Sofía');
    });

    it('returns lastNamePaternal via getLastNamePaternal', function () {
        $tutor = makeTutor(['lastNamePaternal' => 'Hernández']);

        expect($tutor->getLastNamePaternal())->toBe('Hernández');
    });

    it('returns lastNameMaternal via getLastNameMaternal', function () {
        $tutor = makeTutor(['lastNameMaternal' => 'Vega']);

        expect($tutor->getLastNameMaternal())->toBe('Vega');
    });

    it('returns null for lastNameMaternal when not provided', function () {
        $tutor = makeTutor(['lastNameMaternal' => null]);

        expect($tutor->getLastNameMaternal())->toBeNull();
    });

    it('returns email via getEmail', function () {
        $tutor = makeTutor(['email' => 'tutor@colegio.mx']);

        expect($tutor->getEmail())->toBe('tutor@colegio.mx');
    });

    it('returns phone via getPhone', function () {
        $tutor = makeTutor(['phone' => '5551234567']);

        expect($tutor->getPhone())->toBe('5551234567');
    });

    it('returns null for phone when not provided', function () {
        $tutor = makeTutor(['phone' => null]);

        expect($tutor->getPhone())->toBeNull();
    });

    it('returns status via getStatus', function () {
        $tutor = makeTutor(['status' => 'active']);

        expect($tutor->getStatus())->toBe('active');
    });

    it('returns occupation via getOccupation', function () {
        $tutor = makeTutor(['occupation' => 'Engineer']);

        expect($tutor->getOccupation())->toBe('Engineer');
    });

    it('returns null for occupation when not provided', function () {
        $tutor = makeTutor(['occupation' => null]);

        expect($tutor->getOccupation())->toBeNull();
    });

    it('returns createdAt as a DateTime instance', function () {
        $tutor = makeTutor();

        expect($tutor->getCreatedAt())->toBeInstanceOf(DateTime::class);
    });
});
