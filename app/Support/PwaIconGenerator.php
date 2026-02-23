<?php

namespace App\Support;

class PwaIconGenerator
{
    public static function ensureIcon(int $size): ?string
    {
        $filename = "pwa-icon-{$size}.png";
        $path = public_path("images/{$filename}");

        if (is_file($path)) {
            return $path;
        }

        if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
            return null;
        }

        $logoPath = public_path('images/logo.png');
        $src = null;
        if (is_file($logoPath)) {
            $src = @imagecreatefrompng($logoPath);
        }

        $img = imagecreatetruecolor($size, $size);
        if (!$img) {
            return null;
        }

        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            $min = min($w, $h);
            $x = (int) (($w - $min) / 2);
            $y = (int) (($h - $min) / 2);
            imagecopyresampled($img, $src, 0, 0, $x, $y, $size, $size, $min, $min);
            imagedestroy($src);
        } else {
            $color = imagecolorallocate($img, 0x1a, 0x36, 0x5d);
            imagefill($img, 0, 0, $color);
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        imagepng($img, $path, 8);
        imagedestroy($img);

        return is_file($path) ? $path : null;
    }
}
