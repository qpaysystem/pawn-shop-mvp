<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Пользователь системы (сотрудник).
 * Роль: super-admin | manager | appraiser | cashier | storekeeper
 * store_id: привязка к магазину (у super-admin = null).
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super-admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_APPRAISER = 'appraiser';
    public const ROLE_CASHIER = 'cashier';
    public const ROLE_STOREKEEPER = 'storekeeper';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'store_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Магазин, к которому привязан пользователь (null у super-admin). */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** Полный доступ к своему магазину (manager или super-admin). */
    public function hasFullStoreAccess(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_MANAGER], true);
    }

    /** Может создавать договоры залога/комиссии. */
    public function canCreateContracts(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_MANAGER, self::ROLE_APPRAISER], true);
    }

    /** Может оформлять продажи и выкупы. */
    public function canProcessSales(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_MANAGER, self::ROLE_CASHIER], true);
    }

    /** Может менять статус товара и место хранения. */
    public function canManageStorage(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_MANAGER, self::ROLE_STOREKEEPER], true);
    }

    /** Список магазинов, к которым есть доступ (для super-admin — все). */
    public function allowedStoreIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Store::pluck('id')->all();
        }
        return $this->store_id ? [$this->store_id] : [];
    }
}
