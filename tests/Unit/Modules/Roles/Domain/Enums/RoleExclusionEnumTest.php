<?php

use App\Modules\Roles\Domain\Enums\RoleExclusionEnum;

describe('RoleExclusionEnum', function () {
    it('returns alumno and tutor as incompatible slugs for docente', function () {
        $incompatible = RoleExclusionEnum::getIncompatible('docente');

        expect($incompatible)->toContain('alumno');
        expect($incompatible)->toContain('tutor');
    });

    it('returns docente and tutor as incompatible slugs for alumno', function () {
        $incompatible = RoleExclusionEnum::getIncompatible('alumno');

        expect($incompatible)->toContain('docente');
        expect($incompatible)->toContain('tutor');
    });

    it('returns docente and alumno as incompatible slugs for tutor', function () {
        $incompatible = RoleExclusionEnum::getIncompatible('tutor');

        expect($incompatible)->toContain('docente');
        expect($incompatible)->toContain('alumno');
    });

    it('returns an empty array for an unknown slug', function () {
        $incompatible = RoleExclusionEnum::getIncompatible('director');

        expect($incompatible)->toBeArray()->toBeEmpty();
    });
});
