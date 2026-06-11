<?php

use App\Modules\MemberOnboarding\Application\UseCases\ComputeOnboardingProgress\ComputeOnboardingProgressInput;
use App\Modules\MemberOnboarding\Application\UseCases\ComputeOnboardingProgress\ComputeOnboardingProgressUseCase;

beforeEach(function () {
    $this->useCase = new ComputeOnboardingProgressUseCase;
});

/** Required (MVP): first_name, last_name_paternal, email, phone. */
function progressFor(array $fields): array
{
    return (new ComputeOnboardingProgressUseCase)->execute(
        new ComputeOnboardingProgressInput(fields: $fields, roleSlugs: [])
    );
}

it('is 100% and complete when all required fields are present', function () {
    $result = progressFor([
        'first_name' => 'Ana',
        'last_name_paternal' => 'García',
        'email' => 'ana@example.com',
        'phone' => '+52 55 1234 5678',
    ]);

    expect($result['percent'])->toBe(100);
    expect($result['is_complete'])->toBeTrue();
    expect($result['missing'])->toBe([]);
});

it('is 75% with one missing field (freshly invited user without phone)', function () {
    $result = progressFor([
        'first_name' => 'Ana',
        'last_name_paternal' => 'García',
        'email' => 'ana@example.com',
        'phone' => null,
    ]);

    expect($result['percent'])->toBe(75);
    expect($result['is_complete'])->toBeFalse();
    expect($result['missing'])->toBe(['phone']);
    expect($result['completed'])->toContain('email');
});

it('treats empty string as missing', function () {
    $result = progressFor([
        'first_name' => 'Ana',
        'last_name_paternal' => '',
        'email' => 'ana@example.com',
        'phone' => '555',
    ]);

    expect($result['missing'])->toBe(['last_name_paternal']);
    expect($result['percent'])->toBe(75);
});

it('counts absent keys as missing', function () {
    $result = progressFor(['email' => 'ana@example.com']);

    expect($result['completed'])->toBe(['email']);
    expect($result['missing'])->toBe(['first_name', 'last_name_paternal', 'phone']);
    expect($result['percent'])->toBe(25);
});
