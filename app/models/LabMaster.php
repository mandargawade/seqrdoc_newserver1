<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class LabMaster extends Model
{
    protected $table = 'lab_table';

    protected $fillable = [
        'lab_title','created_at', 'created_by','updated_at','updated_by','status','publish'
    ];
}
