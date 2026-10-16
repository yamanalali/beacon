<?php

namespace App\Ai\Drivers;

use App\Ai\Contracts\AiProvider;
use App\Ai\Data\AiAnswer;
use App\Ai\Exceptions\AiUnavailableException;

/**
 * Deterministic driver for tests/local dev. Answers with the first supplied
 * article; unsupported when no knowledge is supplied. Never calls a network.
 */
class FakeAiProvider implements AiProvider
{
    public bool $failing = false;

    /** @var list<array{question:string,knowledge:array}> */
    public array $calls = [];

    public function answerFromKnowledge(string $question, array $knowledge): AiAnswer
    {
        $this->calls[] = ['question' => $question, 'knowledge' => $knowledge];

        if ($this->failing) {
            throw new AiUnavailableException('Fake failure.');
        }

        if ($knowledge === []) {
            return new AiAnswer(false, '');
        }

        $first = $knowledge[0];

        return new AiAnswer(true, 'بحسب إرشادات بيكون: '.mb_substr($first['body'], 0, 300), array_map(fn ($k) => $k['id'], $knowledge));
    }

    public string $transcript = 'دفعت اليوم 5000 ليرة لمحل الأمل أجار';

    public function transcribe(string $audio, string $mimeType): string
    {
        $this->calls[] = ['transcribe' => strlen($audio), 'mime' => $mimeType];
        if ($this->failing) {
            throw new AiUnavailableException('Fake failure.');
        }

        return $this->transcript;
    }

    public function extractReceipt(string $contents, string $mimeType): array
    {
        $this->calls[] = ['extractReceipt' => strlen($contents), 'mime' => $mimeType];
        if ($this->failing) {
            throw new AiUnavailableException('Fake failure.');
        }

        return ['transaction_date' => '2026-10-01', 'amount' => '125.50', 'currency' => null, 'counterparty' => 'مورد تجريبي', 'confidence' => 0.6];
    }

    public function suggestCategory(string $type, ?string $description, ?string $counterparty, array $allowedCategories): array
    {
        $this->calls[] = ['suggestCategory' => compact('type', 'description', 'counterparty', 'allowedCategories')];
        if ($this->failing) {
            throw new AiUnavailableException('Fake failure.');
        }
        $text = mb_strtolower(($description ?? '').' '.($counterparty ?? ''));
        foreach ($allowedCategories as $key => $label) {
            if ($text !== ' ' && (str_contains($text, mb_strtolower((string) $key)) || str_contains($text, mb_strtolower((string) $label)))) {
                return ['category' => (string) $key, 'confidence' => 0.9];
            }
        }

        return ['category' => 'not-an-allowed-category', 'confidence' => 0.2];
    }

    public function explainFigures(array $figures): string
    {
        $this->calls[] = ['explainFigures' => $figures];
        if ($this->failing) {
            throw new AiUnavailableException('Fake failure.');
        }

        return 'ملاحظة تجريبية حول الأرقام المعروضة للفترة '.($figures['period'] ?? '').'.';
    }
}
