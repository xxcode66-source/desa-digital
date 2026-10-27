<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /** Role resmi sesuai PEMBANGUNAN.md bagian 3 (RBAC). */
    public const ALL = ['warga', 'umkm', 'rt', 'kolektor', 'operator', 'kades', 'bendahara_pbb', 'admin'];

    protected $fillable = ['name', 'label'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')->withPivot(['scope_rt', 'scope_rw']);
    }
}
