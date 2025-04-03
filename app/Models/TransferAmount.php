<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferAmount extends Model
{
    protected $guarded=[];
    use SoftDeletes;

    public function fromUser()
    {
        return $this->belongsTo(User::class,'from_user_id','id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class,'to_user_id','id');
    }

    public function fromUserTransaction()
    {
        return $this->belongsTo(Transaction::class,'from_transaction_id','id');
    }

    public function toUserTransaction()
    {
        return $this->belongsTo(Transaction::class,'to_transaction_id','id');
    }
}
