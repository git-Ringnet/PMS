<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $indexPath = public_path('build/index.html');
    if (file_exists($indexPath)) {
        return file_get_contents($indexPath);
    }
    return 'Frontend assets not built. Please run `npm run build` in the frontend directory.';
});

Route::fallback(function () {
    $indexPath = public_path('build/index.html');
    if (file_exists($indexPath)) {
        return file_get_contents($indexPath);
    }
    return response('Page not found or frontend assets not built.', 404);
});
