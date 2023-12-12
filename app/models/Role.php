<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table="roles";
    
    public function getNameAttribute($value)
    {
    	return $this->attribute = ucfirst($value);
    }

    public function permissions()
    {
        return $this->belongsToMany('App\models\AclPermission','user_permissions','permission_id','route_name');
    }
}
