<?php

$extra = [
    'smes.create_title' => ['ar' => 'منشأة جديدة', 'en' => 'New enterprise'],
    'smes.register_title' => ['ar' => 'تسجيل منشأة', 'en' => 'Register enterprise'],
    'smes.step_1_of_1' => ['ar' => 'الخطوة 1 من 1', 'en' => 'Step 1 of 1'],
    'smes.starter_fields' => ['ar' => 'البيانات اللازمة للبدء', 'en' => 'Details needed to start'],
    'smes.business_name' => ['ar' => 'اسم المنشأة', 'en' => 'Business name'],
    'smes.location' => ['ar' => 'الموقع', 'en' => 'Location'],
    'smes.location_placeholder' => ['ar' => 'اختر الموقع', 'en' => 'Select location'],
    'smes.location.damascus' => ['ar' => 'دمشق', 'en' => 'Damascus'],
    'smes.location.rural_damascus' => ['ar' => 'ريف دمشق', 'en' => 'Rural Damascus'],
    'smes.location.daraa' => ['ar' => 'درعا', 'en' => 'Daraa'],
    'smes.contact_phone' => ['ar' => 'هاتف التواصل', 'en' => 'Contact phone'],
    'smes.contact_email' => ['ar' => 'البريد الإلكتروني', 'en' => 'Email'],
    'smes.contact_help' => ['ar' => 'الهاتف أو البريد كافٍ للبدء.', 'en' => 'Phone or email is enough to start.'],
    'smes.save_enterprise' => ['ar' => 'حفظ المنشأة', 'en' => 'Save enterprise'],
    'shell.main_nav' => ['ar' => 'التنقل الرئيسي', 'en' => 'Main navigation'],
];

foreach (['ar', 'en'] as $loc) {
    $path = __DIR__.'/'.$loc.'.json';
    $data = json_decode((string) file_get_contents($path), true) ?: [];
    foreach ($extra as $k => $pair) {
        $data[$k] = $pair[$loc];
    }
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

echo 'merged '.count($extra)." sme keys\n";
