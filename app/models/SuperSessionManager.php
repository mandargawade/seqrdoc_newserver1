<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperSessionManager extends Model
{
	public $connection = "mysql2";
	public $timestamps = false;
    protected $table = 'session_manager';

    protected $fillable = [
    	'id','user_id','session_id','login_time','logout_time','is_logged','ip'
    ];

}
