<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\DeliveryController;

Route::get('/', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', function (\Illuminate\Http\Request $request) {
    // For now, bypass real password check and just login based on email typed in
    $email = $request->input('email');
    
    // Find the user by email
    $user = \App\Models\User::where('email', $email)->first();

    if (!$user) {
        // If they enter a random email, just fallback to sales dummy for demo purposes
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'sales@test.com'],
            ['name' => 'Sales Person', 'password' => bcrypt('password'), 'role' => 'sales']
        );
    }
    
    Auth::login($user);
    return redirect('/dashboard');
})->name('login.post');

Route::post('/logout', function () {
    Auth::logout();
    return redirect('/');
})->name('logout');

// Web Routes
Route::middleware(['auth'])->group(function () {
    
    // Dashboard (All roles can access)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Quotations (Sales, CSR, PPIC)
    Route::middleware(['role:sales,csr,ppic'])->group(function () {
        Route::resource('quotations', QuotationController::class);
        Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
    });

    // Sales Orders (Sales, CSR, PPIC)
    Route::middleware(['role:sales,csr,ppic'])->group(function () {
        Route::resource('sales_orders', SalesOrderController::class);
        Route::post('sales_orders/{sales_order}/approve', [SalesOrderController::class, 'approve'])->name('sales_orders.approve');
        Route::post('sales_orders/{sales_order}/reject', [SalesOrderController::class, 'reject'])->name('sales_orders.reject');
    });

    // Delivery Orders (Sales, CSR, PPIC, Security)
    Route::middleware(['role:sales,csr,ppic,security'])->group(function () {
        Route::resource('delivery_orders', DeliveryOrderController::class);
        Route::post('delivery_orders/{delivery_order}/approve', [DeliveryOrderController::class, 'approve'])->name('delivery_orders.approve');
        Route::post('delivery_orders/{delivery_order}/reject', [DeliveryOrderController::class, 'reject'])->name('delivery_orders.reject');
    });

    // Deliveries (Sales, CSR, Delivery)
    Route::middleware(['role:sales,csr,delivery'])->group(function () {
        Route::resource('deliveries', DeliveryController::class);
        Route::post('deliveries/{delivery}/close', [DeliveryController::class, 'close'])->name('deliveries.close');
        Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');
        
        Route::middleware('role:delivery')->group(function () {
            Route::patch('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus'])->name('deliveries.updateStatus');
            Route::post('/deliveries/{delivery}/checklist', [DeliveryController::class, 'storeChecklist'])->name('deliveries.storeChecklist');
        });
    });

});
