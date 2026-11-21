<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Contest-scoped team account.
 * Extends Authenticatable so it can be used with Auth::guard('contestant').
 */
class ContestParticipation extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'contest_id',
        'team_id',
        'username',
        'password',
        'seat-number',
        'credentials_generated_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password'                => 'hashed',
            'seat-number'             => 'integer',
            'credentials_generated_at'=> 'datetime',
            'created_at'              => 'datetime',
            'updated_at'              => 'datetime',
        ];
    }

    // ---------- Relationships ----------

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ContestQuestion::class);
    }

    // ---------- Helpers ----------

    /**
     * Display name on scoreboard / PDF.
     */
    public function teamLabel(): string
    {
        return $this->team?->name ?? $this->username;
    }

    public function hasGeneratedCredentials(): bool
    {
        return $this->credentials_generated_at !== null;
    }
}
