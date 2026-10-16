<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Dashboard/Index');
    })->name('home');
});

foreach ([
    __DIR__.'/auth.php',
    __DIR__.'/enterprises.php',
    __DIR__.'/advisory.php',
    __DIR__.'/transactions.php',
    __DIR__.'/documents.php',
    __DIR__.'/finance.php',
    __DIR__.'/frontend.php',
] as $routeFile) {
    if (file_exists($routeFile)) {
        require $routeFile;
    }
}
