<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class ScanningRequests extends Model
{
    protected $guard = 'admin';
    protected $table = 'scanning_requests';

    protected $fillable = [
        'request_number','user_id','no_of_documents', 'total_amount','payment_status', 'device_type'
    ];
    public $timestamps = false;
}
