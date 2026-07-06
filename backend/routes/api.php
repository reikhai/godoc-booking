<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\DoctorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Consultation Booking
|--------------------------------------------------------------------------
*/

Route::get('/health', fn () => ['status' => 'ok']);

// Doctors and their available slots.
Route::get('/doctors', [DoctorController::class, 'index']);
Route::get('/doctors/{doctor}/slots', [DoctorController::class, 'slots']);

// Bookings.
Route::get('/bookings', [BookingController::class, 'index']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings/{booking}', [BookingController::class, 'show']);

// State-machine transitions.
Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm']);
Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete']);
