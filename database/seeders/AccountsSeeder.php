<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '01', 'name' => 'Основные средства', 'description' => 'Оборудование, здания (Торговля / Ломбард)', 'type' => 'active', 'sort_order' => 5],
            ['code' => '41', 'name' => 'Товары', 'description' => 'Товары для перепродажи (опт/розница + товары ломбарда)', 'type' => 'active', 'sort_order' => 10],
            ['code' => '44', 'name' => 'Расходы на продажу', 'description' => 'Аренда, зарплата, реклама (Торговля / Ломбард)', 'type' => 'active', 'sort_order' => 15],
            ['code' => '50', 'name' => 'Касса', 'description' => 'Наличные деньги (Касса торговли / Касса ломбарда)', 'type' => 'active', 'sort_order' => 20],
            ['code' => '51', 'name' => 'Расчетный счет', 'description' => 'Безналичные деньги', 'type' => 'active', 'sort_order' => 25],
            ['code' => '58', 'name' => 'Финансовые вложения', 'description' => 'Займы, выданные ломбардом (основной долг клиентам)', 'type' => 'active', 'sort_order' => 30],
            ['code' => '60', 'name' => 'Расчёты с поставщиками', 'description' => 'Задолженность перед поставщиками (начисление расходов и т.п.)', 'type' => 'passive', 'sort_order' => 33],
            ['code' => '62', 'name' => 'Расчеты с покупателями', 'description' => 'Долги покупателей за товар (только торговля)', 'type' => 'passive', 'sort_order' => 35],
            ['code' => '66', 'name' => 'Расчеты по кредитам и займам', 'description' => 'Кредиты, полученные самой компанией (пассив)', 'type' => 'passive', 'sort_order' => 40],
            ['code' => '70', 'name' => 'Расчёты с персоналом по оплате труда', 'description' => 'Начисленная заработная плата сотрудникам', 'type' => 'passive', 'sort_order' => 41],
            ['code' => '76', 'name' => 'Расчёты с разными дебиторами и кредиторами', 'description' => null, 'type' => 'active_passive', 'sort_order' => 42],
            ['code' => '76.Л', 'name' => 'Расчеты с заемщиками ломбарда', 'description' => 'Начисленные проценты по займам ломбарда', 'type' => 'active_passive', 'sort_order' => 45],
            ['code' => '86', 'name' => 'Товары в залоге', 'description' => 'Залоговое имущество (для проводок)', 'type' => 'active', 'sort_order' => 43],
            ['code' => '90', 'name' => 'Продажи', 'description' => 'Доходы и расходы по обычным видам деятельности', 'type' => 'passive', 'sort_order' => 50],
            ['code' => '91', 'name' => 'Прочие доходы/расходы', 'description' => 'Штрафы, услуги хранения ломбарда и т.п.', 'type' => 'active_passive', 'sort_order' => 55],
            ['code' => '002', 'name' => 'Забаланс. ТМЦ на хранении', 'description' => 'Имущество, принятое в залог (не наше!)', 'type' => 'active_passive', 'sort_order' => 60],
        ];

        foreach ($accounts as $a) {
            Account::updateOrCreate(
                ['code' => $a['code']],
                array_merge($a, ['is_active' => true])
            );
        }
    }
}
