<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;  // ← AJOUTEZ CETTE LIGNE

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;  // ← AJOUTEZ HasApiTokens ICI

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'userable_type',
        'userable_id',
        'role',
        'user_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relation polymorphique avec Driver ou Client
     */
    public function userable()
    {
        return $this->morphTo();
    }

    /**
     * Vérifier si l'utilisateur est un driver
     */
    public function isDriver(): bool
    {
        return $this->userable_type === Driver::class;
    }

    /**
     * Vérifier si l'utilisateur est un client
     */
    public function isClient(): bool
    {
        return $this->userable_type === Client::class;
    }

    /**
     * Vérifier si l'utilisateur est un admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}