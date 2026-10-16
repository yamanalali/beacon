<?php

namespace App\Ai\Data;

final class AiAnswer
{
    /**
     * @param  list<int>  $sourceIds
     */
    public function __construct(
        public readonly bool $supported,
        public readonly string $text,
        public readonly array $sourceIds = [],
    ) {}
}
