<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

//\Illuminate\Support\Facades\Cache::set()
