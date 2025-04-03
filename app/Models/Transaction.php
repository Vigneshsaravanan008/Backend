<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    protected $guarded=[];
    use SoftDeletes;

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }
}
