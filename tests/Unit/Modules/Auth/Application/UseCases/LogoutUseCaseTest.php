<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LogoutInput;
use App\Modules\Auth\Application\UseCases\Logout\LogoutUseCase;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;

describe('LogoutUseCase', function () {
    beforeEach(function () {
        $this->tokens = Mockery::mock(TokenServiceInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new LogoutUseCase($this->tokens, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('calls revokeById with the given token ID', function () {
        $this->tokens->shouldReceive('revokeById')->once()->with(42);
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new LogoutInput(tokenId: 42));
    });

    it('writes an auth.logout audit entry including the token_id', function () {
        $this->tokens->shouldReceive('revokeById')->once()->with(7);

        $captured = null;
        $this->audit->shouldReceive('log')->once()->withArgs(function (...$args) use (&$captured) {
            $captured = $args;

            return $args[0] === 'auth.logout';
        });

        $this->useCase->execute(new LogoutInput(
            tokenId: 7,
            userId: 99,
            tenantId: 3,
        ));

        expect($captured[1])->toBe(99);                // userId
        expect($captured[5])->toBe(['token_id' => 7]); // struct_after
        expect($captured[6])->toBe(3);                 // tenantId
    });
});
