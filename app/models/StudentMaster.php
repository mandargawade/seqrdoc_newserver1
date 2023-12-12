<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class StudentMaster extends Model
{
    public $table='student_master';
    public $fillable=['enrollment_no','date_of_birth'];
    
}
