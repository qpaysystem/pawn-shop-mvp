<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Промежуточные страницы разделов админки со ссылками на подразделы. */
class AdminSectionController extends Controller
{
    public function clients(): View
    {
        $user = auth()->user();
        $links = [];
        if ($user->canCreateContracts()) {
            $links[] = ['route' => 'accept.create', 'label' => 'Приём товара', 'icon' => 'bi-plus-circle', 'hint' => 'Залог или комиссия, фото, печать'];
        }
        $links[] = ['route' => 'clients.index', 'label' => 'Клиенты', 'icon' => 'bi-people', 'hint' => 'Список, карточка, синхронизация с 1С'];
        $links[] = ['route' => 'items.index', 'label' => 'Товары', 'icon' => 'bi-box-seam', 'hint' => 'Учёт, статусы, места хранения'];
        $links[] = ['route' => 'pawn-contracts.index', 'label' => 'Договоры залога', 'icon' => 'bi-file-text', 'hint' => 'Выкуп, печать'];
        $links[] = ['route' => 'commission-contracts.index', 'label' => 'Договоры комиссии', 'icon' => 'bi-file-earmark-text', 'hint' => 'Продажа комиссионного товара'];
        $links[] = ['route' => 'purchase-contracts.index', 'label' => 'Договоры скупки', 'icon' => 'bi-cash-coin', 'hint' => 'Скупленные ценности'];
        $links[] = ['route' => 'call-center.index', 'label' => 'Колл-центр', 'icon' => 'bi-telephone-inbound', 'hint' => 'Обращения и звонки'];
        $links[] = ['route' => 'call-center.analytics', 'label' => 'Аналитика колл-центра', 'icon' => 'bi-graph-up', 'hint' => 'Сводка по обращениям'];

        return view('admin.section-hub', [
            'title' => 'Работа с клиентами',
            'section' => 'clients',
            'intro' => 'Клиенты, товары, договоры и колл-центр.',
            'links' => $links,
        ]);
    }

    public function marketing(): View
    {
        $links = [
            ['route' => 'marketing.index', 'label' => 'Маркетинг', 'icon' => 'bi-bar-chart-line', 'hint' => 'Источники, воронка, эффективность, 2ГИС'],
        ];

        return view('admin.section-hub', [
            'title' => 'Маркетинг',
            'section' => 'marketing',
            'intro' => 'Трафик, воронка продаж и внешняя аналитика.',
            'links' => $links,
        ]);
    }

    public function finance(): View
    {
        $user = auth()->user();
        $links = [];
        if ($user->canProcessSales()) {
            $links = [
                ['route' => 'cash.index', 'label' => 'Касса', 'icon' => 'bi-cash-stack', 'hint' => 'Приход и расход по кассе'],
                ['route' => 'cash.report', 'label' => 'Отчёт по кассам', 'icon' => 'bi-bar-chart', 'hint' => 'Сводка операций'],
                ['route' => 'expenses.index', 'label' => 'Расходы', 'icon' => 'bi-cash-expense', 'hint' => 'Документы расходов'],
                ['route' => 'employees.index', 'label' => 'ФОТ', 'icon' => 'bi-currency-dollar', 'hint' => 'Сотрудники и начисления'],
                ['route' => 'bank-accounts.index', 'label' => 'Банк', 'icon' => 'bi-bank', 'hint' => 'Счета и выписки'],
                ['route' => 'documents.index', 'label' => 'Все документы', 'icon' => 'bi-files', 'hint' => 'Сводный список'],
                ['route' => 'chart-of-accounts.index', 'label' => 'План счетов', 'icon' => 'bi-journal-ruled', 'hint' => 'Оборотно-сальдовая ведомость'],
                ['route' => 'document-ledger-templates.index', 'label' => 'Шаблоны проводок', 'icon' => 'bi-journal-check', 'hint' => 'Настройка проводок по типам документов'],
            ];
        }

        return view('admin.section-hub', [
            'title' => 'Финансы',
            'section' => 'finance',
            'intro' => 'Касса, банк, расходы, учёт и отчётность.',
            'links' => $links,
        ]);
    }

    public function settings(): View
    {
        $user = auth()->user();
        $links = [
            ['route' => 'item-categories.index', 'label' => 'Категории товаров', 'icon' => 'bi-tags', 'hint' => 'Справочник категорий'],
            ['route' => 'brands.index', 'label' => 'Бренды', 'icon' => 'bi-award', 'hint' => 'Производители и бренды'],
            ['route' => 'item-statuses.index', 'label' => 'Статусы товара', 'icon' => 'bi-flag', 'hint' => 'Жизненный цикл вещи'],
            ['route' => 'storage-locations.index', 'label' => 'Места хранения', 'icon' => 'bi-geo-alt', 'hint' => 'Склады и ячейки'],
            ['route' => 'kb.index', 'label' => 'База знаний', 'icon' => 'bi-journal-bookmark', 'hint' => 'Как на сайте для клиентов'],
            ['route' => 'profile.show', 'label' => 'Профиль', 'icon' => 'bi-person', 'hint' => 'Ваш аккаунт'],
        ];
        if ($user->isSuperAdmin()) {
            $links[] = ['route' => 'stores.index', 'label' => 'Магазины', 'icon' => 'bi-shop', 'hint' => 'Филиалы сети'];
            $links[] = ['route' => 'users.index', 'label' => 'Пользователи', 'icon' => 'bi-person-gear', 'hint' => 'Сотрудники и роли'];
            $links[] = ['route' => 'kb.categories.index', 'label' => 'База знаний — админка', 'icon' => 'bi-pencil-square', 'hint' => 'Категории и статьи'];
        }

        return view('admin.section-hub', [
            'title' => 'Настройки',
            'section' => 'settings',
            'intro' => 'Справочники, профиль и администрирование.',
            'links' => $links,
        ]);
    }
}
