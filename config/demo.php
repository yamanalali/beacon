<?php

use App\Enums\UserRole;

/*
| Demo accounts for local development / UAT only.
| The password is NEVER committed: set DEMO_PASSWORD in your local .env.
*/

return [
    'password' => env('DEMO_PASSWORD'),

    'accounts' => [
        ['email' => 'sme@beacon.test', 'name' => 'مستخدم منشأة تجريبي', 'role' => UserRole::SmeUser->value],
        ['email' => 'advisor@beacon.test', 'name' => 'مستشار تجريبي', 'role' => UserRole::BeaconAdvisor->value],
        ['email' => 'demo-admin@beacon.test', 'name' => 'مدير بيكون تجريبي', 'role' => UserRole::BeaconAdmin->value],
        ['email' => 'mfi@beacon.test', 'name' => 'مستخدم مؤسسة تمويل تجريبي', 'role' => UserRole::MfiUser->value],
        ['email' => 'tech@beacon.test', 'name' => 'مدير تقني تجريبي', 'role' => UserRole::TechnicalAdmin->value],
    ],
];
