<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class DemoERPData extends Model
{
    protected $table = 'demo_erp_data';

    protected $fillable = [
    	'unique_id','student_name','mother_name','degree_type','degree_name','passing_year','cgpa','status','printable_pdf_link','request_id','api_data','created_at','updated_at'
    ];
}
