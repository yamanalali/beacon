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

        foreach ($rows as $row) {
            $section = BaselineProfileSection::tryFrom((string) $row['section']) ?? BaselineProfileSection::Other;
            BaselineProfileField::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'excel_column' => $row['excel_column'],
                    'label_ar' => $row['label_ar'],
                    'section' => $section->value,
                    'input_kind' => $row['input_kind'],
                    'is_visible' => (bool) $row['is_visible'],
                    'is_child' => (bool) ($row['is_child'] ?? false),
                    'is_system' => in_array($row['key'], $systemKeys, true) || str_starts_with((string) $row['key'], '_'),
                    'sort_order' => (int) $row['sort_order'],
                ]
            );
        }
    }
}
