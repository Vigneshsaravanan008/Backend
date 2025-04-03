<?php

use App\Http\Controllers\api\ApiController;
use Illuminate\Support\Facades\Route;

Route::post('/login',[ApiController::class,'login']);
Route::post('/register',[ApiController::class,'register']);

 //token-expired
Route::get('/token-expired', [ApiController::class, 'token']);

Route::middleware(['apiauth','throttle:global'])->group(function () {
    //User Wallet Amount
    Route::get("/get-wallet-balance",[ApiController::class,'walletBalance']);
    
    //Add Fund
    Route::post("/add-fund",[ApiController::class,'addFund']);
    
    //Transfer Amount
    Route::post("/transfer-fund",[ApiController::class,'transferFund']);
    
    //Debit Amount
    Route::post("/debit-fund",[ApiController::class,'debitFund']);
    
    //Transaction History
    Route::get("/transaction-history",[ApiController::class,'transactionHistory']);
    
    //User Details
    Route::get("/user",[ApiController::class,'userDetails']);
});
