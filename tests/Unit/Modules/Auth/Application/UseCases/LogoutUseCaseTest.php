<?php

use App\Modules\Auth\Application\UseCases\Logout\LogoutUseCase;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;

describe('LogoutUseCase', function () {
    beforeEach(function () {
        $this->tokens = Mockery::mock(TokenServiceInterface::class);
        $this->useCase = new LogoutUseCase($this->tokens);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('calls revokeById with the given token ID', function () {
        $this->tokens->shouldReceive('revokeById')
            ->once()
            ->with(42);

        $this->useCase->execute(42);
    });

    it('delegates entirely to the token service', function () {
        $this->tokens->shouldReceive('revokeById')
            ->once()
            ->with(7);

        $this->useCase->execute(7);
    });
});
