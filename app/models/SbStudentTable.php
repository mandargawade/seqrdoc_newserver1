<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class SbStudentTable extends Model
{
    protected $table = 'sb_student_table';

    protected $fillable = [
        'serial_no','student_name', 'certificate_filename','template_id', 'key','path','created_by','updated_by','status','publish','scan_count','site_id'
    ];
}
