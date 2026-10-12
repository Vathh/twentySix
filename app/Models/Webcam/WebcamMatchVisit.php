<?php

namespace App\Models\Webcam;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebcamMatchVisit extends Model
{
    protected $fillable = [
        'webcam_match_id',
        'user_id',
        'leg_number',
        'visit_number',
        'score',
        'remaining_before',
        'remaining_after',
        'darts_in_visit',
        'bust',
        'closed_leg',
    ];

    protected $casts = [
        'bust' => 'boolean',
        'closed_leg' => 'boolean',
    ];

    /** @return BelongsTo<WebcamMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(WebcamMatch::class, 'webcam_match_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
