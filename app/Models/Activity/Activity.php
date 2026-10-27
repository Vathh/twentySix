<?php

namespace App\Models\Activity;

use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = [
        'type',
        'actor_player_id',
        'actor_user_id',
        'organization_id',
        'subject_type',
        'subject_id',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<Player, $this> */
    public function actorPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'actor_player_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
