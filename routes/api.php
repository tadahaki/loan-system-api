<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\LoanController;

// ADD THIS
use App\Models\User;
use App\Models\Loan;

// ---- YOUR EXISTING ROUTES ---- //
Route::get('/loans', [LoanController::class, 'index']);
Route::post('/loans', [LoanController::class, 'store']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/register', [BorrowController::class, 'register']);
Route::post('/login', [BorrowController::class, 'login']);
Route::patch('/loans/{id}/status', [LoanController::class, 'updateStatus']);
Route::get('/loans/approved', [LoanController::class, 'getApprovedLoans']);
Route::get('/loans/user/{userId}', [LoanController::class, 'getUserLoans']);
Route::get('/loans/user/{userId}/details', [LoanController::class, 'getUserLoanDetails']);
Route::put('/loans/{id}/details', [LoanController::class, 'updateLoanDetails']);
Route::post('/payments/process', [LoanController::class, 'processPayment']);
Route::get('/loans/{loanId}/schedule', [LoanController::class, 'getRepaymentSchedule']);
Route::get('/loans/{loanId}/payments', [LoanController::class, 'getLoanPayments']);
Route::post('/payments/process-gcash', [LoanController::class, 'processGcashPayment']);
Route::put('/payments/{paymentId}/verify', [LoanController::class, 'verifyPayment']);
Route::put('/payments/{payment}', [LoanController::class, 'updatePayment']);
Route::put('/payments/{paymentId}/status', [LoanController::class, 'updatePaymentStatus']);
Route::get('/loans/{loan}/unpaid-balance', [LoanController::class, 'getUnpaidBalance']);
Route::get('/loans/all', [LoanController::class, 'getAllLoans']);
Route::get('/users/{userId}/payments', [LoanController::class, 'getUserPayments']);

// ---- ADD NEW DASHBOARD STATS ROUTE HERE ---- //

Route::get('/admin/users', function () {
    return User::orderBy('created_at', 'desc')->get();
});

Route::get('/admin/stats', function () {

    return response()->json([
        'users' => User::count(),

        'loans' => Loan::count(),

        'ongoing' => Loan::where('status', 'ongoing')->count(),

        'monthly' => Loan::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get(),

        'distribution' => [
            'personal' => Loan::where('type', 'personal')->count(),
            'business' => Loan::where('type', 'business')->count(),
            'education' => Loan::where('type', 'education')->count(),
            'emergency' => Loan::where('type', 'emergency')->count(),
            'other' => Loan::where('type', 'other')->count(),
        ]
    ]);
});

// Sanctum default route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
