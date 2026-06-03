<?php

namespace App\Modules\Schools\Application\UseCases\ListSchools;

/**
 * Input DTO for the ListSchools use case.
 *
 * Tenant context is resolved internally by the repository via TenantContext
 * injection. The optional `statusFilter` lets callers narrow the result set
 * by lifecycle state, including soft-deleted (deactivated) schools.
 *
 * Allowed values for `statusFilter`:
 *   - null            default — non-deleted schools, no `status` column filter
 *   - 'active'        non-deleted AND status = 'active'
 *   - 'suspended'     non-deleted AND status = 'suspended'
 *   - 'deactivated'   only soft-deleted schools (deleted_at IS NOT NULL)
 *   - 'all'           every row, including soft-deleted, no `status` filter
 */
final readonly class ListSchoolsInput
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DEACTIVATED = 'deactivated';

    public const STATUS_ALL = 'all';

    /** @var list<string> */
    public const ALLOWED_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_DEACTIVATED,
        self::STATUS_ALL,
    ];

    public function __construct(
        public ?string $statusFilter = null,
    ) {}
}
