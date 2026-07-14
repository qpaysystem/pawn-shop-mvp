<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = config('services.deepseek.api_key');
if (! $key) {
    echo "no deepseek key\n";
    exit(1);
}

$img = imagecreatetruecolor(100, 100);
imagejpeg($img, '/tmp/test-pass.jpg', 90);
imagedestroy($img);
$b64 = base64_encode(file_get_contents('/tmp/test-pass.jpg'));

foreach (['deepseek-v4-flash', 'deepseek-v4-pro', config('services.deepseek.model', 'deepseek-chat')] as $model) {
    $r = Illuminate\Support\Facades\Http::timeout(25)->withToken($key)->post('https://api.deepseek.com/v1/chat/completions', [
        'model' => $model,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Reply one word: blank'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $b64]],
                ],
            ],
        ],
        'max_tokens' => 10,
    ]);
    echo $model . ': HTTP ' . $r->status() . "\n" . substr($r->body(), 0, 300) . "\n\n";
}
