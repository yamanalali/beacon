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

    /**
     * Suggest receipt fields from a document image/PDF. Returns raw suggestions;
     * callers must keep them as review-only suggestions (rules §15).
     *
     * @return array{transaction_date:?string,amount:?string,currency:?string,counterparty:?string,confidence:?float}
     *
     * @throws AiUnavailableException
     */
    public function extractReceipt(string $contents, string $mimeType): array;

    /**
     * Choose one key from $allowedCategories (rules §16).
     *
     * @param  array<string,string>  $allowedCategories
     * @return array{category:?string,confidence:?float}
     *
     * @throws AiUnavailableException
     */
    public function suggestCategory(string $type, ?string $description, ?string $counterparty, array $allowedCategories): array;

    /**
     * Plain-Arabic explanation of already-calculated aggregate figures (rules §19).
     * Must not compute authoritative values.
     *
     * @param  array<string,mixed>  $figures
     *
     * @throws AiUnavailableException
     */
    public function explainFigures(array $figures): string;

    /**
     * Speech-to-text for a short voice note (voice draft transaction).
     * External providers are only called after AiDataGuard::SPEECH approval.
     *
     * @throws AiUnavailableException
     */
    public function transcribe(string $audio, string $mimeType): string;
}
