<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controller;
use App\Http\Requests\Treasury\ApprovePaymentRequest;
use App\Http\Requests\Treasury\ListPaymentsRequest;
use App\Http\Requests\Treasury\RejectPaymentRequest;
use App\Http\Resources\Treasury\PaymentDetailResource;
use App\Http\Resources\Treasury\PaymentSummaryResource;
use App\Http\Response\ApiResponse;
use App\Models\User as UserModel;
use App\Modules\Treasury\Application\UseCases\ApprovePayment\ApprovePaymentInput;
use App\Modules\Treasury\Application\UseCases\ApprovePayment\ApprovePaymentUseCase;
use App\Modules\Treasury\Application\UseCases\GetPayment\GetPaymentInput;
use App\Modules\Treasury\Application\UseCases\GetPayment\GetPaymentUseCase;
use App\Modules\Treasury\Application\UseCases\ListPayments\ListPaymentsInput;
use App\Modules\Treasury\Application\UseCases\ListPayments\ListPaymentsUseCase;
use App\Modules\Treasury\Application\UseCases\RejectPayment\RejectPaymentInput;
use App\Modules\Treasury\Application\UseCases\RejectPayment\RejectPaymentUseCase;
use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Exceptions\InvalidPaymentTransitionException;
use App\Modules\Treasury\Domain\Exceptions\PaymentNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Treasury payment validation — staff-side controller.
 *
 * In MVP a single Superadmin operates the entire payment validation flow.
 * The Líder/Operador separation from the requirements doc (RF-160..189i)
 * is out of MVP scope. Authorization is enforced by checking `is_staff`:
 * any authenticated staff user (which today means Superadmin) can list,
 * read, approve and reject payments cross-tenant.
 */
class PaymentController extends Controller
{
    /**
     * GET /staff/treasury/payments — Paginated cross-tenant list of payments.
     */
    public function index(
        ListPaymentsRequest $request,
        ListPaymentsUseCase $useCase,
        PaymentRepositoryInterface $repository,
    ): JsonResponse {
        $this->ensureStaff($request);

        $tenantId = $this->resolveCompanyFilter($request->companyUuid(), $repository);
        if ($tenantId === false) {
            // Filter targets a company that doesn't exist — return an empty page.
            return ApiResponse::success([
                'data' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 25,
            ]);
        }

        $schoolId = $this->resolveSchoolFilter($request->schoolUuid(), $repository);
        if ($schoolId === false) {
            return ApiResponse::success([
                'data' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 25,
            ]);
        }

        $result = $useCase->execute(new ListPaymentsInput(
            criteria: $request->toCriteria(tenantId: $tenantId, schoolId: $schoolId),
        ));

        return ApiResponse::success([
            'data' => PaymentSummaryResource::collection($result->items)->resolve(),
            'total' => $result->total,
            'page' => $result->page,
            'per_page' => $result->perPage,
        ]);
    }

    /**
     * GET /staff/treasury/payments/{uuid} — Detail with state log and documents.
     */
    public function show(Request $request, string $uuid, GetPaymentUseCase $useCase): JsonResponse
    {
        $this->ensureStaff($request);

        try {
            $bundle = $useCase->execute(new GetPaymentInput($uuid));

            return ApiResponse::success((new PaymentDetailResource($bundle))->resolve());
        } catch (PaymentNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * POST /staff/treasury/payments/{uuid}/approve — Approve a pending payment.
     */
    public function approve(
        ApprovePaymentRequest $request,
        string $uuid,
        ApprovePaymentUseCase $useCase,
    ): JsonResponse {
        $this->ensureStaff($request);

        /** @var UserModel $user */
        $user = $request->user();

        try {
            $payment = $useCase->execute(new ApprovePaymentInput(
                uuid: $uuid,
                actorUserId: (int) $user->id,
                actorName: $this->resolveActorName($user),
                receivedAmountCents: (int) $request->validated('amount_received_cents'),
                note: $request->validated('note'),
            ));

            return ApiResponse::success(
                (new PaymentSummaryResource($payment))->resolve(),
                'Pago aprobado',
            );
        } catch (PaymentNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (InvalidPaymentTransitionException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }
    }

    /**
     * POST /staff/treasury/payments/{uuid}/reject — Reject a pending payment.
     */
    public function reject(
        RejectPaymentRequest $request,
        string $uuid,
        RejectPaymentUseCase $useCase,
    ): JsonResponse {
        $this->ensureStaff($request);

        /** @var UserModel $user */
        $user = $request->user();

        try {
            $payment = $useCase->execute(new RejectPaymentInput(
                uuid: $uuid,
                actorUserId: (int) $user->id,
                actorName: $this->resolveActorName($user),
                reason: $request->reason(),
                note: $request->validated('note'),
            ));

            return ApiResponse::success(
                (new PaymentSummaryResource($payment))->resolve(),
                'Pago rechazado',
            );
        } catch (PaymentNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (InvalidPaymentTransitionException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }
    }

    /**
     * Resolve a company UUID query param to its internal id.
     *
     * Returns:
     *   - null  → no filter requested
     *   - int   → tenant resolved
     *   - false → filter requested but no matching company exists
     */
    private function resolveCompanyFilter(?string $companyUuid, PaymentRepositoryInterface $repository): int|false|null
    {
        if ($companyUuid === null) {
            return null;
        }

        return $repository->resolveCompanyUuidToId($companyUuid) ?? false;
    }

    /**
     * Resolve a school UUID query param to its internal id. Same tri-state
     * semantics as {@see resolveCompanyFilter()}.
     */
    private function resolveSchoolFilter(?string $schoolUuid, PaymentRepositoryInterface $repository): int|false|null
    {
        if ($schoolUuid === null) {
            return null;
        }

        return $repository->resolveSchoolUuidToId($schoolUuid) ?? false;
    }

    /**
     * Guard rail: every endpoint in this controller requires a staff user.
     * The route group already enforces `auth:sanctum`, but we double-check
     * `is_staff` to refuse tenant-issued tokens reaching the staff prefix.
     */
    private function ensureStaff(Request $request): void
    {
        /** @var UserModel|null $user */
        $user = $request->user();

        if ($user === null || ! $user->is_staff) {
            throw new AccessDeniedHttpException('Staff access required');
        }
    }

    /**
     * Build a display-friendly snapshot of the actor's name from the
     * three-column user identity. Falls back to the email when the name
     * fields are unexpectedly empty.
     */
    private function resolveActorName(UserModel $user): string
    {
        $full = trim(implode(' ', array_filter([
            $user->first_name,
            $user->last_name_paternal,
            $user->last_name_maternal,
        ])));

        return $full !== '' ? $full : (string) $user->email;
    }
}
