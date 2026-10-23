<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\Http\Controllers\Auth\SsoController;
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

/*Route::get('/', function () {
    return view('welcome');
});*/

Route::middleware('throttle:20,1')
    ->prefix('auth/oidc')
    ->name('sso.')
    ->group(function () {
        Route::get('redirect', [SsoController::class, 'redirect'])->name('redirect');
        Route::get('callback', [SsoController::class, 'callback'])->name('callback');
    });
