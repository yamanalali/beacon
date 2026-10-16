<?php

namespace App\Ai\Support;

use App\Exceptions\ThirdPartySharingNotApproved;
use App\Support\DataControl;
use Illuminate\Support\Facades\Log;

/**
 * Section 54 guard for every outbound AI provider call. Wraps
 * DataControl::assertCanShare() so AI code fails safely (no provider call,
 * caller falls back to its "AI unavailable" path) when sharing is not approved.
 *
 * TODO: BUSINESS DECISION REQUIRED — the processor key and the approved
 * data-field names for each AI use (config operations.data_processors.*.allowed_data
 * is empty until Beacon approves a DPA). The field names below are the
 * technical description of what each call sends; they are not approvals.
 */
class AiDataGuard
{
    /** Receipt OCR (§15): sends only the receipt file. */
    public const RECEIPT = ['ocr', ['receipt_image']];

    /** Categorization (§16): sends type, description, counterparty and allowed list. */
    public const CATEGORIZATION = ['ai_assistant', ['transaction_type', 'transaction_description', 'counterparty']];

    /** Assistant (§33–36): sends the question and approved knowledge articles. */
    public const ASSISTANT = ['ai_assistant', ['user_question', 'approved_knowledge']];

    /**
     * Insights (§19): sends aggregate report figures only.
     * TODO: BUSINESS DECISION REQUIRED — whether insights use the "ai_assistant"
     * processor or need a separate approved processor entry.
     */
    public const INSIGHTS = ['ai_assistant', ['aggregate_financial_figures']];

    /** @param  array{0:string,1:list<string>}  $use */
    public static function allows(array $use): bool
    {
        [$processor, $fields] = $use;
        try {
            DataControl::assertCanShare($processor, $fields);

            return true;
        } catch (ThirdPartySharingNotApproved $e) {
            Log::info('AI provider call skipped: '.$e->getMessage());

            return false;
        }
    }
}
