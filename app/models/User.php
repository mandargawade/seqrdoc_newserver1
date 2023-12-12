<?php

namespace App\models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable,HasApiTokens;

    protected $guard = 'webuser';
    protected $table = 'user_table';

    
    //private $fields = \Schema::getColumnListing('user_table');
    
    protected $fillable = [
        'fullname','username', 'email_id','mobile_no', 'password','status','role_id','publish','verify_by','is_verified','is_admin','token','OTP','site_id','device_type','print_limit','created_at'
    ];
	//}
    protected $hidden = [
        'password', 'remember_token',
    ];

   

  

}
