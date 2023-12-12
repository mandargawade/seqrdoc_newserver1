<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiplomaMET extends Model
{
    public $table="diploma_met";
    public $primaryKey="id";
    public $fillable=['faculty','department','year_of_study','academic_year','award','campus','name','college_number','gender','mathematics','electronics','hospital_plant_&_building_services','measurement_&_control','clinical_engineering_i','clinical_engineering_ii','field_attachment','trade_project','research_project','total_marks_(written_and_practical/900)','exam_marks_(60%)','cats_(40%)','total_(%)','remarks'];
}
