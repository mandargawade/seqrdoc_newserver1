<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayConfig extends Model
{
    protected $table='payment_gateway_config';
    protected $primaryKey="id";

    protected $fillable=['pg_id','pg_status','amount','crendential','updated_by'];
}
