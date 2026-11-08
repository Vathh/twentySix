<?php

namespace App\Models\Moderation;

use App\Domain\Moderation\ReportStatus;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'subject_type',
        'subject_id',
        'subject_user_id',
        'reason',
        'details',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }
}
