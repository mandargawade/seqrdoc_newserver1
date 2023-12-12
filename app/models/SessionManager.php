<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class SessionManager extends Model
{
    protected $table = 'session_manager';

    protected $fillable = [
    	'id','user_id','session_id','login_time','logout_time','is_logged','device_type','ip','latitude','longitude'
    ];

    public $timestamps = false;
}
