<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'owner_id',
        'name',
        'email',
        'phone',
        'password',
        'status',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(User::class, 'owner_id');
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class, 'owner_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function usageLimits(): HasMany
    {
        return $this->hasMany(UsageLimit::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    public function facebookAccounts(): HasMany
    {
        return $this->hasMany(FacebookAccount::class);
    }

    public function facebookPages(): HasMany
    {
        return $this->hasMany(FacebookPage::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $roleSlugs = is_array($roles) ? $roles : [$roles];

        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function canAccessApplication(): bool
    {
        if ($this->status === 'suspended') {
            return false;
        }

        if ($this->status === 'pending') {
            return (bool) config('auth.allow_pending_login', false);
        }

        return $this->status === 'active';
    }

    /**
     * For team members returns the owner's user ID; for owners returns own ID.
     * All resource queries (conversations, pages, etc.) scope by this value.
     */
    public function effectiveTenantId(): int
    {
        return $this->owner_id ?? $this->id;
    }

    public function isTeamMember(): bool
    {
        return $this->owner_id !== null;
    }
}
