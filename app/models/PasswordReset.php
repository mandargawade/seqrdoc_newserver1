<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $table='password_resets';
    //protected $primaryKey='id';

    public $timestamps = false;
    
    protected $fillable=['email','token','created_at'];
}
