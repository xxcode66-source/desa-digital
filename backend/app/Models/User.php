<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasApiTokens, Notifiable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    /** Role yang boleh masuk panel operator/admin (Filament). */
    public const STAFF_ROLES = ['operator', 'kades', 'bendahara_pbb', 'admin'];

    protected $fillable = [
        'village_id', 'nik', 'name', 'phone', 'email', 'password', 'status', 'rt', 'rw',
        'consent_given_at', 'terms_version',
    ];

    protected $hidden = ['password', 'remember_token', 'nik'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'village_id' => 'integer',
            'consent_given_at' => 'datetime',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['scope_rt', 'scope_rw']);
    }

    public function letters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Letter::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles->whereIn('name', $roleNames)->isNotEmpty();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(self::STAFF_ROLES);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    /** Beri role (idempotent) — dipakai saat registrasi & verifikasi operator. */
    public function assignRole(string $roleName, ?string $scopeRt = null, ?string $scopeRw = null): void
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $this->roles()->syncWithoutDetaching([
            $role->id => ['scope_rt' => $scopeRt, 'scope_rw' => $scopeRw],
        ]);
        $this->unsetRelation('roles');
    }

    // Fase 0: panel Filament sudah bisa diakses (base). Pembatasan peran/RBAC
    // yang sebenarnya (operator vs admin, verifikasi) baru diimplement Fase 1.
    public function canAccessPanel(Panel $panel): bool
    {
        // Hanya role staff yang boleh masuk panel; warga biasa tidak.
        return $this->isStaff();
    }
}
