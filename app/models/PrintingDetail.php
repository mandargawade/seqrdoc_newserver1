<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class PrintingDetail extends Model
{
    protected $table = 'printing_details';

    protected $fillable = ['username','print_datetime','printer_name','print_count','print_serial_no','sr_no','template_name','card_serial_no','scan_count','created_by','updated_by','reprint','status','publish','site_id','student_table_id'];

    public $timestamps = true;
}


