<?php

namespace Database\Seeders;

use App\Enums\BaselineProfileSection;
use App\Models\BaselineProfileField;
use Illuminate\Database\Seeder;

class BaselineFieldCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $rows = require database_path('data/baseline_field_catalog.php');
        $systemKeys = ['start', 'end', 'today', 'i01_mse_id', '_id', '_uuid', '_submission_time', '_status', '__version__', '_index'];

        // Display corrections for KoBo export quirks (section dump / orphan "other" labels).
        $sectionOverrides = [
            'col_p' => BaselineProfileSection::Eligibility->value,
            'col_ba' => BaselineProfileSection::Eligibility->value,
            'col_be' => BaselineProfileSection::Eligibility->value,
            'col_bk' => BaselineProfileSection::OwnerWorkforce->value,
        ];
        $labelOverrides = [
            'col_bk' => 'دور آخر في المنشأة (حدّد)',
            'col_q' => 'هل توافق على تسجيل اسم المحل واسمك ورقم هاتفك وعنوانك؟ (البيانات سرية وبوصول مقيّد لفريق بيكون)',
        ];

        foreach ($rows as $row) {
            $section = BaselineProfileSection::tryFrom((string) $row['section']) ?? BaselineProfileSection::Other;
            $sectionValue = $sectionOverrides[$row['key']] ?? $section->value;
            $label = $labelOverrides[$row['key']] ?? $row['label_ar'];
            $isVisible = (bool) $row['is_visible'];

            // Generic orphan "أخرى" rows clutter the profile; keep them admin-toggleable only.
            if ($label === 'أخرى (حدّد)' && $sectionValue === BaselineProfileSection::Other->value) {
                $isVisible = false;
            }

            BaselineProfileField::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'excel_column' => $row['excel_column'],
                    'label_ar' => $label,
                    'section' => $sectionValue,
                    'input_kind' => $row['input_kind'],
                    'is_visible' => $isVisible,
                    'is_child' => (bool) ($row['is_child'] ?? false),
                    'is_system' => in_array($row['key'], $systemKeys, true) || str_starts_with((string) $row['key'], '_'),
                    'sort_order' => (int) $row['sort_order'],
                ]
            );
        }
    }
}
