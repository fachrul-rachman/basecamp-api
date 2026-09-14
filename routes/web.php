<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Thin internal convenience: logs in via the real POST /api/v1/login
// endpoint (no new auth logic here) and hands the token straight to
// /docs/api, so a SuperAdmin can view the docs without Postman/curl and
// without waiting on a real frontend to exist.
Route::get('/login', function () {
    return view('login');
});
