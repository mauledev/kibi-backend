<?php

namespace App\Http\Requests\Treasury;

use App\Modules\Treasury\Domain\Criteria\PaymentListCriteria;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by PaymentController::ensureStaff() —
        // the route lives under the staff prefix and rejects non-staff
        // tokens before the use case runs.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::enum(PaymentStatus::class)],
            'company_id' => ['sometimes', 'string', 'uuid'],
            'school_id' => ['sometimes', 'string', 'uuid'],
            'search' => ['sometimes', 'string', 'max:100'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Build the Domain criteria from validated query data. UUID-typed
     * filters (`company_id`, `school_id`) are resolved to internal ids by
     * the caller — this helper only forwards primitives.
     */
    public function toCriteria(?int $tenantId, ?int $schoolId): PaymentListCriteria
    {
        $statusValue = $this->validated('status');
        $search = $this->validated('search');
        $dateFrom = $this->validated('date_from');
        $dateTo = $this->validated('date_to');

        return new PaymentListCriteria(
            status: $statusValue !== null ? PaymentStatus::from($statusValue) : null,
            tenantId: $tenantId,
            schoolId: $schoolId,
            search: $search !== null && $search !== '' ? $search : null,
            dateFrom: $dateFrom !== null ? new DateTimeImmutable($dateFrom) : null,
            dateTo: $dateTo !== null ? new DateTimeImmutable($dateTo) : null,
            page: (int) ($this->validated('page') ?? 1),
        );
    }

    public function companyUuid(): ?string
    {
        return $this->validated('company_id');
    }

    public function schoolUuid(): ?string
    {
        return $this->validated('school_id');
    }
}
