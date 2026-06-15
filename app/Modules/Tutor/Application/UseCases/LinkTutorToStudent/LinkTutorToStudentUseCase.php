<?php

namespace App\Modules\Tutor\Application\UseCases\LinkTutorToStudent;

use App\Common\Audit\AuditLoggerInterface;
use App\Common\Mail\MailerInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Exceptions\StudentAlreadyLinkedToTutorException;
use App\Modules\Tutor\Domain\Exceptions\TutorNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Link a tutor to a student, creating a row in student_tutors.
 *
 * Magic link logic:
 *   - If the student already has an active tutor link from another tutor, the magic
 *     link was already sent at that time — do not send again.
 *   - If this is the student's first active tutor link AND the student's email has not
 *     been verified, send the magic link so the student can activate their account.
 *
 * The check for an existing link between THIS specific tutor+student pair catches the
 * duplicate via a DB unique constraint violation (partial index on unlinked_at IS NULL),
 * which is translated into StudentAlreadyLinkedToTutorException.
 */
final class LinkTutorToStudentUseCase
{
    public function __construct(
        private readonly TutorRepositoryInterface $tutors,
        private readonly GlobalUserRepositoryInterface $globalUsers,
        private readonly MailerInterface $mailer,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Execute the use case.
     *
     * @throws TutorNotFoundException When the tutor UUID does not exist.
     * @throws \RuntimeException When the student UUID does not exist.
     * @throws StudentAlreadyLinkedToTutorException When the specific tutor+student link already exists.
     */
    public function execute(LinkTutorToStudentInput $input): void
    {
        $tutor = $this->tutors->findByUserUuid($input->tutorUserUuid);

        if ($tutor === null) {
            throw new TutorNotFoundException($input->tutorUserUuid);
        }

        $studentUser = $this->globalUsers->findByUuid($input->studentUserUuid);

        if ($studentUser === null) {
            throw new \RuntimeException("Student user not found: {$input->studentUserUuid}");
        }

        // Determine if the student already has an active link with any tutor.
        // If yes, the magic link was already sent — skip sending again.
        $sendMagicLink = ! $this->tutors->hasActiveLink($studentUser->getId());

        try {
            DB::transaction(function () use ($tutor, $studentUser, $input): void {
                $this->tutors->linkToStudent(
                    tutorUserId: $tutor->getUserId(),
                    studentUserId: $studentUser->getId(),
                    relationship: $input->relationship,
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw new StudentAlreadyLinkedToTutorException;
        }

        if ($sendMagicLink && $studentUser->getEmailVerifiedAt() === null) {
            $this->mailer->sendActivation(
                to: $studentUser->getEmail(),
                activationUrl: $this->buildActivationUrl($studentUser->getUuid(), $input->tenantSlug),
            );
        }

        $this->audit->log(
            action: 'tutor.link_student',
            userId: $tutor->getUserId(),
            entityId: $tutor->getId(),
            structAfter: [
                'tutor_user_uuid' => $tutor->getUserUuid(),
                'student_user_uuid' => $studentUser->getUuid(),
                'relationship' => $input->relationship,
            ],
        );
    }

    /**
     * Build the tenant-aware frontend magic-link URL from a backend signed route.
     * Mirrors InviteUserUseCase (same 'auth.activate' route, 7-day TTL).
     */
    private function buildActivationUrl(string $userUuid, string $tenantSlug): string
    {
        $backendSignedUrl = URL::temporarySignedRoute(
            'auth.activate',
            now()->addHours(168),
            ['user' => $userUuid],
            absolute: false,
        );

        $query = parse_url($backendSignedUrl, PHP_URL_QUERY);

        $baseUrl = config('app.frontend_url') ?? config('app.url');
        $baseUrl = rtrim(str_replace('{APP_TENANT}', $tenantSlug, (string) $baseUrl), '/');

        $frontendUrl = $baseUrl.'/auth/magic';

        return $query ? "{$frontendUrl}?{$query}" : $frontendUrl;
    }
}
