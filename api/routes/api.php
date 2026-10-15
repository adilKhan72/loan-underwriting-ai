<?php

use App\Http\Controllers\Api\ApplicationAuditController;
use App\Http\Controllers\Api\ApplicationDocumentController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\SubmitApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('applications', LoanApplicationController::class)->only(['index', 'store', 'show']);
    Route::post('applications/{application}/documents', [ApplicationDocumentController::class, 'store']);
    Route::post('applications/{application}/submit', SubmitApplicationController::class);
    Route::get('applications/{application}/audit', ApplicationAuditController::class);
});
