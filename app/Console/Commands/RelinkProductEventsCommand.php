<?php

namespace App\Console\Commands;

use App\Services\OneC\DayOpsJsonImportService;
use Illuminate\Console\Command;

/** Повторная привязка lmb_product_events к товарам/складам по payload 1С. */
class RelinkProductEventsCommand extends Command
{
    protected $signature = 'lmb:relink-product-events
                            {--type= : Только event_type (move, status, Выкуп, …)}
                            {--force : Подтвердить запись в БД}';

    protected $description = 'Перепривязать события по товару из 1С к item/store/status';

    public function handle(DayOpsJsonImportService $service): int
    {
        if (! $this->option('force')) {
            $this->error('Для записи укажите --force.');

            return self::FAILURE;
        }

        $type = $this->option('type');
        $stats = $service->relinkExisting(is_string($type) && $type !== '' ? $type : null);
        $this->table(['Метрика', 'Значение'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }
}
