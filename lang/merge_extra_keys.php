<?php

$extra = [
    'transactions.operation' => ['ar' => 'العملية', 'en' => 'Operation'],
    'transactions.counterparty' => ['ar' => 'الجهة', 'en' => 'Counterparty'],
    'transactions.outstanding' => ['ar' => 'المتبقي للدفع', 'en' => 'Outstanding'],
    'transactions.description' => [
        'ar' => 'اطّلع على الدخل والمصروف والمتبقي، ثم راجع العمليات بسرعة.',
        'en' => 'Review income, expenses, and outstanding amounts, then check transactions quickly.',
    ],
    'transactions.add_operation' => ['ar' => 'إضافة عملية', 'en' => 'Add transaction'],
    'transactions.summary_aria' => ['ar' => 'ملخص المعاملات', 'en' => 'Transactions summary'],
    'transactions.remaining' => ['ar' => 'المتبقي', 'en' => 'Remaining'],
    'transactions.posted_count' => ['ar' => 'عملية معتمدة', 'en' => 'posted transactions'],
    'transactions.type' => ['ar' => 'نوع العملية', 'en' => 'Transaction type'],
    'transactions.search' => ['ar' => 'بحث (الجهة أو الوصف)', 'en' => 'Search (party or description)'],
    'transactions.show_corrected' => [
        'ar' => 'إظهار السجلات الأصلية التي تم تصحيحها',
        'en' => 'Show original records that were corrected',
    ],
    'transactions.empty_title' => ['ar' => 'لا توجد عمليات مسجلة', 'en' => 'No transactions recorded'],
    'transactions.empty_filtered' => [
        'ar' => 'جرّب مسح التصفية أو تغيير الفترة.',
        'en' => 'Try clearing filters or changing the period.',
    ],
    'transactions.empty_cta_hint' => [
        'ar' => 'أضف أول عملية ليظهر الملخص هنا.',
        'en' => 'Add your first transaction to see the summary here.',
    ],
    'shell.breadcrumb' => ['ar' => 'مسار الصفحة', 'en' => 'Breadcrumb'],
    'shell.brand_home' => ['ar' => 'الرئيسية', 'en' => 'Home'],
];

foreach (['ar', 'en'] as $loc) {
    $path = __DIR__.'/'.$loc.'.json';
    $data = json_decode((string) file_get_contents($path), true) ?: [];
    foreach ($extra as $k => $pair) {
        $data[$k] = $pair[$loc];
    }
    file_put_contents(
        $path,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
    );
}

echo 'merged '.count($extra)." keys\n";
