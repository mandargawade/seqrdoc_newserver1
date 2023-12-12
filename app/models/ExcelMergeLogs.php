<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class ExcelMergeLogs extends Model
{
    protected $table='excel_merge_logs';
    protected $fillable=['raw_excel','processed excel','total_unique_records','username','status','date_time'];
}
