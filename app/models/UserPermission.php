<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    protected $table = 'user_permissions';
    protected $fillable =['user_id','permission_id','route_name','created_by','updated_by'];

    public function permissions()
    {
        return $this->belongsToMany('App\Models\AclPermission','user_permissions','permission_id','route_name');
    }
}
