<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload to approve a superadmin creation request: the approver signs the
 * decision with a fresh TOTP (`code`, same field name as the 2FA login step).
 *
 * Authorization is enforced by the `staff.superadmin` middleware on the route group.
 */
class ApproveSuperadminApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
