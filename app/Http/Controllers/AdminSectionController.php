<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Промежуточные страницы разделов админки со ссылками на подразделы. */
class AdminSectionController extends Controller
{
    public function contactCenter(): View
    {
        if (! auth()->user()->canAccessContactCenter()) {
            abort(403);
        }

        $links = [
            ['route' => 'contact-center.leads.index', 'label' => 'Заявки', 'icon' => 'bi-inbox', 'hint' => 'Очередь черновиков и заявок'],
            ['route' => 'contact-center.vitrine-priority.index', 'label' => 'Витрина к продаже', 'icon' => 'bi-shop-window', 'hint' => 'Комиссия на витрине: приоритет для Avito и скидок'],
            ['route' => 'contact-center.avito-match.index', 'label' => 'Avito ↔ витрина', 'icon' => 'bi-table', 'hint' => 'Сверка активных объявлений Avito с инвентаризацией точки'],
            ['route' => 'call-center.index', 'label' => 'Коммуникации', 'icon' => 'bi-headset', 'hint' => 'Чаты Telegram/Avito и звонки'],
            ['route' => 'call-center.analytics', 'label' => 'Аналитика', 'icon' => 'bi-graph-up', 'hint' => 'Сводка по обращениям'],
        ];

        return view('admin.section-hub', [
            'title' => 'Контакт центр',
            'section' => 'contact-center',
            'intro' => 'Очередь обращений и коммуникации с клиентами.',
            'links' => $links,
        ]);
    }

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

    public function management(): View
    {
        if (! auth()->user()->hasFullStoreAccess()) {
            abort(403);
        }

        $links = [
            [
                'route' => 'management.personnel.index',
                'label' => 'Персонал',
                'icon' => 'bi-people',
                'hint' => 'Журнал сотрудников, карточки, компетенции',
            ],
            [
                'route' => 'management.reports.index',
                'label' => 'Отчёты',
                'icon' => 'bi-file-earmark-bar-graph',
                'hint' => 'Залоги, прибыль, остатки, Acuerdo и собрания',
            ],
            [
                'label' => 'Регламентные документы',
                'icon' => 'bi-journal-text',
                'hint' => 'Инструкции, положения и нормативные документы',
                'placeholder' => true,
            ],
            [
                'route' => 'management.tasks.index',
                'label' => 'Задачи',
                'icon' => 'bi-check2-square',
                'hint' => 'Журнал и канбан: постановка и контроль задач',
            ],
        ];

        return view('admin.section-hub', [
            'title' => 'Управление',
            'section' => 'management',
            'intro' => 'Персонал, отчёты, регламентные документы и задачи сети.',
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
            $links[] = ['route' => 'settings.system.index', 'label' => 'Системные настройки', 'icon' => 'bi-sliders', 'hint' => 'Telegram, портал ИИ-агентов, общие'];
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
