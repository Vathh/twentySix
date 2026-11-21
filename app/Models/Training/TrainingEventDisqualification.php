<?php

namespace App\Models\Training;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEventDisqualification extends Model
{
    protected $fillable = [
        'definition_id',
        'user_id',
        'admin_id',
        'disqualified_at',
        'reinstated_at',
    ];

    protected $casts = [
        'disqualified_at' => 'datetime',
        'reinstated_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->reinstated_at === null;
    }

    /** @return BelongsTo<TrainingDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(TrainingDefinition::class, 'definition_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
