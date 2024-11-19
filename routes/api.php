<?php

use App\Http\Controllers\WhatsappController;
use App\Http\Middleware\TwilioRequestMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/new_message', [WhatsappController::class, 'new_message'])
    ->middleware(TwilioRequestMiddleware::class)
    ->name('new_message');
