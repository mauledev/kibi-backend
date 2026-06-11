<?php

use App\Modules\Roles\Domain\Enums\RoleExclusionEnum;

describe('RoleExclusionEnum', function () {
    it('returns student and tutor as incompatible slugs for teacher', function () {
        $incompatible = RoleExclusionEnum::getIncompatible('teacher');

        expect($incompatible)->toContain('student');
        expect($incompatible)->toContain('tutor');
    });

    it('returns teacher and tutor as incompatible slugs for student', function () {
        $incompatible = RoleExclusionEnum::getIncompatible('student');

        expect($incompatible)->toContain('teacher');
        expect($incompatible)->toContain('tutor');
    });

    it('returns teacher and student as incompatible slugs for tutor', function () {
        $incompatible = RoleExclusionEnum::getIncompatible('tutor');

        expect($incompatible)->toContain('teacher');
        expect($incompatible)->toContain('student');
    });

    it('returns an empty array for an unknown slug', function () {
        $incompatible = RoleExclusionEnum::getIncompatible('director');

        expect($incompatible)->toBeArray()->toBeEmpty();
    });
});
