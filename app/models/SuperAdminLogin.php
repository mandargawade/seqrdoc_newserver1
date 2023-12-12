<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
class SuperAdminLogin extends Authenticatable
{   
    public $guard="superadmin";
    public $table="super_admin_login";

    public $fillable=['username','password','role_id','publish'];

    public $hidden=['password'];
}
