<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class ConsumptionReport extends Model
{
    protected $guard = 'admin';
    protected $table = 'grade_card_data';

    protected $fillable = [
        'roll_no','result_no', 'student_name','registration_no','enrollment_no','term','examination','programme','department','scheme','student_type','section','serial_no','updated_by','created_at', 'updated_at'
    ];
}
