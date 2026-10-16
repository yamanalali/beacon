<?php

namespace App\Support;

use App\Exceptions\ThirdPartySharingNotApproved;

/**
 * Section 54: programme data may only leave Beacon's control through an approved processor,
 * limited to the data fields approved for it. AI/OCR/notification code must call
 * DataControl::assertCanShare() before sending anything to an external provider.
 */
class DataControl
{
    public static function isApproved(string $processor): bool
    {
        $config = config("operations.data_processors.$processor");

        return is_array($config) && ($config['approved'] ?? false) === true;
    }

    /**
     * @param  list<string>  $fields  data fields about to be sent
     */
    public static function assertCanShare(string $processor, array $fields = []): void
    {
        $config = config("operations.data_processors.$processor");

        if (! is_array($config)) {
            throw ThirdPartySharingNotApproved::for($processor, 'unknown processor');
        }
        if (($config['approved'] ?? false) !== true) {
            throw ThirdPartySharingNotApproved::for($processor, 'no approved data-processing arrangement');
        }
        $extra = array_diff($fields, $config['allowed_data'] ?? []);
        if ($extra !== []) {
            throw ThirdPartySharingNotApproved::for($processor, 'fields not approved: '.implode(', ', $extra));
        }
    }

    /** @return list<array{key:string,label_ar:string,approved:bool,agreement_reference:?string,allowed_data:list<string>}> */
    public static function registry(): array
    {
        return collect(config('operations.data_processors', []))
            ->map(fn (array $c, string $key) => [
                'key' => $key,
                'label_ar' => $c['label_ar'] ?? $key,
                'approved' => ($c['approved'] ?? false) === true,
                'agreement_reference' => $c['agreement_reference'] ?? null,
                'allowed_data' => array_values($c['allowed_data'] ?? []),
            ])->values()->all();
    }
}
