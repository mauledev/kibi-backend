<?php

namespace App\Modules\Schools\Domain\Criteria;

use App\Modules\Schools\Domain\Enums\SchoolListFilter;

/**
 * Criteria object accepted by SchoolRepository::findAll.
 *
 * Wrapping the query parameters in a single structure means new criteria
 * (pagination, search, sorting) can be added without changing the contract
 * signature or breaking callers. The Criteria is part of Domain because the
 * repository interface lives there — keep this object free of framework
 * dependencies.
 *
 * `status` is non-nullable: omitting the filter is expressed as the explicit
 * default `SchoolListFilter::Active`, and "include every row" is expressed
 * only as `SchoolListFilter::All`. This removes the prior ambiguity where
 * both `null` and `All` could mean the same thing.
 */
final readonly class SchoolListCriteria
{
    public function __construct(
        public SchoolListFilter $status = SchoolListFilter::Active,
    ) {}
}
