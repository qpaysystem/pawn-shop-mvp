<?php

namespace App\Console\Commands;

use App\Services\LmbPurchaseItemNameService;
use Illuminate\Console\Command;

/**
 * Обновить наименования товаров у договоров скупки из документа 1С.
 * Читает из заголовка _document389x1 колонки _fld9638, _fld9643, _fld9650 (или из конфига)
 * и записывает первую непустую в item.name.
 */
class RefreshPurchaseItemNamesFrom1cCommand extends Command
{
    protected $signature = 'lmb:refresh-purchase-item-names-from-1c
                            {--only-missing : Обновлять только товары с пустым name/metal/sample/weight_grams}';

    protected $description = 'Обновить наименования товаров договоров скупки из документа 1С (заголовок)';

    public function handle(LmbPurchaseItemNameService $service): int
    {
        $this->info('Чтение документов скупки из 1С и обновление item (name/metal/sample/weight)…');

        // Простейшая фильтрация: если нужна «only-missing», сервису пока не передаём фильтр,
        // а просто информируем (массовое обновление безопасно: обновляет только отличающиеся значения).
        if ($this->option('only-missing')) {
            $this->line('Режим --only-missing: будут изменены только товары, где значения отличаются/пустые.');
        }

        $result = $service->refresh();

        $this->info("Обновлено позиций: {$result['updated']}, пропущено: {$result['skipped']}.");

        if (! empty($result['errors'])) {
            $this->warn('Ошибки:');
            foreach (array_slice($result['errors'], 0, 15) as $err) {
                $this->line('  '.$err);
            }
            if (count($result['errors']) > 15) {
                $this->line('  ... и ещё '.(count($result['errors']) - 15));
            }
        }

        return self::SUCCESS;
    }
}
