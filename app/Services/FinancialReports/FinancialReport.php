<?php

namespace App\Services\FinancialReports;

/**
 * A rendered, format-independent report: sections of tabular rows.
 * Used by the screen, CSV, Excel and printable/PDF output alike.
 */
final class FinancialReport
{
    /**
     * @param  list<array{title: string, columns: list<string>, rows: list<list<string>>}>  $sections
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $periodLabel,
        public readonly array $sections,
        public readonly array $notes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'period_label' => $this->periodLabel,
            'sections' => $this->sections,
            'notes' => $this->notes,
        ];
    }
}
