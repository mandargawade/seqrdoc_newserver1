<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class InstituteMaster extends Model
{
    protected $table = 'institute_table';

    protected $fillable = [
        'institute_username','username', 'password','created_at', 'created_by','updated_at','updated_by','status','publish'
    ];
}
