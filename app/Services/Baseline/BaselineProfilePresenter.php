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
     * @return array{has_profile:bool,external_mse_id:?string,imported_at:?string,sections:list<array{key:string,label:string,fields:list<array{key:string,label:string,value:mixed,kind:string}>}>}
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
        foreach ($fields as $field) {
            $section = $field->section instanceof BaselineProfileSection
                ? $field->section
                : BaselineProfileSection::tryFrom((string) $field->section) ?? BaselineProfileSection::Other;

            if (! $section->visibleByDefault() && ! $field->is_visible) {
                continue;
            }

            $key = $section->value;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'label' => $section->label(),
                    'fields' => [],
                ];
            }

            $value = $answers[$field->key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $grouped[$key]['fields'][] = [
                'key' => $field->key,
                'label' => $field->label_ar,
                'value' => $value,
                'kind' => $field->input_kind,
            ];
        }

        $sections = array_values(array_filter($grouped, fn ($s) => $s['fields'] !== []));

        return [
            'has_profile' => $profile !== null,
            'external_mse_id' => $profile?->external_mse_id,
            'imported_at' => $profile?->imported_at?->toDateTimeString(),
            'sections' => $sections,
        ];
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
