<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class FFReasonLog extends Model
{
    protected $table = 'ff_reason_log';

    protected $fillable = [
    	'ff_id',
        'reason',
        'status',
		'admin_id'
    ];
}
