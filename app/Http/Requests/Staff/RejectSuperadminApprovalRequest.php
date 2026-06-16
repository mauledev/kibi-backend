<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload to reject a superadmin creation request with an explicit reason
 * (recorded on the request and in the audit trail).
 *
 * Authorization is enforced by the `staff.superadmin` middleware on the route group.
 */
class RejectSuperadminApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
