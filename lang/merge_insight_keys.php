<?php

$extra = [
    'ai.insights.insufficient_records' => [
        'ar' => 'لا توجد سجلات كافية في هذه الفترة.',
        'en' => 'There are not enough records in this period.',
    ],
    'ai.insights.review_flags_limit' => [
        'ar' => 'توجد عمليات تحتاج مراجعة (مكررة محتملة أو ناقصة البيانات)، وقد تقلّل من دقة هذه الملاحظات.',
        'en' => 'Some transactions need review (possible duplicates or missing data), which may reduce the usefulness of these notes.',
    ],
];

foreach (['ar', 'en'] as $loc) {
    $path = __DIR__.'/'.$loc.'.json';
    $data = json_decode((string) file_get_contents($path), true) ?: [];
    foreach ($extra as $k => $pair) {
        $data[$k] = $pair[$loc];
    }
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

echo "ok\n";
