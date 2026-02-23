<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature = 'user:reset-password {email : Email пользователя} {password : Новый пароль}';

    protected $description = 'Сбросить пароль пользователя по email';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Пользователь с email {$email} не найден.");
            return self::FAILURE;
        }

        // Пишем один хеш напрямую в БД, чтобы не зависеть от cast в модели
        DB::table('users')->where('id', $user->id)->update([
            'password' => Hash::make($password),
        ]);

        $this->info("Пароль для {$email} успешно обновлён. Войдите с этим паролем.");
        return self::SUCCESS;
    }
}
