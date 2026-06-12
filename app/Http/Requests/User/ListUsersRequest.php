<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query parameters for GET /users (list users).
 *
 * Gate authorization is handled in the controller via $this->authorize('user.view').
 * This request only ensures the authenticated user is logged in before validation.
 */
class ListUsersRequest extends FormRequest
{
    /** Allow any authenticated user; gate check happens in the controller. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Validation rules for list-users query parameters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', 'array'],
            'filter.role' => ['nullable'],
            'filter.status' => ['nullable', 'string', 'in:active,inactive,suspended'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** Sentinel value of filter[role] meaning "users without any active role". */
    public const UNASSIGNED = 'none';

    /**
     * Normalize the filter[role] parameter to a string array.
     *
     * The client may send a single role slug as a string or multiple slugs as
     * an array. This helper always returns a consistent array<string> — empty
     * when the parameter is absent, empty, or the "unassigned" sentinel.
     *
     * @return array<int, string>
     */
    public function roleSlugs(): array
    {
        if ($this->wantsUnassigned()) {
            return [];
        }

        $raw = $this->input('filter.role');

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            return [$raw];
        }

        if (is_array($raw)) {
            return array_values(array_filter($raw, fn ($v) => is_string($v) && $v !== ''));
        }

        return [];
    }

    /**
     * True when the client asked for users with NO active role assignment
     * (filter[role]=none). Mutually exclusive with concrete role slugs.
     */
    public function wantsUnassigned(): bool
    {
        $raw = $this->input('filter.role');

        if (is_string($raw)) {
            return $raw === self::UNASSIGNED;
        }

        if (is_array($raw)) {
            return count($raw) === 1 && reset($raw) === self::UNASSIGNED;
        }

        return false;
    }
}
