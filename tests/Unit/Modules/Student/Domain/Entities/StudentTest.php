<?php

use App\Modules\Student\Domain\Entities\Student;

describe('Student entity', function () {

    /**
     * Instantiate a Student entity with sensible defaults.
     *
     * @param  array<string, mixed>  $overrides
     */
    function makeStudent(array $overrides = []): Student
    {
        return new Student(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'student-profile-uuid',
            userId: $overrides['userId'] ?? 1,
            userUuid: $overrides['userUuid'] ?? 'user-uuid-1',
            email: $overrides['email'] ?? 'student@example.com',
            firstName: $overrides['firstName'] ?? 'Ana',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'Torres',
            lastNameMaternal: array_key_exists('lastNameMaternal', $overrides) ? $overrides['lastNameMaternal'] : 'Gómez',
            phone: array_key_exists('phone', $overrides) ? $overrides['phone'] : null,
            status: $overrides['status'] ?? 'pending',
            birthDate: array_key_exists('birthDate', $overrides) ? $overrides['birthDate'] : null,
            nationalId: array_key_exists('nationalId', $overrides) ? $overrides['nationalId'] : null,
            enrollmentNumber: array_key_exists('enrollmentNumber', $overrides) ? $overrides['enrollmentNumber'] : null,
            gender: array_key_exists('gender', $overrides) ? $overrides['gender'] : null,
            bloodType: array_key_exists('bloodType', $overrides) ? $overrides['bloodType'] : null,
            groupUuid: array_key_exists('groupUuid', $overrides) ? $overrides['groupUuid'] : null,
            groupName: array_key_exists('groupName', $overrides) ? $overrides['groupName'] : null,
            createdAt: new DateTime,
        );
    }

    // -------------------------------------------------------------------------
    // getFullName
    // -------------------------------------------------------------------------

    it('getFullName includes maternal last name when present', function () {
        $student = makeStudent([
            'firstName' => 'Ana',
            'lastNamePaternal' => 'Torres',
            'lastNameMaternal' => 'Gómez',
        ]);

        expect($student->getFullName())->toBe('Ana Torres Gómez');
    });

    it('getFullName omits maternal last name when null', function () {
        $student = makeStudent([
            'firstName' => 'Luis',
            'lastNamePaternal' => 'Ramírez',
            'lastNameMaternal' => null,
        ]);

        expect($student->getFullName())->toBe('Luis Ramírez');
    });

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    it('returns uuid via getUuid', function () {
        $student = makeStudent(['uuid' => 'abc-123']);

        expect($student->getUuid())->toBe('abc-123');
    });

    it('returns id via getId', function () {
        $student = makeStudent(['id' => 42]);

        expect($student->getId())->toBe(42);
    });

    it('returns userId via getUserId', function () {
        $student = makeStudent(['userId' => 10]);

        expect($student->getUserId())->toBe(10);
    });

    it('returns userUuid via getUserUuid', function () {
        $student = makeStudent(['userUuid' => 'user-abc']);

        expect($student->getUserUuid())->toBe('user-abc');
    });

    it('returns firstName via getFirstName', function () {
        $student = makeStudent(['firstName' => 'Sofía']);

        expect($student->getFirstName())->toBe('Sofía');
    });

    it('returns lastNamePaternal via getLastNamePaternal', function () {
        $student = makeStudent(['lastNamePaternal' => 'Hernández']);

        expect($student->getLastNamePaternal())->toBe('Hernández');
    });

    it('returns lastNameMaternal via getLastNameMaternal', function () {
        $student = makeStudent(['lastNameMaternal' => 'Vega']);

        expect($student->getLastNameMaternal())->toBe('Vega');
    });

    it('returns null for lastNameMaternal when not provided', function () {
        $student = makeStudent(['lastNameMaternal' => null]);

        expect($student->getLastNameMaternal())->toBeNull();
    });

    it('returns email via getEmail', function () {
        $student = makeStudent(['email' => 'student@colegio.mx']);

        expect($student->getEmail())->toBe('student@colegio.mx');
    });

    it('returns phone via getPhone', function () {
        $student = makeStudent(['phone' => '5551234567']);

        expect($student->getPhone())->toBe('5551234567');
    });

    it('returns null for phone when not provided', function () {
        $student = makeStudent(['phone' => null]);

        expect($student->getPhone())->toBeNull();
    });

    it('returns status via getStatus', function () {
        $student = makeStudent(['status' => 'active']);

        expect($student->getStatus())->toBe('active');
    });

    it('returns birthDate as a string via getBirthDate', function () {
        $student = makeStudent(['birthDate' => '2010-05-15']);

        expect($student->getBirthDate())->toBe('2010-05-15');
    });

    it('returns null for birthDate when not provided', function () {
        $student = makeStudent(['birthDate' => null]);

        expect($student->getBirthDate())->toBeNull();
    });

    it('returns nationalId via getNationalId', function () {
        $student = makeStudent(['nationalId' => 'CURP123456']);

        expect($student->getNationalId())->toBe('CURP123456');
    });

    it('returns enrollmentNumber via getEnrollmentNumber', function () {
        $student = makeStudent(['enrollmentNumber' => 'ENR-001']);

        expect($student->getEnrollmentNumber())->toBe('ENR-001');
    });

    it('returns gender via getGender', function () {
        $student = makeStudent(['gender' => 'female']);

        expect($student->getGender())->toBe('female');
    });

    it('returns bloodType via getBloodType', function () {
        $student = makeStudent(['bloodType' => 'O+']);

        expect($student->getBloodType())->toBe('O+');
    });

    it('returns groupUuid via getGroupUuid', function () {
        $student = makeStudent(['groupUuid' => 'grp-uuid-1']);

        expect($student->getGroupUuid())->toBe('grp-uuid-1');
    });

    it('returns null for groupUuid when not assigned', function () {
        $student = makeStudent(['groupUuid' => null]);

        expect($student->getGroupUuid())->toBeNull();
    });

    it('returns groupName via getGroupName', function () {
        $student = makeStudent(['groupName' => '3° A']);

        expect($student->getGroupName())->toBe('3° A');
    });

    it('returns createdAt as a DateTime instance', function () {
        $student = makeStudent();

        expect($student->getCreatedAt())->toBeInstanceOf(DateTime::class);
    });
});
