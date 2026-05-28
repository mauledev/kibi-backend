<?php

use App\Modules\Roles\Domain\Entities\Permission;

describe('Permission entity', function () {
    function makeDomainPermission(array $overrides = []): Permission
    {
        return new Permission(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'perm-uuid',
            categoryId: $overrides['categoryId'] ?? 5,
            name: $overrides['name'] ?? 'Publish Grade',
            slug: $overrides['slug'] ?? 'grade.publish',
            createdAt: $overrides['createdAt'] ?? new DateTimeImmutable('2024-01-01'),
        );
    }

    it('exposes all read properties correctly', function () {
        $perm = makeDomainPermission();

        expect($perm->getId())->toBe(1);
        expect($perm->getUuid())->toBe('perm-uuid');
        expect($perm->getCategoryId())->toBe(5);
        expect($perm->getName())->toBe('Publish Grade');
        expect($perm->getSlug())->toBe('grade.publish');
    });

    it('exposes createdAt as DateTimeImmutable', function () {
        $perm = makeDomainPermission();

        expect($perm->getCreatedAt())->toBeInstanceOf(DateTimeImmutable::class);
    });

    it('stores the correct slug in the expected format', function () {
        $perm = makeDomainPermission(['slug' => 'payment.approve']);

        expect($perm->getSlug())->toBe('payment.approve');
    });

    it('allows arbitrary category IDs', function () {
        $perm = makeDomainPermission(['categoryId' => 99]);

        expect($perm->getCategoryId())->toBe(99);
    });

    it('stores uuid as string', function () {
        $perm = makeDomainPermission(['uuid' => '00000000-0000-0000-0000-000000000001']);

        expect($perm->getUuid())->toBe('00000000-0000-0000-0000-000000000001');
    });

    it('uses current time as default for createdAt when not provided', function () {
        $before = new DateTimeImmutable;
        $perm = new Permission(id: 1, uuid: 'u', categoryId: 1, name: 'N', slug: 's');
        $after = new DateTimeImmutable;

        expect($perm->getCreatedAt()->getTimestamp())->toBeGreaterThanOrEqual($before->getTimestamp());
        expect($perm->getCreatedAt()->getTimestamp())->toBeLessThanOrEqual($after->getTimestamp());
    });
});
