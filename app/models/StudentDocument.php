<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class StudentDocument extends Model
{
    public $table='student_documents';

    public $fillable=['doc_id','student_id'];

    public $timestamps=false;
    
}
