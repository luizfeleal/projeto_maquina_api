<?php

use Illuminate\Support\Facades\Route;
use App\Support\HttpMetrics;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return 'funcionou';
    return view('welcome');
});

Route::get('/metrics', function () {
    return response(HttpMetrics::export(), 200)
        ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
});
