<?php

$extra = [
    'validation.attr.code' => ['ar' => 'الرمز', 'en' => 'code'],
    'validation.attr.label_ar' => ['ar' => 'الاسم', 'en' => 'name'],
    'validation.attr.weight' => ['ar' => 'الوزن', 'en' => 'weight'],
    'validation.attr.min_score' => ['ar' => 'الحد الأدنى', 'en' => 'minimum score'],
    'validation.attr.sort_order' => ['ar' => 'الترتيب', 'en' => 'order'],
    'validation.attr.type' => ['ar' => 'النوع', 'en' => 'type'],
    'validation.attr.config' => ['ar' => 'الإعدادات', 'en' => 'settings'],
    'validation.attr.min' => ['ar' => 'من', 'en' => 'from'],
    'validation.attr.max' => ['ar' => 'إلى', 'en' => 'to'],
    'validation.attr.color_token' => ['ar' => 'اللون', 'en' => 'color'],
    'validation.attr.ids' => ['ar' => 'الترتيب', 'en' => 'order'],
    'validation.attr.answers' => ['ar' => 'الإجابات', 'en' => 'answers'],
    'validation.attr.definition' => ['ar' => 'التعريف', 'en' => 'definition'],
    'validation.attr.readiness_pillar_id' => ['ar' => 'المحور', 'en' => 'pillar'],
    'validation.attr.missing_data_treatment' => ['ar' => 'معالجة البيانات الناقصة', 'en' => 'missing data treatment'],
];

foreach (['ar', 'en'] as $loc) {
    $path = __DIR__.'/'.$loc.'.json';
    $data = json_decode((string) file_get_contents($path), true) ?: [];
    foreach ($extra as $k => $pair) {
        $data[$k] = $pair[$loc];
    }
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

echo 'merged '.count($extra)." attr keys\n";
