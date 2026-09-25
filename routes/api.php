<?php

use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::post('/shifts', [ShiftController::class, 'store']);
