<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePwaIcons extends Command
{
    protected $signature = 'pwa:icons';
    protected $description = 'Генерация квадратных иконок 192x192 и 512x512 для PWA из images/logo.png';

    public function handle(): int
    {
        $logoPath = public_path('images/logo.png');
        $out192 = public_path('images/pwa-icon-192.png');
        $out512 = public_path('images/pwa-icon-512.png');

        if (!function_exists('imagecreatefrompng')) {
            $this->error('Требуется расширение PHP GD.');
            return self::FAILURE;
        }

        $sizes = [192 => $out192, 512 => $out512];

        if (is_file($logoPath)) {
            $src = @imagecreatefrompng($logoPath);
            if (!$src) {
                $this->warn('Не удалось прочитать logo.png, создаю однотонные иконки.');
                $src = null;
            }
        } else {
            $this->warn('Файл images/logo.png не найден, создаю однотонные иконки.');
            $src = null;
        }

        $brandColor = [0x1a, 0x36, 0x5d]; // #1a365d

        foreach ($sizes as $size => $path) {
            $img = imagecreatetruecolor($size, $size);
            if (!$img) {
                $this->error("Не удалось создать изображение {$size}x{$size}");
                continue;
            }

            if ($src) {
                $w = imagesx($src);
                $h = imagesy($src);
                $min = min($w, $h);
                $x = (int) (($w - $min) / 2);
                $y = (int) (($h - $min) / 2);
                imagecopyresampled($img, $src, 0, 0, $x, $y, $size, $size, $min, $min);
            } else {
                $color = imagecolorallocate($img, $brandColor[0], $brandColor[1], $brandColor[2]);
                imagefill($img, 0, 0, $color);
            }

            imagepng($img, $path, 8);
            imagedestroy($img);
            $this->info("Создан: " . basename($path));
        }

        if ($src) {
            imagedestroy($src);
        }

        $this->info('Готово. Обновите страницу кабинета и снова добавьте на экран «Домой».');
        return self::SUCCESS;
    }
}
