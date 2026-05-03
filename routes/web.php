<?php

use App\Http\Controllers\CameraController;
use App\Http\Controllers\SignalingController;
use App\Http\Controllers\ViewerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/camera', [CameraController::class, 'index']);
Route::get('/viewer', [ViewerController::class, 'index']);
Route::post('/signal/join', [SignalingController::class, 'join']);
Route::post('/signal/offer', [SignalingController::class, 'sendOffer']);
Route::post('/signal/answer', [SignalingController::class, 'sendAnswer']);
Route::post('/signal/ice', [SignalingController::class, 'sendIce']);
Route::get('/signal/poll', [SignalingController::class, 'poll']);
Route::post('/signal/status', [SignalingController::class, 'updateStatus']);
Route::get('/signal/status', [SignalingController::class, 'getStatus']);
