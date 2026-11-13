<?php

namespace App\Actions\Baseline;

use App\Actions\Audit\RecordAuditLog;
use App\Models\BaselineProfileField;
use App\Models\Enterprise;
use App\Models\EnterpriseBaselineProfile;
use App\Models\User;
use App\Support\Baseline\KoboXlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportBaselineXlsxAction
{
    public function __construct(
        private readonly KoboXlsxReader $reader,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @return array{created:int,updated:int,skipped:int,errors:list<string>}
     */
    public function execute(?User $actor, string $absolutePath, bool $keepCopy = true): array
    {
        $parsed = $this->reader->read($absolutePath);
        $headers = $parsed['headers'];
        $rows = $parsed['rows'];

        $fields = BaselineProfileField::query()->orderBy('sort_order')->get();
        $byColumn = $fields->keyBy(fn (BaselineProfileField $f) => strtoupper((string) $f->excel_column));

        $columnByKey = [];
        foreach ($headers as $col => $label) {
            $field = $byColumn->get(strtoupper($col));
            if ($field) {
                $columnByKey[$col] = $field->key;
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        if ($keepCopy) {
            $disk = Storage::disk('local');
            $disk->makeDirectory('baseline-imports');
            $target = 'baseline-imports/'.now()->format('Ymd_His').'_'.preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($absolutePath));
            $disk->put($target, (string) file_get_contents($absolutePath));
        }

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            try {
                $result = DB::transaction(function () use ($actor, $row, $columnByKey, $byColumn) {
                    return $this->importRow($actor, $row, $columnByKey, $byColumn);
                });
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                report($e);
                $errors[] = 'صف '.$rowNumber.': '.$e->getMessage();
            }
        }

        if ($actor) {
            $this->audit->record($actor, 'baseline.import.completed', $actor, [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'error_count' => count($errors),
            ]);
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * @param  array<string,string>  $row
     * @param  array<string,string>  $columnByKey
     * @param  \Illuminate\Support\Collection<string,BaselineProfileField>  $byColumn
     */
    private function importRow(?User $actor, array $row, array $columnByKey, $byColumn): string
    {
        $answers = [];
        foreach ($columnByKey as $col => $key) {
            if (! array_key_exists($col, $row)) {
                continue;
            }
            $val = trim((string) $row[$col]);
            if ($val === '') {
                continue;
            }
            $answers[$key] = $this->normalizeValue($val);
        }

        $mseId = $this->pick($row, ['D']) ?: ($answers['i01_mse_id'] ?? null);
        $uuid = $this->pick($row, ['LU']) ?: ($answers['_uuid'] ?? null);
        $businessName = $this->pick($row, ['S']) ?: ($answers['col_s'] ?? null);
        $phone = $this->pick($row, ['U']) ?: ($answers['col_u'] ?? null);
        $owner = $this->pick($row, ['T']) ?: ($answers['col_t'] ?? null);

        if (($businessName === null || $businessName === '') && ($mseId === null || $mseId === '')) {
            return 'skipped';
        }

        $enterprise = $this->findEnterprise($mseId, $uuid, $businessName, $phone);
        $isNew = $enterprise === null;

        if ($isNew) {
            $enterprise = new Enterprise;
            $enterprise->fill([
                'business_name' => $businessName ?: ('منشأة '.($mseId ?: 'بدون رقم')),
                'location' => 'rural_damascus', // داريا ضمن ريف دمشق في نطاق البرنامج الحالي
            ]);
            $enterprise->created_by = $actor?->id;
            $enterprise->save();
        }

        $this->syncCoreFields($enterprise, $row, $answers, $owner, $phone, $businessName);

        $profile = EnterpriseBaselineProfile::query()->firstOrNew(['enterprise_id' => $enterprise->id]);
        $profile->external_mse_id = $mseId ?: $profile->external_mse_id;
        $profile->source_uuid = $uuid ?: $profile->source_uuid;
        $profile->submission_time = $this->parseSubmissionTime($row, $answers) ?? $profile->submission_time;
        $profile->answers = $answers;
        $profile->imported_at = now();
        $profile->imported_by = $actor?->id;
        $profile->save();

        return $isNew ? 'created' : 'updated';
    }

    /**
     * @param  array<string,string>  $row
     * @param  array<string,mixed>  $answers
     */
    private function syncCoreFields(Enterprise $enterprise, array $row, array $answers, ?string $owner, ?string $phone, ?string $businessName): void
    {
        $address = $this->pick($row, ['V']) ?: ($answers['col_v'] ?? null);
        $sector = $this->pick($row, ['AH']) ?: ($answers['col_ah'] ?? null);
        $activity = $this->pick($row, ['AI']) ?: ($answers['col_ai'] ?? null);
        $year = $this->pick($row, ['BQ']) ?: ($answers['col_bq'] ?? null);
        $employees = $this->pick($row, ['BR']) ?: ($answers['col_br'] ?? null);
        $female = $this->pick($row, ['BT']) ?: ($answers['col_bt'] ?? null);
        $eligible = $this->pick($row, ['BG']) ?: ($answers['s16_eligible'] ?? null);

        if ($businessName) {
            $enterprise->business_name = $businessName;
        }
        if ($owner) {
            $enterprise->owner_name = $owner;
            $enterprise->contact_name = $owner;
        }
        if ($phone) {
            $enterprise->contact_phone = $phone;
        }
        if ($address) {
            $enterprise->address_line = $address;
        }
        if ($sector || $activity) {
            $enterprise->sector = trim(($sector ? $sector.' — ' : '').($activity ?? ''), ' —');
        }
        if ($year !== null && $year !== '' && preg_match('/^\d{4}$/', (string) $year)) {
            $enterprise->founding_date = $year.'-01-01';
        }
        if ($employees !== null && $employees !== '' && is_numeric($employees)) {
            $enterprise->employee_count = (int) $employees;
        }
        if ($female !== null && $female !== '' && is_numeric($female)) {
            $enterprise->female_employee_count = (int) $female;
        }
        if ($eligible !== null && $eligible !== '') {
            $enterprise->programme_stage = strtolower((string) $eligible) === 'yes' ? 'baseline_eligible' : 'baseline_ineligible';
        }
        $enterprise->save();
    }

    private function findEnterprise(?string $mseId, ?string $uuid, ?string $businessName, ?string $phone): ?Enterprise
    {
        if ($mseId) {
            $byMse = EnterpriseBaselineProfile::query()->where('external_mse_id', $mseId)->first();
            if ($byMse) {
                return $byMse->enterprise;
            }
        }
        if ($uuid) {
            $byUuid = EnterpriseBaselineProfile::query()->where('source_uuid', $uuid)->first();
            if ($byUuid) {
                return $byUuid->enterprise;
            }
        }
        if ($businessName && $phone) {
            return Enterprise::query()
                ->where('business_name', $businessName)
                ->where('contact_phone', $phone)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string,string>  $row
     * @param  list<string>  $cols
     */
    private function pick(array $row, array $cols): ?string
    {
        foreach ($cols as $col) {
            if (isset($row[$col]) && trim((string) $row[$col]) !== '') {
                return trim((string) $row[$col]);
            }
        }

        return null;
    }

    private function normalizeValue(string $val): string|int|float
    {
        if (is_numeric($val) && ! preg_match('/^0\d+$/', $val)) {
            return str_contains($val, '.') ? (float) $val : (int) $val;
        }

        return $val;
    }

    /**
     * @param  array<string,string>  $row
     * @param  array<string,mixed>  $answers
     */
    private function parseSubmissionTime(array $row, array $answers): ?\Carbon\Carbon
    {
        $raw = $this->pick($row, ['LV']) ?: ($answers['_submission_time'] ?? null);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
