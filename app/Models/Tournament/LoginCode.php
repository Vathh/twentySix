<?php

namespace App\Models\Tournament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class LoginCode extends Model
{
    use HasApiTokens;

    public const CODE_LENGTH = 8;

    protected $fillable = [
        'code',
        'tournament_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public static function generate(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = collect(range(1, self::CODE_LENGTH))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->join('');

        if (LoginCode::where('code', $code)->exists()) {
            return self::generate();
        }

        return $code;
    }
}
