<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'email',
        'password',
        'chat_color',
        'locale',
        'lancore_user_id',
        'avatar_url',
        'lancore_synced_at',
        'is_blocked',
        'block_reason',
        'blocked_at',
        'blocked_by',
        'timed_out_until',
        'timeout_reason',
        'timed_out_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
            'lancore_synced_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
            'blocked_at' => 'datetime',
            'timed_out_until' => 'datetime',
        ];
    }

    public function isLanCoreUser(): bool
    {
        return $this->lancore_user_id !== null;
    }

    public function isBlocked(): bool
    {
        return $this->is_blocked;
    }

    public function isTimedOut(): bool
    {
        return $this->timed_out_until !== null && $this->timed_out_until->isFuture();
    }

    public function isModerator(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'moderator']);
    }

    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }
}
