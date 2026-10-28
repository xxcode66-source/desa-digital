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

    protected $fillable = [
        'village_id', 'nik', 'name', 'phone', 'email', 'password', 'status', 'rt', 'rw',
    ];

    protected $hidden = ['password', 'remember_token', 'nik'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'village_id' => 'integer',
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

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    // Fase 0: panel Filament sudah bisa diakses (base). Pembatasan peran/RBAC
    // yang sebenarnya (operator vs admin, verifikasi) baru diimplement Fase 1.
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
