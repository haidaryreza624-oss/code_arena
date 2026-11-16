<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN    = 'admin';
    public const ROLE_OPERATOR = 'operator';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'    => 'hashed',
            'email_verified_at' => 'datetime',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function createdContests(): HasMany
    {
        return $this->hasMany(Contest::class, 'created_by');
    }

    public function approvedContests(): HasMany
    {
        return $this->hasMany(Contest::class, 'approved_by');
    }

    public function createdProblems(): HasMany
    {
        return $this->hasMany(Problem::class, 'created_by');
    }

    public function answeredQuestions(): HasMany
    {
        return $this->hasMany(ContestQuestion::class, 'answered_by');
    }
}