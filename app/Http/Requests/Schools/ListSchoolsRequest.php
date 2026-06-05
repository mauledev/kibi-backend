<?php

namespace App\Http\Requests\Schools;

use App\Modules\Schools\Domain\Enums\SchoolListFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSchoolsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate authorization happens in the controller via $this->authorize()
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::enum(SchoolListFilter::class)],
        ];
    }

    /**
     * Resolve the validated `status` query param to the typed enum case.
     *
     * `Rule::enum` already rejects invalid values with 422 upstream, so by the
     * time we get here `$value` is either a valid enum value or null (omitted).
     * The `tryFrom() ?? Active` fallback is defence-in-depth: if validation is
     * ever bypassed, an unknown value silently degrades to the default rather
     * than throwing — and an omitted param maps to the same default.
     */
    public function statusFilter(): SchoolListFilter
    {
        $value = $this->validated('status');

        return $value !== null
            ? (SchoolListFilter::tryFrom($value) ?? SchoolListFilter::Active)
            : SchoolListFilter::Active;
    }
}
