<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\MainController;

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
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy'); 

    Route::get('/orders', function () {
        return Inertia::render('List');
    })->name('orders');

    Route::get('/reports', function () {
        return Inertia::render('Reports');
    })->name('reports');

    Route::get('/inventory', function () {
        return Inertia::render('Inventory');
    })->name('inventory');

    // Specific orders endpoint route
    // Route::any('/bc-api/v2/orders/{orderId?}', [MainController::class, 'proxyBigCommerceAPIRequest'])
    //     ->where('orderId', '[0-9]+')
    //     ->defaults('endpoint', 'v2/orders');

    // Catch-all route
    Route::any('/bc-api/{endpoint}', [MainController::class, 'proxyBigCommerceAPIRequest'])
        ->where('endpoint', 'v2/.*|v3/.*');
    });
require __DIR__.'/auth.php';
