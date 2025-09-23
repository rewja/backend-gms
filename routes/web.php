<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SPA fallback: serve built frontend for any non-API route
Route::get('/{any}', function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    // Fallback to default view if build not present
    return view('welcome');
})->where('any', '^(?!api).*$');
