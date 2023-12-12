<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $table = 'role_permissions';

    protected $fillable = ['role_id','permission_id','created_by','updated_by'];

    public function getPermission(){
        return $this->hasOne('App\models\AclPermission','id','permission_id');
    }
}
