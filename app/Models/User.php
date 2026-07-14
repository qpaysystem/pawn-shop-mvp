<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Пользователь системы (сотрудник).
 * Роль: super-admin | manager | appraiser | cashier | storekeeper | contact-center
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
    public const ROLE_CONTACT_CENTER = 'contact-center';

    protected $fillable = [
        'name',
        'email',
        'telegram',
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

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** Оценщик / товаровед — упрощённый интерфейс (только приём, выкуп, касса). */
    public function isAppraiser(): bool
    {
        return $this->role === self::ROLE_APPRAISER;
    }

    public function isContactCenter(): bool
    {
        return $this->role === self::ROLE_CONTACT_CENTER;
    }

    /** Доступ к контакт-центру и колл-центру. */
    public function canAccessContactCenter(): bool
    {
        return in_array($this->role, [
            self::ROLE_CONTACT_CENTER,
            self::ROLE_SUPER_ADMIN,
            self::ROLE_MANAGER,
        ], true);
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

    /** Может назначать скидку на товар витрины (контакт-центр). */
    public function canApplyVitrineDiscount(): bool
    {
        return $this->canAccessContactCenter();
    }

    /** Список магазинов, к которым есть доступ (для super-admin — все). */
    public function allowedStoreIds(): array
    {
        if ($this->isSuperAdmin() || $this->isContactCenter()) {
            return Store::pluck('id')->all();
        }
        return $this->store_id ? [$this->store_id] : [];
    }
}
