<?php

namespace App\Repositories\Moderation;

use App\Domain\Moderation\ReportStatus;
use App\Models\Moderation\ContentReport;
use Illuminate\Support\Collection;

class ContentReportRepository
{
    public function exists(int $reporterId, string $subjectType, int $subjectId): bool
    {
        return ContentReport::query()
            ->where('reporter_id', $reporterId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    public function create(
        int $reporterId,
        string $subjectType,
        int $subjectId,
        int $subjectUserId,
        string $reason,
        ?string $details,
    ): ContentReport {
        return ContentReport::query()->create([
            'reporter_id' => $reporterId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_user_id' => $subjectUserId,
            'reason' => $reason,
            'details' => $details,
            'status' => ReportStatus::Open,
        ]);
    }

    /**
     * @return Collection<int, ContentReport>
     */
    public function open(): Collection
    {
        return ContentReport::query()
            ->with(['reporter.player', 'subjectUser.player'])
            ->where('status', ReportStatus::Open->value)
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?ContentReport
    {
        return ContentReport::query()->find($id);
    }

    public function mark(ContentReport $report, ReportStatus $status): void
    {
        $report->status = $status;
        $report->save();
    }

    public function resolveSubject(string $subjectType, int $subjectId): void
    {
        ContentReport::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', ReportStatus::Open->value)
            ->update(['status' => ReportStatus::Resolved->value]);
    }

    public function resolveUser(int $subjectUserId): void
    {
        ContentReport::query()
            ->where('subject_user_id', $subjectUserId)
            ->where('status', ReportStatus::Open->value)
            ->update(['status' => ReportStatus::Resolved->value]);
    }
}
