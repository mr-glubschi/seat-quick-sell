<?php

use Glubschi\Seat\QuickSell\Http\Controllers\QuickSellController;
use Illuminate\Support\Facades\Route;

// Middleware as used by SeAT core and plugins: web session, logged in, the user's language,
// and the plugin permission. Route names are scoped "seatquicksell::" (SeAT development tips).
Route::group([
    'prefix' => 'quick-sell',
    'middleware' => ['web', 'auth', 'locale', 'can:quicksell.use'],
], function () {
    Route::get('/', [QuickSellController::class, 'index'])->name('seatquicksell::home');
});
