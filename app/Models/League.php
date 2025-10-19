<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    public $timestamps = true;
    protected $fillable = ['name', 'description'];

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_user_admin');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function relatedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_user');
    }
}
