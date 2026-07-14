<?php

namespace App\Services\Meetings;

/** Краткое содержание собрания через LLM. */
class MeetingSummaryService
{
    public function __construct(
        private MeetingLlmService $llm = new MeetingLlmService,
    ) {}

    /**
     * @return array{summary: string, highlights: list<string>}
     */
    public function summarize(string $transcript, string $title, string $room): array
    {
        $system = <<<'PROMPT'
Ты секретарь утреннего собрания сети ломбардов. По стенограмме составь краткий отчёт для руководителя.
Структура summary: 1) главные темы; 2) цифры и факты (только из текста); 3) проблемы и решения; 4) поручения.
Не выдумывай. Если чего-то нет в стенограмме — не добавляй.
Верни JSON:
{
  "summary": "текст отчёта 8-20 предложений, абзацами",
  "highlights": ["краткий тезис", "..."]
}
PROMPT;

        $user = implode("\n", [
            "room: {$room}",
            "recording: {$title}",
            '',
            mb_substr($transcript, 0, 100000),
        ]);

        $result = $this->llm->chat($system, $user, 0.2, 4096);
        $parsed = $this->parseJsonResponse($result['content'] ?? '');
        if ($parsed !== null) {
            return $parsed;
        }

        $excerpt = mb_strlen($transcript) > 4000
            ? mb_substr($transcript, 0, 3980)."\n… (обрезано)"
            : $transcript;

        return [
            'summary' => $excerpt,
            'highlights' => [],
        ];
    }

    /** @return array{summary: string, highlights: list<string>}|null */
    private function parseJsonResponse(?string $content): ?array
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        $json = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/u', $json, $m)) {
            $json = trim($m[1]);
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $summary = trim((string) ($data['summary'] ?? ''));
        if ($summary === '') {
            return null;
        }

        $highlights = [];
        foreach (($data['highlights'] ?? []) as $item) {
            $line = trim((string) $item);
            if ($line !== '') {
                $highlights[] = $line;
            }
        }

        return [
            'summary' => mb_substr($summary, 0, 50000),
            'highlights' => $highlights,
        ];
    }
}
