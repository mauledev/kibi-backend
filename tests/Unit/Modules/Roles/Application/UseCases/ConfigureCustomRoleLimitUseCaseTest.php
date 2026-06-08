<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Application\UseCases\ConfigureCustomRoleLimit\ConfigureCustomRoleLimitInput;
use App\Modules\Roles\Application\UseCases\ConfigureCustomRoleLimit\ConfigureCustomRoleLimitUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;

describe('ConfigureCustomRoleLimitUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new ConfigureCustomRoleLimitUseCase(
            $this->roleRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    it('throws InvalidArgumentException when limit is 0', function () {
        $input = new ConfigureCustomRoleLimitInput(
            actorUserId: 1,
            tenantId: 10,
            limit: 0,
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws InvalidArgumentException when limit is 51', function () {
        $input = new ConfigureCustomRoleLimitInput(
            actorUserId: 1,
            tenantId: 10,
            limit: 51,
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidArgumentException::class);
    });

    it('updates custom_roles_limit when limit is 1 (lower boundary)', function () {
        $input = new ConfigureCustomRoleLimitInput(
            actorUserId: 1,
            tenantId: 10,
            limit: 1,
        );

        $this->roleRepo->shouldReceive('setCustomRolesLimit')
            ->once()
            ->with(10, 1);

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });

    it('updates custom_roles_limit when limit is 50 (upper boundary)', function () {
        $input = new ConfigureCustomRoleLimitInput(
            actorUserId: 1,
            tenantId: 10,
            limit: 50,
        );

        $this->roleRepo->shouldReceive('setCustomRolesLimit')
            ->once()
            ->with(10, 50);

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });

    it('updates custom_roles_limit for a value between 1 and 50', function () {
        $input = new ConfigureCustomRoleLimitInput(
            actorUserId: 1,
            tenantId: 10,
            limit: 10,
        );

        $this->roleRepo->shouldReceive('setCustomRolesLimit')
            ->once()
            ->with(10, 10);

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });

    it('does not call repo when limit is out of range', function () {
        $input = new ConfigureCustomRoleLimitInput(
            actorUserId: 1,
            tenantId: 10,
            limit: 0,
        );

        $this->roleRepo->shouldNotReceive('setCustomRolesLimit');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidArgumentException::class);
    });
});
