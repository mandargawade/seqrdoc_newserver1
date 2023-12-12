<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $table='payment_gateway';
    protected $primaryKey='id';
    protected $fillable=['pg_name','merchant_key','merchant_key','salt','status','publish','test_merchant_key','test_salt','updated_by'];
}
