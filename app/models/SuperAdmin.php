<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class SuperAdmin extends Model
{
    protected $table = 'super_admin';

    protected $fillable = ['id','property','value','installation_date','current_value'];

}
