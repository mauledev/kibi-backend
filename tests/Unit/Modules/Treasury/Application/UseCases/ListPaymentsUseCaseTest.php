<?php

use App\Modules\Treasury\Application\UseCases\ListPayments\ListPaymentsInput;
use App\Modules\Treasury\Application\UseCases\ListPayments\ListPaymentsUseCase;
use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Criteria\PaymentListCriteria;
use App\Modules\Treasury\Domain\Criteria\PaymentListResult;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;

describe('ListPaymentsUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(PaymentRepositoryInterface::class);
        $this->useCase = new ListPaymentsUseCase($this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('forwards the criteria from the input to the repository', function () {
        $criteria = new PaymentListCriteria(status: PaymentStatus::Pending);
        $expected = new PaymentListResult(items: [], total: 0, page: 1, perPage: 25);

        $this->repo->shouldReceive('findAll')
            ->once()
            ->with(Mockery::on(fn (PaymentListCriteria $c) => $c->status === PaymentStatus::Pending))
            ->andReturn($expected);

        $result = $this->useCase->execute(new ListPaymentsInput(criteria: $criteria));

        expect($result)->toBe($expected);
    });

    it('returns the PaymentListResult unchanged', function () {
        $expected = new PaymentListResult(items: [], total: 42, page: 3, perPage: 25);

        $this->repo->shouldReceive('findAll')->once()->andReturn($expected);

        $result = $this->useCase->execute(new ListPaymentsInput(criteria: new PaymentListCriteria));

        expect($result->total)->toBe(42);
        expect($result->page)->toBe(3);
        expect($result->perPage)->toBe(25);
    });
});
