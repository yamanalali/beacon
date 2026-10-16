<?php

return [
    // Private disk (storage/app/private). Never a public disk.
    'disk' => env('DOCUMENTS_DISK', 'local'),

    'max_kilobytes' => 10240,

    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],

    'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
];
