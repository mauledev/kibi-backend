<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controller;
use App\Http\Requests\Staff\ApproveSuperadminApprovalRequest;
use App\Http\Requests\Staff\ProposeSuperadminApprovalRequest;
use App\Http\Requests\Staff\RejectSuperadminApprovalRequest;
use App\Http\Resources\Staff\SuperadminApprovalResource;
use App\Http\Response\ApiResponse;
use App\Modules\Auth\Domain\Exceptions\InvalidTwoFactorCodeException;
use App\Modules\Staff\Application\UseCases\ApproveSuperadminCreation\ApproveSuperadminCreationInput;
use App\Modules\Staff\Application\UseCases\ApproveSuperadminCreation\ApproveSuperadminCreationUseCase;
use App\Modules\Staff\Application\UseCases\GetSuperadminApproval\GetSuperadminApprovalUseCase;
use App\Modules\Staff\Application\UseCases\ListSuperadminApprovals\ListSuperadminApprovalsUseCase;
use App\Modules\Staff\Application\UseCases\ProposeSuperadminCreation\ProposeSuperadminCreationInput;
use App\Modules\Staff\Application\UseCases\ProposeSuperadminCreation\ProposeSuperadminCreationUseCase;
use App\Modules\Staff\Application\UseCases\RejectSuperadminCreation\RejectSuperadminCreationInput;
use App\Modules\Staff\Application\UseCases\RejectSuperadminCreation\RejectSuperadminCreationUseCase;
use App\Modules\Staff\Domain\Enums\SuperadminApprovalStatusEnum;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestExpiredException;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestNotFoundException;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestNotPendingException;
use App\Modules\Staff\Domain\Exceptions\ApproverNotTwoFactorEnrolledException;
use App\Modules\Staff\Domain\Exceptions\DuplicatePendingApprovalException;
use App\Modules\Staff\Domain\Exceptions\SelfApprovalForbiddenException;
use App\Modules\Staff\Domain\Exceptions\StaffEmailAlreadyTakenException;
use App\Modules\Staff\Domain\Exceptions\StaffRoleNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Superadmin dual-control creation ceremony: propose → a DIFFERENT
 * superadmin approves with a fresh TOTP (or rejects with a reason) → only then
 * the account is created. All routes sit behind `auth:sanctum` + `staff.superadmin`.
 */
class SuperadminApprovalController extends Controller
{
    /**
     * List superadmin approval requests (paginated queue).
     * GET /staff/superadmin/approvals
     *
     * Query params:
     *   page     — page number (default 1)
     *   per_page — items per page (default 20, max 100)
     *   status   — optional filter (pending_approval|approved|rejected|expired);
     *              invalid values are ignored
     */
    public function index(Request $request, ListSuperadminApprovalsUseCase $useCase): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '20')));
        $page = max(1, (int) $request->query('page', '1'));
        $status = SuperadminApprovalStatusEnum::tryFrom((string) $request->query('status', ''));

        $result = $useCase->execute($page, $perPage, $status);

        $items = array_map(
            fn ($item) => (new SuperadminApprovalResource($item))->resolve(),
            $result['items'],
        );

        return ApiResponse::paginated($items, [
            'total' => $result['total'],
            'per_page' => $result['per_page'],
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
        ]);
    }

    /**
     * Get a single approval request by UUID.
     * GET /staff/superadmin/approvals/{uuid}
     */
    public function show(string $uuid, GetSuperadminApprovalUseCase $useCase): JsonResponse
    {
        try {
            return ApiResponse::success(new SuperadminApprovalResource($useCase->execute($uuid)));
        } catch (ApprovalRequestNotFoundException) {
            return ApiResponse::notFound();
        }
    }

    /**
     * Propose creating a superadmin (step 1 of the ceremony). Persists a pending
     * request only — no user is created until a different superadmin approves.
     * POST /staff/superadmin/approvals
     *
     * Responds 201 with the pending request. 409 when the candidate email already
     * belongs to a user or already has a live pending request.
     */
    public function store(ProposeSuperadminApprovalRequest $request, ProposeSuperadminCreationUseCase $useCase): JsonResponse
    {
        try {
            $approval = $useCase->execute(new ProposeSuperadminCreationInput(
                justification: $request->validated('justification'),
                candidateEmail: $request->validated('personal_data.email'),
                candidateFirstName: $request->validated('personal_data.first_name'),
                candidateLastNamePaternal: $request->validated('personal_data.last_name_paternal'),
                candidateLastNameMaternal: $request->validated('personal_data.last_name_maternal'),
                candidatePhone: $request->validated('personal_data.phone'),
                proposedBy: (int) $request->user()->id,
            ));

            return ApiResponse::created(new SuperadminApprovalResource($approval));
        } catch (StaffEmailAlreadyTakenException|DuplicatePendingApprovalException $e) {
            return ApiResponse::conflict($e->getMessage(), ['personal_data.email' => [$e->getMessage()]]);
        }
    }

    /**
     * Approve a pending request with a fresh TOTP — creates the superadmin account.
     * POST /staff/superadmin/approvals/{uuid}/approve
     *
     * Responds 200 with the approved request. 403 on self-approval, 404 unknown,
     * 409 already resolved / expired / candidate email taken, 422 invalid TOTP or
     * approver without confirmed 2FA.
     */
    public function approve(string $uuid, ApproveSuperadminApprovalRequest $request, ApproveSuperadminCreationUseCase $useCase): JsonResponse
    {
        try {
            $approval = $useCase->execute(new ApproveSuperadminCreationInput(
                requestUuid: $uuid,
                approvedBy: (int) $request->user()->id,
                code: $request->validated('code'),
            ));

            return ApiResponse::success(new SuperadminApprovalResource($approval));
        } catch (ApprovalRequestNotFoundException) {
            return ApiResponse::notFound();
        } catch (SelfApprovalForbiddenException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ApprovalRequestNotPendingException|ApprovalRequestExpiredException $e) {
            return ApiResponse::conflict($e->getMessage());
        } catch (StaffEmailAlreadyTakenException $e) {
            return ApiResponse::conflict($e->getMessage(), ['personal_data.email' => [$e->getMessage()]]);
        } catch (ApproverNotTwoFactorEnrolledException|InvalidTwoFactorCodeException|StaffRoleNotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Reject a pending request with an explicit reason (no TOTP required).
     * POST /staff/superadmin/approvals/{uuid}/reject
     *
     * Responds 200 with the rejected request. 403 on self-reject, 404 unknown,
     * 409 already resolved / expired.
     */
    public function reject(string $uuid, RejectSuperadminApprovalRequest $request, RejectSuperadminCreationUseCase $useCase): JsonResponse
    {
        try {
            $approval = $useCase->execute(new RejectSuperadminCreationInput(
                requestUuid: $uuid,
                rejectedBy: (int) $request->user()->id,
                reason: $request->validated('reason'),
            ));

            return ApiResponse::success(new SuperadminApprovalResource($approval));
        } catch (ApprovalRequestNotFoundException) {
            return ApiResponse::notFound();
        } catch (SelfApprovalForbiddenException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ApprovalRequestNotPendingException|ApprovalRequestExpiredException $e) {
            return ApiResponse::conflict($e->getMessage());
        }
    }
}
