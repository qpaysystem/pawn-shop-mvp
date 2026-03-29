<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class LmbCleanBankClientsCommand extends Command
{
    protected $signature = 'lmb:clean-bank-clients
                            {--dry-run : Только показать количество и примеры, не удалять}
                            {--force : Удалить без подтверждения}';

    protected $description = 'Удалить клиентов, загруженных из таблицы банков 1С (_reference108)';

    public function handle(): int
    {
        $query = Client::where('lmb_data->synced_from_1c_table', '_reference108');
        $count = $query->count();

        if ($count === 0) {
            $this->info('Клиентов из таблицы банков (_reference108) не найдено.');

            return self::SUCCESS;
        }

        $this->warn("Найдено клиентов из таблицы банков: {$count}");

        if ($this->option('dry-run')) {
            $examples = $query->limit(5)->get(['id', 'full_name', 'phone']);
            $this->table(['id', 'full_name', 'phone'], $examples->map(fn ($c) => [$c->id, $c->full_name, $c->phone]));
            $this->info('Запустите без --dry-run, чтобы удалить (будет запрос подтверждения).');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Удалить {$count} клиентов?", true)) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($query->get() as $client) {
            $client->delete();
            $deleted++;
        }

        $this->info("Удалено клиентов: {$deleted}.");

        return self::SUCCESS;
    }
}
