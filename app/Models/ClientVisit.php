<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Событие «Личный визит клиента»: цель визита и привязка к сделке (залог/комиссия/скупка). */
class ClientVisit extends Model
{
    public const PURPOSE_APPRAISAL = 'appraisal';
    public const PURPOSE_REDEMPTION = 'redemption';
    public const PURPOSE_NON_TARGET = 'non_target';
    public const PURPOSE_IDENTIFICATION = 'identification';

    public static function purposeLabels(): array
    {
        return [
            self::PURPOSE_APPRAISAL => 'Проведение оценки',
            self::PURPOSE_REDEMPTION => 'Выкуп заложенного имущества',
            self::PURPOSE_NON_TARGET => 'Нецелевой визит',
            self::PURPOSE_IDENTIFICATION => 'Идентификация по существующей базе',
        ];
    }

    protected $fillable = [
        'store_id', 'client_id', 'visit_purpose', 'visited_at', 'created_by',
        'pawn_contract_id', 'commission_contract_id', 'purchase_contract_id',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pawnContract()
    {
        return $this->belongsTo(PawnContract::class);
    }

    public function commissionContract()
    {
        return $this->belongsTo(CommissionContract::class);
    }

    public function purchaseContract()
    {
        return $this->belongsTo(PurchaseContract::class);
    }
}
