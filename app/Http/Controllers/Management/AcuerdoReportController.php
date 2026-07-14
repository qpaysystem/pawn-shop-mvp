<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Services\Acuerdo\AcuerdoReportService;
use Illuminate\View\View;

/** Отчёты info.acuerdo.pro в разделе «Управление». */
class AcuerdoReportController extends Controller
{
    public function __construct(
        private AcuerdoReportService $reports,
    ) {}

    public function index(): View
    {
        $this->ensureAccess();

        $links = [
            [
                'route' => 'management.reports.lombard.pawns',
                'label' => 'Залоги и выкупы',
                'icon' => 'bi-file-earmark-lock',
                'hint' => 'Выданные залоги, выкупы за период и активные договоры',
            ],
            [
                'route' => 'management.reports.lombard.gross-profit',
                'label' => 'Валовая прибыль',
                'icon' => 'bi-graph-up-arrow',
                'hint' => 'Сводный отчёт как в 1С: залоги + реализация',
            ],
            [
                'route' => 'management.reports.lombard.pawn-profit',
                'label' => 'Прибыль с залогов',
                'icon' => 'bi-percent',
                'hint' => 'Процентный доход по выкупленным договорам',
            ],
            [
                'route' => 'management.reports.lombard.sales-profit',
                'label' => 'Прибыль по продажам',
                'icon' => 'bi-cart-check',
                'hint' => 'Маржа по документам реализации',
            ],
            [
                'route' => 'management.reports.lombard.inventory',
                'label' => 'Инвентаризация',
                'icon' => 'bi-clipboard-data',
                'hint' => 'Остатки залогов и скупки по точкам, датам и виду товара',
            ],
            [
                'route' => 'management.reports.current-asset',
                'label' => 'Текущий актив',
                'icon' => 'bi-pie-chart',
                'hint' => 'Отчёт info.acuerdo.pro — залоги, товары, кассы, итого',
            ],
            [
                'route' => 'management.reports.current-finances',
                'label' => 'Текущие финансы',
                'icon' => 'bi-cash-stack',
                'hint' => 'Отчёт info.acuerdo.pro — кассы по точкам, полотно ЕС',
            ],
            [
                'route' => 'management.reports.meetings.index',
                'label' => 'Собрания',
                'icon' => 'bi-camera-video',
                'hint' => 'Видеособрания conf.nnfm.pro — транскрипция и отчёт ИИ',
            ],
        ];

        return view('admin.section-hub', [
            'title' => 'Отчёты',
            'section' => 'management',
            'intro' => 'Операционные отчёты ломбарда, info.acuerdo.pro и журнал видеособраний.',
            'links' => $links,
            'breadcrumbs' => [
                ['label' => 'Управление', 'route' => 'section.management'],
            ],
        ]);
    }

    public function currentAsset(): View
    {
        return $this->showReport(
            AcuerdoReportService::REPORT_CURRENT_ASSET,
            'Текущий актив',
        );
    }

    public function currentFinances(): View
    {
        return $this->showReport(
            AcuerdoReportService::REPORT_CURRENT_FINANCES,
            'Текущие финансы',
        );
    }

    private function showReport(string $reportKey, string $pageTitle): View
    {
        $this->ensureAccess();
        $result = $this->reports->fetchReport($reportKey);

        return view('management.reports.acuerdo', [
            'pageTitle' => $pageTitle,
            'result' => $result,
        ]);
    }

    private function ensureAccess(): void
    {
        if (! auth()->user()->hasFullStoreAccess()) {
            abort(403);
        }
    }
}
