<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PermitPdfController;

Route::get('/', function () {return view('welcome');});
Route::get('/permits/{permit}/pdf', PermitPdfController::class)->name('permits.pdf');
