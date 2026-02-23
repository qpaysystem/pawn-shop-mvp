<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LandingController extends Controller
{
    /** Главная (лендинг) */
    public function lombard(): View
    {
        return view('landing.lombard.index');
    }

    /** Любая статичная страница: buy, contacts, about, lombard, catalog */
    public function page(string $view): View
    {
        return view('landing.lombard.pages.' . $view);
    }

    /** Подраздел категории: gold/mernie, fur/sobol, technical/mv, tool/shurupoverti, gadjets/phone */
    public function section(string $category, string $slug): View
    {
        $titles = $this->sectionTitles();
        $categoryNames = [
            'gold' => ['name' => 'Золото', 'route' => 'landing.gold'],
            'fur' => ['name' => 'Меха', 'route' => 'landing.fur'],
            'technical' => ['name' => 'Техника', 'route' => 'landing.technical'],
            'tool' => ['name' => 'Инструменты', 'route' => 'landing.tool'],
            'gadjets' => ['name' => 'Гаджеты', 'route' => 'landing.gadjets'],
        ];
        $title = $titles[$category][$slug] ?? ucfirst($slug);
        return view('landing.lombard.pages.section', [
            'category'       => $category,
            'categoryName'   => $categoryNames[$category]['name'] ?? $category,
            'categoryRoute'   => $categoryNames[$category]['route'] ?? 'landing.catalog',
            'slug'           => $slug,
            'title'          => $title,
        ]);
    }

    /** Раздел каталога /catalog/:category_code */
    public function catalogSection(string $category_code): View
    {
        return view('landing.lombard.pages.catalog-section', [
            'category_code' => $category_code,
        ]);
    }

    /** Товар /catalog/:category_code/item/:id */
    public function catalogItem(string $category_code, string $id): View
    {
        return view('landing.lombard.pages.catalog-item', [
            'category_code' => $category_code,
            'id'           => $id,
        ]);
    }

    private function sectionTitles(): array
    {
        return [
            'gold' => [
                'mernie' => 'Мерные изделия',
                'coins'  => 'Монеты',
                'rings'   => 'Кольца',
                'lom'     => 'Лом',
            ],
            'fur' => [
                'sobol' => 'Соболь',
                'norka' => 'Норка',
            ],
            'technical' => [
                'mv' => 'Музыкальные центры',
                'fr' => 'Холодильники',
                'tv' => 'Телевизоры',
                'st' => 'Станки',
            ],
            'tool' => [
                'shurupoverti' => 'Шуруповёрты',
                'perforatori'  => 'Перфораторы',
                'lobziki'      => 'Лобзики',
            ],
            'gadjets' => [
                'phone' => 'Телефоны',
                'comp'  => 'Компьютеры',
                'play'  => 'Плееры',
                'photo' => 'Фототехника',
            ],
        ];
    }
}
