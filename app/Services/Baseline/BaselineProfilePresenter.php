<?php

namespace App\Services\Baseline;

use App\Enums\BaselineProfileSection;
use App\Models\BaselineProfileField;
use App\Models\Enterprise;
use App\Models\EnterpriseBaselineProfile;

class BaselineProfilePresenter
{
    /**
     * Visible sections/fields only — never send hidden catalog fields to the client.
     *
     * @return array{
     *   has_profile:bool,
     *   external_mse_id:?string,
     *   imported_at:?string,
     *   highlight:list<array{key:string,label:string,value:string}>,
     *   sections:list<array{key:string,label:string,fields:list<array{key:string,label:string,value:mixed,kind:string,values?:list<string>}>}>
     * }
     */
    public function forEnterprise(Enterprise $enterprise): array
    {
        /** @var EnterpriseBaselineProfile|null $profile */
        $profile = $enterprise->baselineProfile;
        $answers = $profile?->answers ?? [];

        $fields = BaselineProfileField::query()
            ->where('is_visible', true)
            ->where('is_child', false)
            ->orderBy('sort_order')
            ->get();

        $grouped = [];
        foreach (BaselineProfileSection::cases() as $sectionCase) {
            $grouped[$sectionCase->value] = [
                'key' => $sectionCase->value,
                'label' => $sectionCase->label(),
                'fields' => [],
            ];
        }

        foreach ($fields as $field) {
            $section = $field->section instanceof BaselineProfileSection
                ? $field->section
                : BaselineProfileSection::tryFrom((string) $field->section) ?? BaselineProfileSection::Other;

            if (! $section->visibleByDefault() && ! $field->is_visible) {
                continue;
            }

            $value = $answers[$field->key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $entry = [
                'key' => $field->key,
                'label' => $this->displayLabel($field->label_ar),
                'value' => $value,
                'kind' => $field->input_kind,
            ];

            $values = $this->splitMultiValues($field->input_kind, $value);
            if ($values !== null) {
                $entry['values'] = $values;
            }

            $grouped[$section->value]['fields'][] = $entry;
        }

        $sections = array_values(array_filter($grouped, fn ($s) => $s['fields'] !== []));

        return [
            'has_profile' => $profile !== null,
            'external_mse_id' => $profile?->external_mse_id,
            'imported_at' => $profile?->imported_at?->toDateTimeString(),
            'highlight' => $this->highlightFromAnswers($answers),
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string,mixed>  $answers
     * @return list<array{key:string,label:string,value:string}>
     */
    private function highlightFromAnswers(array $answers): array
    {
        $map = [
            'col_s' => 'اسم المنشأة',
            'col_t' => 'المالك',
            'col_u' => 'الهاتف',
            'col_v' => 'العنوان',
            'col_ah' => 'نوع النشاط',
            'col_ai' => 'النشاط الرئيسي',
        ];

        $items = [];
        foreach ($map as $key => $label) {
            $value = $answers[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $items[] = [
                'key' => $key,
                'label' => $label,
                'value' => is_scalar($value) ? (string) $value : '',
            ];
        }

        return $items;
    }

    private function displayLabel(string $label): string
    {
        $label = trim($label);
        if ($label === 'أخرى (حدّد)') {
            return 'تفاصيل إضافية';
        }

        // Drop truncated ellipsis leftovers from KoBo label export.
        return rtrim($label, "….");
    }

    /**
     * @return list<string>|null
     */
    private function splitMultiValues(string $kind, mixed $value): ?array
    {
        if ($kind !== 'multi_choice' || ! is_string($value)) {
            return null;
        }

        $parts = preg_split('/\n+|؛\s*|;\s*|\s*\|\s*/u', $value) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

        return count($parts) > 1 ? $parts : null;
    }

    /**
     * @return list<array{key:string,label:string,is_visible:bool,fields:list<array{id:int,key:string,label:string,is_visible:bool,is_child:bool,is_system:bool}>}>
     */
    public function adminCatalog(): array
    {
        $fields = BaselineProfileField::query()->orderBy('sort_order')->get();
        $grouped = [];
        foreach (BaselineProfileSection::cases() as $section) {
            $grouped[$section->value] = [
                'key' => $section->value,
                'label' => $section->label(),
                'is_visible' => $fields
                    ->filter(fn ($f) => ($f->section instanceof BaselineProfileSection ? $f->section->value : (string) $f->section) === $section->value)
                    ->where('is_child', false)
                    ->contains(fn ($f) => $f->is_visible),
                'fields' => [],
            ];
        }

        foreach ($fields as $field) {
            $sectionKey = $field->section instanceof BaselineProfileSection
                ? $field->section->value
                : (string) $field->section;
            if (! isset($grouped[$sectionKey])) {
                continue;
            }
            $grouped[$sectionKey]['fields'][] = [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label_ar,
                'is_visible' => $field->is_visible,
                'is_child' => $field->is_child,
                'is_system' => $field->is_system,
            ];
        }

        return array_values($grouped);
    }
}
