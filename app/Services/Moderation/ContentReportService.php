<?php

namespace App\Services\Moderation;

use App\Domain\Moderation\ReportReason;
use App\Domain\Moderation\ReportStatus;
use App\Domain\Moderation\ReportSubjectType;
use App\Models\Moderation\ContentReport;
use App\Models\Users\User;
use App\Repositories\Activity\ActivityCommentRepository;
use App\Repositories\Activity\ActivityRepository;
use App\Repositories\Moderation\ContentReportRepository;
use App\Repositories\User\UserRepository;
use App\Services\Platform\PlatformAdminService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ContentReportService
{
    public function __construct(
        private ContentReportRepository $reports,
        private ActivityCommentRepository $comments,
        private ActivityRepository $activities,
        private UserRepository $users,
        private PlatformAdminService $platformAdmin,
    ) {}

    public function report(
        User $reporter,
        ReportSubjectType $subjectType,
        int $subjectId,
        ReportReason $reason,
        ?string $details,
    ): void {
        $subjectUserId = $this->subjectUserId($reporter, $subjectType, $subjectId);

        if ($this->reports->exists((int) $reporter->id, $subjectType->value, $subjectId)) {
            throw new DomainException('To zgłoszenie już jest.');
        }

        $this->reports->create(
            (int) $reporter->id,
            $subjectType->value,
            $subjectId,
            $subjectUserId,
            $reason->value,
            $this->details($details),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openReports(): array
    {
        $reports = $this->reports->open();
        $commentIds = $reports
            ->where('subject_type', ReportSubjectType::Comment->value)
            ->map(fn (ContentReport $report) => (int) $report->subject_id)
            ->all();
        $bodies = $this->comments->bodiesFor($commentIds);
        $postIds = $reports
            ->where('subject_type', ReportSubjectType::Post->value)
            ->map(fn (ContentReport $report) => (int) $report->subject_id)
            ->all();
        $sentences = $this->activities->sentencesFor($postIds);

        return $reports->map(function (ContentReport $report) use ($bodies, $sentences) {
            $reason = ReportReason::tryFrom((string) $report->reason);
            $subjectId = (int) $report->subject_id;

            return [
                'id' => (int) $report->id,
                'reason' => $reason?->label() ?? (string) $report->reason,
                'details' => $report->details,
                'subjectType' => $report->subject_type,
                'subjectLabel' => $this->subjectLabel($report->subject_type, $subjectId, $bodies, $sentences),
                'reporterName' => $this->name($report->reporter),
                'subjectName' => $this->name($report->subjectUser),
                'createdAt' => $report->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'canDeleteComment' => $report->subject_type === ReportSubjectType::Comment->value && isset($bodies[$subjectId]),
                'canDeletePost' => $report->subject_type === ReportSubjectType::Post->value && isset($sentences[$subjectId]),
                'canBan' => $report->subjectUser !== null && ! $report->subjectUser->isPlatformAdmin(),
            ];
        })->all();
    }

    public function dismiss(int $reportId): void
    {
        $report = $this->openReport($reportId);
        $this->reports->mark($report, ReportStatus::Dismissed);
    }

    public function deleteComment(int $reportId): void
    {
        $report = $this->openReport($reportId);
        if ($report->subject_type !== ReportSubjectType::Comment->value) {
            throw new DomainException('To zgłoszenie nie dotyczy komentarza.');
        }

        $comment = $this->comments->find((int) $report->subject_id);
        if ($comment !== null) {
            $this->comments->deleteWithReplies($comment);
        }

        $this->reports->resolveSubject(ReportSubjectType::Comment->value, (int) $report->subject_id);
    }

    public function deletePost(int $reportId): void
    {
        $report = $this->openReport($reportId);
        if ($report->subject_type !== ReportSubjectType::Post->value) {
            throw new DomainException('To zgłoszenie nie dotyczy wpisu.');
        }

        $this->activities->deleteById((int) $report->subject_id);
        $this->reports->resolveSubject(ReportSubjectType::Post->value, (int) $report->subject_id);
    }

    public function ban(int $reportId): void
    {
        $report = $this->openReport($reportId);
        $this->platformAdmin->setBanned((int) $report->subject_user_id, true);
        $this->reports->resolveUser((int) $report->subject_user_id);
    }

    private function openReport(int $reportId): ContentReport
    {
        $report = $this->reports->find($reportId);
        if ($report === null || $report->status !== ReportStatus::Open) {
            throw new DomainException('Nie ma takiego zgłoszenia.');
        }

        return $report;
    }

    private function subjectUserId(User $reporter, ReportSubjectType $subjectType, int $subjectId): int
    {
        if ($subjectType === ReportSubjectType::User) {
            if ($subjectId === (int) $reporter->id) {
                throw new DomainException('Nie możesz zgłosić siebie.');
            }
            $this->requireUser($subjectId);

            return $subjectId;
        }

        if ($subjectType === ReportSubjectType::Post) {
            $activity = $this->activities->find($subjectId);
            if ($activity === null || $activity->actor_user_id === null) {
                throw new DomainException('Nie ma takiego wpisu.');
            }
            if ((int) $activity->actor_user_id === (int) $reporter->id) {
                throw new DomainException('Nie możesz zgłosić siebie.');
            }

            return (int) $activity->actor_user_id;
        }

        $comment = $this->comments->find($subjectId);
        if ($comment === null) {
            throw new DomainException('Nie ma takiego komentarza.');
        }
        if ((int) $comment->user_id === (int) $reporter->id) {
            throw new DomainException('Nie możesz zgłosić siebie.');
        }

        return (int) $comment->user_id;
    }

    private function requireUser(int $userId): void
    {
        try {
            $this->users->findModel($userId);
        } catch (ModelNotFoundException) {
            throw new DomainException('Nie ma takiego gracza.');
        }
    }

    private function name(?User $user): string
    {
        if ($user === null) {
            return 'Gracz usunięty';
        }

        return (string) ($user->player->name ?? $user->email);
    }

    private function details(?string $details): ?string
    {
        $details = trim((string) $details);

        return $details === '' ? null : $details;
    }

    /**
     * @param  array<int, string>  $bodies
     * @param  array<int, string>  $sentences
     */
    private function subjectLabel(string $subjectType, int $subjectId, array $bodies, array $sentences): string
    {
        if ($subjectType === ReportSubjectType::Comment->value) {
            return isset($bodies[$subjectId])
                ? 'Komentarz: '.$this->snippet($bodies[$subjectId])
                : 'Komentarz został usunięty';
        }

        if ($subjectType === ReportSubjectType::Post->value) {
            return isset($sentences[$subjectId])
                ? 'Wpis: '.$this->snippet($sentences[$subjectId])
                : 'Wpis został usunięty';
        }

        return 'Gracz';
    }

    private function snippet(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? '');
        if (mb_strlen($body) <= 80) {
            return $body;
        }

        return mb_substr($body, 0, 77).'…';
    }
}
