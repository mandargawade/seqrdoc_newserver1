<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class ExceUploadHistory extends Model
{
    protected $table = 'excelupload_history';

    protected $fillable = ['template_name','excel_sheet_name','user','no_of_records','created_on'];

}
