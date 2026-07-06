<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// This service is API-only (the React app lives in /frontend); point visitors
// at the API instead of serving a template.
Route::get('/', fn () => response()->json([
    'service' => 'GoDoc Booking API',
    'docs' => 'see README.md',
    'api' => url('/api'),
]));
