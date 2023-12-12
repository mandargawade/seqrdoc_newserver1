<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $guard = 'admin';
    protected $table = 'admin_table';

    protected $fillable = [
        'fullname','username', 'email','mobile_no', 'password','status','role_id','publish',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];
}
