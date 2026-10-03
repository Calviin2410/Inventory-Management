<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    $frontend = public_path('index.html');

    if (! file_exists($frontend)) {
        return view('welcome');
    }

    return response()->file($frontend);
})->where('path', '^(?!api(?:/|$)).*');
