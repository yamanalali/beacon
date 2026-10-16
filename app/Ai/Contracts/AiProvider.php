<?php

namespace App\Ai\Contracts;

use App\Ai\Data\AiAnswer;
use App\Ai\Exceptions\AiUnavailableException;

/**
 * Provider-agnostic AI adapter. Vendor adapters implement this; business code
 * depends only on this contract (beacon-platform §71).
 */
interface AiProvider
{
    /**
     * Answer a question strictly from the supplied approved knowledge.
     *
     * @param  list<array{id:int,title:string,body:string}>  $knowledge
     *
     * @throws AiUnavailableException
     */
    public function answerFromKnowledge(string $question, array $knowledge): AiAnswer;
}
