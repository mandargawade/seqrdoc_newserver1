<?php

namespace App\models\Demo;

use Illuminate\Database\Eloquent\Model;

class SuperAdmin extends Model
{
	protected $connection = 'mysql1';
    protected $table = 'super_admin';

    protected $fillable = ['id','property','value','installation_date','current_value'];
}
