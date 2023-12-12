<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class SbTransactions extends Model
{
    protected $table = 'sb_transactions';

    protected $fillable = [
        'pay_gateway_id','trans_id_ref', 'trans_id_gateway','payment_mode', 'amount','additional','user_id','student_key','trans_status','publish','created_at','updated_at'
    ];
}
