<?php

namespace App\Ai\Drivers;

use App\Ai\Contracts\AiProvider;
use App\Ai\Data\AiAnswer;
use App\Ai\Exceptions\AiUnavailableException;

/** Default until an AI vendor is approved: always unavailable. */
class NullAiProvider implements AiProvider
{
    public function answerFromKnowledge(string $question, array $knowledge): AiAnswer
    {
        throw new AiUnavailableException('No AI provider configured.');
    }
}
