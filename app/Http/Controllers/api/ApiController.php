<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransferAmount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class ApiController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(),[
            "email"=>"required",
            "password"=>"required"
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400,'errors' => $validator->errors()->all()]);
        }

        try {
            if(User::where("email",request("email"))->exists())
            {
                $user = User::where('email', $request->email)->first();
                $credentials = request(['email', 'password']);
                if (!Auth::attempt($credentials)) {
                    return response()->json([
                        'status' => 401,
                        'message' => "Password Incorrect",
                    ]);
                }
                $token = $user->createToken('token')->accessToken;
                return response()->json([
                    'status' => 200,
                    'user' => $user,
                    'access_token' => $token,
                ]);
            }else{
                return response()->json([
                    'status' => 400,
                    'message' => "Invalid credentials",
                ]);
            }
        }catch(\Exception $e)
        {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function register(Request $request)
    {
        DB::beginTransaction();

        $validator = Validator::make($request->all(),[
            'name'=>"required|max:255",
            "email"=>"required|unique:users|email|max:255",
            "phone_number"=>"required|numeric|digits_between:10,15",
            "address"=>"nullable",
            "currency"=>"required",
            "password" => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400,'errors' => $validator->errors()->all()]);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'currency' => $request->currency,
                'password' => Hash::make($request->password),
                'wallet_amount'=>0
            ]);

            DB::commit();
            return response()->json([
                'status' => 200,
                'user' => $user,
                'access_token' => $user->createToken('token')->accessToken,
            ]);
        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function token()
    {
        return response()->json([
            'status' => 400,
            'token_expired' => true,
            'message' => 'User is Unauthenticated',
        ]);
    }

    public function userDetails()
    {
        $user_details=User::where("id",Auth::guard("api")->user()->id)->first();
        return response()->json([
            "status"=>200,
            "user_details"=>$user_details
        ]);
    }

    public function walletBalance()
    {
        $wallets=Transaction::where("user_id", Auth::guard("api")->user()->id)->orderBy("id","desc")->select("id","user_id","type","amount")->paginate(15);
        $user_details=User::where("id",Auth::guard("api")->user()->id)->pluck("wallet_amount")->first();
        return response()->json(['status'=>200,'wallet_transaction_lists'=>$wallets,"total_wallet_amount"=>$user_details]);
    }

    public function addFund(Request $request)
    {
        DB::beginTransaction();

        $validator = Validator::make($request->all(),[
            "amount"=>"required|integer",
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400,'errors' => $validator->errors()->all()]);
        }

        if($this->isSuspiciousTransaction($request->amount))
        {
            return response()->json([
                'status' => 400,
                'message' => 'Suspicious activity detected: Too many high-value transactions in a short period.',
            ]);
        }

        $todayTotal = Transaction::where('user_id', Auth::guard("api")->user()->id)
            ->whereDate('created_at', Carbon::today())
            ->where('type', 1)
            ->sum('amount');

        $dailyLimit=env("DAILY_LIMIT");

        if (($todayTotal + $request->amount) > $dailyLimit) {
            return response()->json([
                'status'=>403,
                'message' => 'Daily transaction limit exceeded!',
                'remaining_limit' => max(0, $dailyLimit - $todayTotal)
            ]);
        }

        try{
            Transaction::create([
                "user_id"=>Auth::guard("api")->user()->id,
                "amount"=>$request->amount,
                "type"=>1,
            ]);

            User::where("id",Auth::guard("api")->user()->id)->update([
                "wallet_amount"=>Auth::guard("api")->user()->wallet_amount+$request->amount
            ]);

            $wallets=Transaction::where("user_id", Auth::guard("api")->user()->id)->orderBy("id","desc")->paginate(15);
            DB::commit();

            return response()->json([
                'status' => 200,
                'wallet_transaction_lists'=>$wallets,
                'message' => 'Wallet Amount added',
            ]);
        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ]); 
        }
    }

    public function transferFund(Request $request)
    {
        DB::beginTransaction();

        $validator = Validator::make($request->all(),[
            "amount"=>"required|integer",
            "to_user_id"=>"required",
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400,'errors' => $validator->errors()->all()]);
        }

        if(!User::where("id",$request->to_user_id)->exists())
        {
            return response()->json(['status'=>400,"message"=>"User Not Found"]);
        }

        if($this->isSuspiciousTransaction($request->amount,Auth::guard("api")->user()->id,$request->to_user_id))
        {
            return response()->json([
                'status' => 400,
                'message' => 'Suspicious activity detected: Too many high-value transactions in a short period.',
            ]);
        }

        try{
            if(Auth::guard("api")->user()->wallet_amount>=$request->amount)
            {
                $to_user= User::where("id",$request->to_user_id)->first();
                $convert=true;
                $from_currency=Auth::guard("api")->user()->currency;
                if($to_user->currency == $from_currency)
                {
                    $convert=false;
                    $to_amount=$request->amount;
                }

                if($convert)
                {
                    try{
                        $apiKey = env('EXCHANGE_RATE_API');
                        $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/pair/{$from_currency}/{$to_user->currency}";
                        $response = Http::get($url);
                        if (!$response->successful()) {
                            return response()->json([
                                'status'=>400,
                                'error'=>"Failed to fetch exchange rates"
                            ]);
                        }
                        $value=$response->json();
                        $to_amount=($request->amount)*$value['conversion_rate'];
                    }catch(\Exception $e)
                    {
                        return response()->json([
                            'status'=>400,
                            'message'=>$e->getMessage()
                        ]);
                    }
                }

                $from_transaction_id = Transaction::create([
                    "user_id"=>Auth::guard("api")->user()->id,
                    "amount"=>$request->amount,
                    "type"=>2,
                ])->id;

                User::where("id",Auth::guard("api")->user()->id)->update([
                    "wallet_amount"=>Auth::guard("api")->user()->wallet_amount-$request->amount
                ]);

                $to_transaction_id = Transaction::create([
                    "user_id"=>$request->to_user_id,
                    "amount"=>$to_amount,
                    "type"=>1,
                ])->id;

               
                $to_user->wallet_amount=$to_user->wallet_amount+$to_amount;
                $to_user->save();

                $transfer_amount_id = TransferAmount::create([
                    "from_user_id"=>Auth::guard("api")->user()->id,
                    "to_user_id"=>$request->to_user_id,
                    "amount"=>$request->amount,
                    "from_transaction_id"=>$from_transaction_id,
                    "to_transaction_id"=>$to_transaction_id
                ])->id;

                $transfer_amount=TransferAmount::where("id",$transfer_amount_id)->with("fromUser:id,name,email","toUser:id,name,email","fromUserTransaction","toUserTransaction")->first();
                DB::commit();

                return response()->json(['status'=>200,"message"=>"Amount Transfered Successfully","transferAmount"=>$transfer_amount]);

            }else{
                return response()->json([
                    'status' => 400,
                    'message' => "Insufficient Balance",
                ]);
            }
        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ]); 
        }
    }

    public function debitFund(Request $request)
    {
        DB::beginTransaction(); 

        $validator = Validator::make($request->all(),[
            "amount"=>"required|integer",
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400,'errors' => $validator->errors()->all()]);
        }

        if($this->isSuspiciousTransaction($request->amount))
        {
            return response()->json([
                'status' => 400,
                'message' => 'Suspicious activity detected: Too many high-value transactions in a short period.',
            ]);
        }

        try{
            if(Auth::guard("api")->user()->wallet_amount>=$request->amount)
            {
                Transaction::create([
                    "user_id"=>Auth::guard("api")->user()->id,
                    "amount"=>$request->amount,
                    "type"=>2,
                ]);

                User::where("id",Auth::guard("api")->user()->id)->update([
                    "wallet_amount"=>Auth::guard("api")->user()->wallet_amount-$request->amount
                ]);

                $wallets=Transaction::where("user_id", Auth::guard("api")->user()->id)->orderBy("id","desc")->paginate(15);
                DB::commit();

                return response()->json([
                    'status' => 200,
                    'wallet_transaction_lists'=>$wallets,
                    'message' => 'Wallet Amount Debited',
                ]);
            }else{
                return response()->json([
                    'status' => 400,
                    'message' => "Insufficient Balance",
                ]);
            }

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ]); 
        }
    }

    public function transactionHistory()
    {
        $wallets=Transaction::where("user_id", Auth::guard("api")->user()->id)->orderBy("id","desc")->paginate(15);
        return response()->json(['status'=>200,'transaction_histories'=>$wallets]);
    }

    public function isSuspiciousTransaction($amount,$from_user_id=null,$to_user_id=null)
    {
        $maximum_amount = env("MAXIMUM_AMOUNT"); 
        $time = env("TIME_PERIOD"); 

        $now = Carbon::now();
        $start_time = $now->subMinutes($time);
        $userId=$from_user_id ?? Auth::guard("api")->user()->id; 
        $toUserId=$to_user_id; 
        $fromUserTransactions = Transaction::where('user_id', $userId)
            ->where('created_at', '>=', $start_time)
            ->sum("amount");
        if ($fromUserTransactions+$amount >= $maximum_amount) {
            return true;
        }

        if($toUserId)
        {
            $toUserTransactions = Transaction::where('user_id', $toUserId)
            ->where('created_at', '>=', $start_time)
            ->sum("amount");

            if ($toUserTransactions+$amount >= $maximum_amount) {
                return true;
            }
        }
        
        return false;
    }
}
