<?php

namespace App\Support;

class SubmissionHash
{
    public static function make(array $data): string
    {
        unset($data['client_draft_id']);
        $normalise = function ($v) use (&$normalise) {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($normalise, $v);
        };

        return hash('sha256', json_encode($normalise($data), JSON_THROW_ON_ERROR));
    }
}
