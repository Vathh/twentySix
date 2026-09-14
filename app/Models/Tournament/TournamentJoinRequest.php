<?php

namespace App\Models\Tournament;

use App\Enums\TournamentJoinRequestStatus;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentJoinRequest extends Model
{
    protected $fillable = [
        'tournament_id',
        'user_id',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'status' => TournamentJoinRequestStatus::class,
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
