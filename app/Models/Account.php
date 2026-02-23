<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Счёт из плана бухгалтерских счетов. */
class Account extends Model
{
    public const TYPE_ACTIVE = 'active';
    public const TYPE_PASSIVE = 'passive';
    public const TYPE_ACTIVE_PASSIVE = 'active_passive';

    /** Код счёта 50 — Касса. */
    public const CODE_CASH = '50';

    /** 60 — Расчёты с поставщиками. */
    public const CODE_SUPPLIERS = '60';

    /** 62 — Расчёты с покупателями. */
    public const CODE_BUYERS = '62';

    /** 66 — Расчёты по краткосрочным кредитам и займам. */
    public const CODE_LOANS = '66';

    /** 41 — Товары. */
    public const CODE_GOODS = '41';

    /** 58 — Финансовые вложения (займы, выданные ломбардом). */
    public const CODE_FINANCIAL_INVESTMENTS = '58';

    /** 86 — Залоговое имущество / товар в залоге. */
    public const CODE_PLEDGE = '86';

    /** 70 — Расчёты с персоналом по оплате труда (начисление заработной платы). */
    public const CODE_PAYROLL = '70';

    /** 76 — Расчёты с разными дебиторами и кредиторами (в т.ч. обеспечение по залогу). */
    public const CODE_SETTLEMENTS_OTHER = '76';

    /** 90 — Продажи / прибыль. */
    public const CODE_SALES = '90';

    /** 91 — Прочие доходы и расходы (проценты по займам ломбарда). */
    public const CODE_OTHER_INCOME = '91';

    protected $fillable = ['code', 'name', 'description', 'type', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
