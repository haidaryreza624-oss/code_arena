<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class)->orderBy('ordinal');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(ContestParticipation::class);
    }

    /**
     * A team "display name" for the credential PDF / scoreboard.
     * Returns e.g. "Team Alpha (Alice, Bob)".
     */
    public function fullLabel(): string
    {
        $names = $this->members->pluck('name')->implode(', ');

        return $names ? "{$this->name} ({$names})" : $this->name;
    }
}
