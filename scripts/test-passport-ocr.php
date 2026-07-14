<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$img = imagecreatetruecolor(200, 200);
imagejpeg($img, '/tmp/test-pass.jpg', 90);
imagedestroy($img);

$openaiKey = config('services.openai.api_key');
$deepseekKey = config('services.deepseek.api_key');
$visionKey = config('services.google_vision.api_key');
$geminiKey = config('services.gemini.api_key');

echo "openai key: " . ($openaiKey ? 'yes' : 'no') . "\n";
echo "deepseek key: " . ($deepseekKey ? 'yes' : 'no') . "\n";
echo "vision key: " . ($visionKey ? 'yes' : 'no') . "\n";
echo "gemini key: " . ($geminiKey ? 'yes' : 'no') . "\n\n";

if ($openaiKey) {
    $content = file_get_contents('/tmp/test-pass.jpg');
    $b64 = base64_encode($content);
    $model = config('services.openai.model', 'gpt-4o-mini');
    $r = Illuminate\Support\Facades\Http::timeout(30)->withToken($openaiKey)->post('https://api.openai.com/v1/chat/completions', [
        'model' => str_contains($model, 'gpt-4') ? $model : 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Reply with one word: blank or text'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $b64]],
                ],
            ],
        ],
        'max_tokens' => 20,
    ]);
    echo "openai vision HTTP: {$r->status()}\n";
    echo substr($r->body(), 0, 500) . "\n\n";
}

if ($deepseekKey) {
    $content = file_get_contents('/tmp/test-pass.jpg');
    $b64 = base64_encode($content);
    $r = Illuminate\Support\Facades\Http::timeout(25)->withToken($deepseekKey)->post('https://api.deepseek.com/v1/chat/completions', [
        'model' => config('services.deepseek.model', 'deepseek-chat'),
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Describe in one word'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $b64]],
                ],
            ],
        ],
        'max_tokens' => 20,
    ]);
    echo "deepseek HTTP: {$r->status()}\n";
    echo substr($r->body(), 0, 500) . "\n\n";
}

if ($visionKey) {
    $content = file_get_contents('/tmp/test-pass.jpg');
    $b64 = base64_encode($content);
    $r = Illuminate\Support\Facades\Http::timeout(20)->post(
        'https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($visionKey),
        [
            'requests' => [
                [
                    'image' => ['content' => $b64],
                    'features' => [['type' => 'TEXT_DETECTION']],
                ],
            ],
        ]
    );
    echo "vision HTTP: {$r->status()}\n";
    echo substr($r->body(), 0, 500) . "\n";
}
