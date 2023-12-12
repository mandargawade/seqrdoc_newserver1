<?php

namespace App\models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;


class sgrsaAdmin extends Authenticatable
{
    use Notifiable;

    protected $guard = 'admin';
    protected $table = 'admin_table';

    protected $fillable = [
        'fullname','username', 'email','mobile_no', 'password','status','role_id','publish', 'supplier_id', 'agent_id', 'site_id'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    
}
