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
}
